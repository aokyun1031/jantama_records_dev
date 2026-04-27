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
$flash = consumeFlash();
$success = isset($_GET['saved']) && $_GET['saved'] === '1';
$validationError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validationError = validatePost();
    if ($validationError) {
        // バリデーションエラー
    } elseif (sanitizeInput('action') === 'delete') {
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
    } elseif (sanitizeInput('action') === 'unlink_discord') {
        try {
            Player::updateDiscord($playerId, null, null);
            $_SESSION['flash'] = 'Discord 連携を解除しました。';
            regenerateCsrfToken();
            header('Location: player_edit?id=' . $playerId);
            exit;
        } catch (PDOException $e) {
            error_log('[DB] ' . $e->getMessage());
            $validationError = 'Discord 連携の解除に失敗しました。';
        }
    } else {
        $nickname = sanitizeInput('nickname');
        $characterId = filter_input(INPUT_POST, 'character_id', FILTER_VALIDATE_INT);

        if ($nickname === '') {
            $validationError = '呼称を入力してください。';
        } elseif (mb_strlen($nickname) > 50) {
            $validationError = '呼称は50文字以内で入力してください。';
        } elseif ($characterId && !array_filter($characters ?? [], fn($c) => (int) $c['id'] === $characterId)) {
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
$pageDescription = $player['name'] . ' の選手情報を編集します。';
$pageCss = ['css/forms.css'];
$pageStyle = <<<'CSS'
.edit-form { max-width: 600px; }
.edit-label { margin-bottom: 12px; }
.edit-name-readonly { font-size: 0.8rem; color: var(--text-sub); margin-top: 6px; }
.discord-section { background: linear-gradient(135deg, rgba(var(--discord-blurple-rgb),0.04), rgba(var(--discord-blurple-rgb),0.01)); margin-top: 32px; }
.discord-card { display: flex; align-items: center; gap: 14px; padding: 18px 20px; background: linear-gradient(135deg, var(--discord-blurple) 0%, var(--discord-blurple-alt) 100%); border-radius: 14px; box-shadow: 0 6px 24px rgba(var(--discord-blurple-rgb),0.25); position: relative; overflow: hidden; }
.discord-card::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 90% 0%, rgba(255,255,255,0.12), transparent 60%); pointer-events: none; }
.discord-card-logo { width: 36px; height: 36px; color: var(--white); flex-shrink: 0; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2)); position: relative; }
.discord-card-body { flex: 1; min-width: 0; position: relative; }
.discord-card-status { display: inline-flex; align-items: center; gap: 6px; font-size: 0.7rem; font-weight: 800; letter-spacing: 1px; color: rgba(255,255,255,0.95); text-transform: uppercase; margin-bottom: 4px; }
.discord-status-dot { display: inline-block; width: 8px; height: 8px; background: var(--discord-online); border-radius: 50%; box-shadow: 0 0 0 2px rgba(var(--discord-online-rgb),0.3), 0 0 12px rgba(var(--discord-online-rgb),0.6); animation: discordPulse 2s ease-in-out infinite; }
@keyframes discordPulse { 0%,100% { opacity: 1 } 50% { opacity: 0.6 } }
.discord-card-username { font-size: 1.1rem; font-weight: 800; color: var(--white); line-height: 1.2; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-shadow: 0 1px 2px rgba(0,0,0,0.15); }
.discord-card-username-id { font-size: 0.9rem; opacity: 0.8; font-family: monospace; }
.discord-card-action { margin: 0; flex-shrink: 0; position: relative; }
.btn-discord-unlink { padding: 6px 14px; background: rgba(255,255,255,0.18); color: var(--white); border: 1px solid rgba(255,255,255,0.3); border-radius: 8px; font-size: 0.75rem; font-weight: 700; font-family: 'Noto Sans JP', sans-serif; cursor: pointer; transition: background 0.2s, border-color 0.2s; backdrop-filter: blur(4px); }
.btn-discord-unlink:hover { background: rgba(255,255,255,0.28); border-color: rgba(255,255,255,0.5); }
.btn-discord-link { display: inline-flex; align-items: center; gap: 10px; padding: 12px 24px; background: var(--discord-blurple); color: var(--white); border-radius: 10px; font-weight: 700; font-size: 0.9rem; text-decoration: none; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 3px 12px rgba(var(--discord-blurple-rgb),0.3); }
.btn-discord-link:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(var(--discord-blurple-rgb),0.4); }
.btn-discord-icon { width: 20px; height: 20px; }
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
  <?php elseif ($flash): ?>
    <div class="edit-message success"><?= h($flash) ?></div>
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
            <input type="radio" name="character_id" value="<?= (int) $c['id'] ?>" <?= (int) ($player['character_id'] ?? 0) === (int) $c['id'] ? 'checked' : '' ?>>
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

  <!-- Discord 連携（form 外。連携=外部遷移GET、解除=独立POST） -->
  <div class="edit-section discord-section">
    <label class="edit-label">Discord 連携</label>
    <?php if (!empty($player['discord_user_id'])): ?>
      <div class="discord-card">
        <svg class="discord-card-logo" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.65 12.65 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128c.126-.094.252-.192.372-.291a.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.099.246.197.373.291a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.331c-1.182 0-2.157-1.085-2.157-2.418 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.974 0c-1.183 0-2.157-1.085-2.157-2.418 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
        <div class="discord-card-body">
          <div class="discord-card-status">
            <span class="discord-status-dot"></span>連携済み
          </div>
          <?php if (!empty($player['discord_username'])): ?>
            <div class="discord-card-username">@<?= h($player['discord_username']) ?></div>
          <?php else: ?>
            <div class="discord-card-username discord-card-username-id">ID: <?= h(substr($player['discord_user_id'], 0, 8)) ?>…</div>
          <?php endif; ?>
        </div>
        <form method="post" action="player_edit?id=<?= $playerId ?>" data-confirm="Discord 連携を解除しますか？大会DM配信が届かなくなります。" class="discord-card-action">
          <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
          <input type="hidden" name="action" value="unlink_discord">
          <button type="submit" class="btn-discord-unlink">解除</button>
        </form>
      </div>
    <?php else: ?>
      <div class="edit-hint" style="margin-bottom: 14px;">大会開催時にDM通知が届きます。要求権限はユーザー名取得のみ。</div>
      <a href="discord_oauth_redirect?id=<?= $playerId ?>" class="btn-discord-link">
        <svg class="btn-discord-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.65 12.65 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128c.126-.094.252-.192.372-.291a.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.099.246.197.373.291a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.331c-1.182 0-2.157-1.085-2.157-2.418 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.974 0c-1.183 0-2.157-1.085-2.157-2.418 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
        Discord と連携
      </a>
    <?php endif; ?>
  </div>

  <div class="edit-danger-section">
    <div class="edit-danger-header">選手の削除</div>
    <?php if ($hasTournaments): ?>
      <p class="edit-danger-desc">この選手は大会に参加しているため削除できません。</p>
    <?php else: ?>
      <p class="edit-danger-desc">この選手を完全に削除します。この操作は取り消せません。</p>
      <form method="post" action="player_edit?id=<?= $playerId ?>" data-confirm="<?= h($player['name']) ?> を削除しますか？この操作は取り消せません。">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="delete">
    
        <button type="submit" class="btn-delete">削除する</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
