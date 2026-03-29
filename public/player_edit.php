<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';

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
    if (!validateCsrfToken()) {
        http_response_code(403);
        $validationError = '不正なリクエストです。ページを再読み込みしてください。';
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
$pageTitle = h($player['name']) . ' 編集 - 最強位戦';
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

.edit-name-readonly {
  font-size: 0.8rem;
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
.edit-message.success {
  background: linear-gradient(135deg, rgba(var(--mint-rgb),0.12), rgba(var(--mint-rgb),0.04));
  color: var(--success);
  border: 1px solid rgba(var(--mint-rgb),0.3);
  box-shadow: 0 2px 12px rgba(var(--mint-rgb),0.1);
}
.edit-message.success::before {
  content: '\2714';
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
@keyframes messageFadeOut {
  from { opacity: 1; max-height: 60px; margin-bottom: 20px; padding: 16px 24px; }
  80% { opacity: 0; max-height: 60px; margin-bottom: 20px; padding: 16px 24px; }
  to { opacity: 0; max-height: 0; margin-bottom: 0; padding: 0 24px; overflow: hidden; }
}
.edit-message.success {
  animation: messageFadeIn 0.4s ease, messageFadeOut 0.6s ease 3s forwards;
}

.edit-danger-section {
  margin-top: 32px;
  padding: 20px 24px;
  border: 1px solid rgba(var(--danger-rgb), 0.2);
  border-radius: var(--radius);
  background: rgba(var(--danger-rgb), 0.03);
}
.edit-danger-header {
  font-weight: 700;
  font-size: 0.85rem;
  color: var(--danger);
  margin-bottom: 8px;
}
.edit-danger-desc {
  font-size: 0.8rem;
  color: var(--text-sub);
  margin: 0;
  line-height: 1.5;
}
.btn-delete {
  margin-top: 12px;
  padding: 8px 20px;
  background: none;
  color: var(--danger);
  border: 1px solid rgba(var(--danger-rgb), 0.35);
  border-radius: 8px;
  font-weight: 700;
  font-size: 0.8rem;
  font-family: 'Noto Sans JP', sans-serif;
  cursor: pointer;
  transition: background 0.2s, color 0.2s;
}
.btn-delete:hover {
  background: var(--danger);
  color: #fff;
}
CSS;

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
      <button type="submit" class="btn-save">保存</button>
    </div>

  </form>

  <div class="edit-danger-section">
    <div class="edit-danger-header">選手の削除</div>
    <?php if ($hasTournaments): ?>
      <p class="edit-danger-desc">この選手は大会に参加しているため削除できません。</p>
    <?php else: ?>
      <p class="edit-danger-desc">この選手を完全に削除します。この操作は取り消せません。</p>
      <form method="post" action="player_edit?id=<?= $playerId ?>" onsubmit="return confirm('<?= h($player['name']) ?> を削除しますか？この操作は取り消せません。')">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="btn-delete">削除する</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
