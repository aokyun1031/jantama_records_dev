<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';

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
$pageTitle = '選手登録 - 最強位戦';
$pageStyle = <<<'CSS'
.edit-hero {
  text-align: center;
  padding: 48px 20px 32px;
}

.edit-badge {
  display: inline-block;
  background: var(--badge-bg);
  color: var(--badge-color);
  font-size: 0.7rem;
  font-weight: 700;
  padding: 4px 14px;
  border-radius: 20px;
  margin-bottom: 20px;
  letter-spacing: 2px;
  box-shadow: 0 2px 12px rgba(var(--accent-rgb),0.3);
}

.edit-title {
  font-family: 'Noto Sans JP', sans-serif;
  font-size: clamp(1.4rem, 5vw, 2rem);
  font-weight: 900;
  color: var(--text);
  margin-bottom: 4px;
}

.edit-subtitle {
  font-size: 0.85rem;
  color: var(--text-sub);
}

.edit-form {
  max-width: 600px;
  margin: 0 auto 40px;
  padding: 0 16px;
}

.edit-section {
  background: var(--card);
  border: 1px solid rgba(var(--accent-rgb), 0.25);
  border-radius: var(--radius);
  padding: 24px;
  margin-bottom: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.edit-label {
  font-weight: 700;
  font-size: 0.85rem;
  color: var(--text);
  margin-bottom: 12px;
  display: block;
}

.edit-input {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-sm);
  font-size: 1rem;
  font-family: 'Noto Sans JP', sans-serif;
  background: var(--card);
  color: var(--text);
  box-sizing: border-box;
}
.edit-input:focus {
  outline: none;
  border-color: var(--purple);
  box-shadow: 0 0 0 3px rgba(var(--accent-rgb),0.15);
}

.edit-hint {
  font-size: 0.75rem;
  color: var(--text-sub);
  margin-top: 6px;
}

.chara-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(72px, 1fr));
  gap: 10px;
}

.chara-option {
  position: relative;
  cursor: pointer;
}
.chara-option input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.chara-option-inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 8px 4px;
  border: 2px solid transparent;
  border-radius: var(--radius-sm);
  transition: border-color 0.2s, box-shadow 0.2s;
}
.chara-option input:checked + .chara-option-inner {
  border-color: var(--purple);
  box-shadow: 0 0 0 3px rgba(var(--accent-rgb),0.2);
  background: rgba(var(--accent-rgb),0.06);
}
.chara-option-inner:hover {
  border-color: rgba(var(--accent-rgb),0.4);
}
.chara-option-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  object-fit: cover;
}
.chara-option-noicon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: var(--glass-border);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.5rem;
  color: var(--text-sub);
}
.chara-option-name {
  font-size: 0.6rem;
  color: var(--text-sub);
  text-align: center;
  line-height: 1.2;
  word-break: break-all;
}

.edit-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
  margin-top: 24px;
}

.btn-save {
  display: inline-block;
  padding: 12px 32px;
  background: var(--btn-primary-bg);
  color: var(--btn-text-color);
  border: none;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.9rem;
  font-family: 'Noto Sans JP', sans-serif;
  cursor: pointer;
  transition: transform 0.3s, box-shadow 0.3s;
  box-shadow: 0 4px 16px rgba(var(--accent-rgb), 0.3);
}
.btn-save:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 24px rgba(var(--accent-rgb), 0.4);
}

.btn-cancel {
  display: inline-flex;
  align-items: center;
  padding: 12px 24px;
  background: var(--card);
  color: var(--text-sub);
  border: 1px solid var(--glass-border);
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.9rem;
  font-family: 'Noto Sans JP', sans-serif;
  text-decoration: none;
  transition: transform 0.3s;
}
.btn-cancel:hover {
  transform: translateY(-2px);
}

.edit-message {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 16px 24px;
  border-radius: var(--radius);
  margin-bottom: 20px;
  font-size: 0.9rem;
  font-weight: 700;
  animation: messageFadeIn 0.4s ease both;
}
.edit-message::before {
  font-size: 1.2rem;
  flex-shrink: 0;
}
.edit-message.error {
  background: linear-gradient(135deg, rgba(var(--coral-rgb),0.12), rgba(var(--coral-rgb),0.04));
  color: var(--danger);
  border: 1px solid rgba(var(--coral-rgb),0.3);
  box-shadow: 0 2px 12px rgba(var(--coral-rgb),0.1);
}
.edit-message.error::before {
  content: '\26A0';
}
@keyframes messageFadeIn {
  from { opacity: 0; transform: translateY(-8px); }
  to { opacity: 1; transform: translateY(0); }
}
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
      <div class="edit-hint">雀魂アプリ内の正式名称。登録後の変更はできません。</div>
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
