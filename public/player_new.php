<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();
ensureCsrfToken();

['data' => $characters] = fetchData(fn() => Character::all());

// POST処理
$validationError = '';
$postName = '';
$postNickname = '';
$postCharacterId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        http_response_code(403);
        $validationError = '不正なリクエストです。ページを再読み込みしてください。';
    } else {
        $postName = sanitizeInput('name');
        $postNickname = sanitizeInput('nickname');
        $postCharacterId = filter_input(INPUT_POST, 'character_id', FILTER_VALIDATE_INT);

        if ($postName === '') {
            $validationError = 'プレイヤー名を入力してください。';
        } elseif (mb_strlen($postName) > 50) {
            $validationError = 'プレイヤー名は50文字以内で入力してください。';
        } elseif ($postNickname === '') {
            $validationError = '呼称を入力してください。';
        } elseif (mb_strlen($postNickname) > 50) {
            $validationError = '呼称は50文字以内で入力してください。';
        } elseif (!$postCharacterId) {
            $validationError = 'キャラクターを選択してください。';
        } elseif (!array_filter($characters ?? [], fn($c) => (int)$c['id'] === $postCharacterId)) {
            $validationError = '不正なキャラクター選択です。';
        } elseif (Player::existsByName($postName)) {
            $validationError = 'このプレイヤー名は既に使用されています。';
        } else {
            try {
                $newId = Player::create($postName, $postNickname, $postCharacterId);
                regenerateCsrfToken();
                startSecureSession();
                $_SESSION['flash'] = '選手を登録しました。';
                header('Location: player?id=' . $newId);
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23505') {
                    $validationError = 'このプレイヤー名は既に使用されています。';
                } else {
                    error_log('[DB] ' . $e->getMessage());
                    $validationError = '登録に失敗しました。';
                }
            }
        }
    }
}

// --- テンプレート変数 ---
$pageTitle = '選手登録 - ' . SITE_NAME;
$pageCss = ['css/forms.css'];
$pageStyle = <<<'CSS'
.edit-form { max-width: 600px; }
.edit-label { margin-bottom: 12px; }
CSS;

require __DIR__ . '/../templates/header.php';
?>

<div class="edit-hero">
  <div class="edit-badge">NEW PLAYER</div>
  <div class="edit-title">選手登録</div>
  <div class="edit-subtitle">新しい選手を追加します</div>
</div>

<div class="edit-form">
  <?php if ($validationError): ?>
    <div class="edit-message error"><?= h($validationError) ?></div>
  <?php endif; ?>

  <form method="post" action="player_new">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

    <div class="edit-section">
      <label class="edit-label" for="input-name">プレイヤー名</label>
      <input type="text" id="input-name" name="name" class="edit-input" value="<?= h($postName) ?>" maxlength="50" required>
      <div class="edit-hint">雀魂アプリ内の正式名称。後から変更できませんので、注意してください。</div>
    </div>

    <div class="edit-section">
      <label class="edit-label" for="input-nickname">呼称</label>
      <input type="text" id="input-nickname" name="nickname" class="edit-input" value="<?= h($postNickname) ?>" maxlength="50" required>
      <div class="edit-hint">サイト上で表示される通称。後から変更可能です。</div>
    </div>

    <div class="edit-section">
      <label class="edit-label">使用キャラクター</label>
      <div class="chara-grid">
        <?php foreach ($characters ?? [] as $c): ?>
          <label class="chara-option">
            <input type="radio" name="character_id" value="<?= (int)$c['id'] ?>" <?= $postCharacterId === (int)$c['id'] ? 'checked' : '' ?>>
            <div class="chara-option-inner">
              <?php if ($c['icon_filename']): ?>
                <img src="img/chara_deformed/<?= h($c['icon_filename']) ?>" alt="<?= h($c['name']) ?>" class="chara-option-icon" width="48" height="48" loading="lazy">
              <?php else: ?>
                <div class="chara-option-noicon">NO<br>IMAGE</div>
              <?php endif; ?>
              <span class="chara-option-name"><?= h($c['name']) ?></span>
            </div>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="edit-actions">
      <a href="players" class="btn-cancel">&#x2190; 選手一覧に戻る</a>
      <button type="submit" class="btn-save">登録</button>
    </div>

  </form>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
