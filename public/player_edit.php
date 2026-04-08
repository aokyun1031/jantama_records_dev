<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();
ensureCsrfToken();

// --- バリデーション ---
$playerId = requirePlayerId();
$player = requirePlayer($playerId);

['data' => $characters] = fetchData(fn() => Character::all());
['data' => $hasTournaments] = fetchData(fn() => Player::hasTournaments($playerId));

// POST処理
$success = isset($_GET['saved']) && $_GET['saved'] === '1';
$validationError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validationError = validatePost();
    if ($validationError) {
        // バリデーションエラー
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        if ($hasTournaments) {
            $validationError = '大会に参加しているため削除できません。';
        } else {
            try {
                Player::delete($playerId);
                $_SESSION['flash'] = '選手を削除しました。';
                regenerateCsrfToken();
                header('Location: players');
                exit;
            } catch (PDOException $e) {
                error_log('[DB] ' . $e->getMessage());
                $validationError = '削除に失敗しました。';
            }
        }
    } else {
        $nickname = sanitizeInput('nickname');
        $characterId = filter_input(INPUT_POST, 'character_id', FILTER_VALIDATE_INT);

        if ($nickname === '') {
            $validationError = '呼称を入力してください。';
        } elseif (mb_strlen($nickname) > 50) {
            $validationError = '呼称は50文字以内で入力してください。';
        } elseif ($characterId && !array_filter($characters ?? [], fn($c) => (int)$c['id'] === $characterId)) {
            $validationError = '不正なキャラクター選択です。';
        } else {
            try {
                Player::update($playerId, $nickname, $characterId ?: null);
                regenerateCsrfToken();
                header('Location: player_edit?id=' . $playerId . '&saved=1');
                exit;
            } catch (PDOException $e) {
                error_log('[DB] ' . $e->getMessage());
                $validationError = '保存に失敗しました。';
            }
        }
    }

    if ($validationError) {
        $player = requirePlayer($playerId);
    }
}

// --- テンプレート変数 ---
$pageTitle = h($player['name']) . ' 編集 - ' . SITE_NAME;
$pageCss = ['css/forms.css'];
$pageStyle = <<<'CSS'
.edit-form { max-width: 600px; }
.edit-label { margin-bottom: 12px; }
.edit-name-readonly { font-size: 0.8rem; color: var(--text-sub); margin-top: 6px; }
CSS;

$pageTurnstile = true;
require __DIR__ . '/../templates/header.php';
?>

<div class="edit-hero">
  <div class="edit-badge">EDIT</div>
  <div class="edit-title"><?= h($player['name']) ?></div>
  <div class="edit-subtitle">選手情報の編集</div>
</div>

<div class="edit-form">
  <?php if ($success): ?>
    <div class="edit-message success">保存しました。</div>
  <?php elseif ($validationError): ?>
    <div class="edit-message error"><?= h($validationError) ?></div>
  <?php endif; ?>

  <form method="post" action="player_edit?id=<?= $playerId ?>">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

    <div class="edit-section">
      <label class="edit-label">呼称</label>
      <input type="text" name="nickname" class="edit-input" value="<?= h($player['nickname'] ?? '') ?>" maxlength="50" required>
      <div class="edit-name-readonly">プレイヤー名: <?= h($player['name']) ?>（変更不可）</div>
    </div>

    <div class="edit-section">
      <label class="edit-label">使用キャラクター</label>
      <div class="chara-grid">
        <?php foreach ($characters ?? [] as $c): ?>
          <label class="chara-option">
            <input type="radio" name="character_id" value="<?= (int)$c['id'] ?>" <?= (int)($player['character_id'] ?? 0) === (int)$c['id'] ? 'checked' : '' ?>>
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
      <a href="player?id=<?= $playerId ?>" class="btn-cancel">&#x2190; 個人ページに戻る</a>
      <div class="cf-turnstile" data-sitekey="<?= h(turnstileSiteKey()) ?>"></div>
      <button type="submit" class="btn-save">保存</button>
    </div>

  </form>

  <div class="edit-danger-section">
    <div class="edit-danger-header">選手の削除</div>
    <?php if ($hasTournaments): ?>
      <p class="edit-danger-desc">この選手は大会に参加しているため削除できません。</p>
    <?php else: ?>
      <p class="edit-danger-desc">この選手を完全に削除します。この操作は取り消せません。</p>
      <form method="post" action="player_edit?id=<?= $playerId ?>" data-confirm="<?= h($player['name']) ?> を削除しますか？この操作は取り消せません。">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="delete">
        <div class="cf-turnstile" data-sitekey="<?= h(turnstileSiteKey()) ?>"></div>
        <button type="submit" class="btn-delete">削除する</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
