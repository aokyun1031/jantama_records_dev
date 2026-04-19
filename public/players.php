<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();

// --- フラッシュメッセージ ---
$flash = consumeFlash();

// --- データ取得 ---
['data' => $players, 'error' => $error] = fetchData(fn() => Player::all());

// --- テンプレート変数 ---
$pageTitle = '選手一覧 - ' . SITE_NAME;
$pageDescription = '麻雀トーナメントに参加する選手の一覧です。';
$pageCss = ['css/forms.css'];
$pageStyle = <<<'CSS'
.players-count {
  font-size: 0.85rem;
  color: var(--text-sub);
  animation: fadeUp 1s ease 0.3s both;
}

.players-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
  margin-bottom: 40px;
}

.player-card {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
  color: inherit;
  background: var(--card);
  backdrop-filter: blur(12px);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-sm);
  padding: 12px 16px;
  box-shadow: var(--shadow-sm);
  transition: transform 0.3s, box-shadow 0.3s;
  opacity: 0;
  transform: translateY(16px);
  animation: playerFadeIn 0.5s ease forwards;
}

.player-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow);
}

.player-icon {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
  background: var(--glass-border);
}

.player-icon-placeholder {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  flex-shrink: 0;
  background: var(--glass-border);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.6rem;
  color: var(--text-sub);
}

.player-info {
  min-width: 0;
}

.player-name {
  font-weight: 700;
  font-size: 1rem;
  color: var(--text);
}

.player-nickname {
  font-size: 0.75rem;
  color: var(--text-sub);
  margin-top: 2px;
}

.players-error {
  text-align: center;
  padding: 24px;
  background: linear-gradient(135deg, rgba(var(--coral-rgb),0.1), rgba(var(--coral-rgb),0.05));
  border: 1px solid rgba(var(--coral-rgb),0.3);
  border-radius: var(--radius);
  color: var(--text);
  font-size: 0.9rem;
  margin-bottom: 24px;
}

.players-error-label {
  font-weight: 700;
  color: var(--coral);
  margin-bottom: 8px;
}

.players-error-detail {
  font-size: 0.75rem;
  color: var(--text-sub);
  word-break: break-all;
}

@keyframes playerFadeIn {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (max-width: 480px) {
  .players-grid {
    grid-template-columns: 1fr;
  }
}

.edit-message { max-width: 600px; margin-left: auto; margin-right: auto; }
CSS;

// --- 表示 ---
require __DIR__ . '/../templates/header.php';
?>

<div class="page-hero">
  <div class="page-hero-badge">PLAYERS</div>
  <h1 class="page-hero-title">選手一覧</h1>
  <div class="players-count"><?= count($players ?? []) ?> 名の選手が登録されています</div>
</div>

<?php if ($flash): ?>
  <div class="edit-message success"><?= h($flash) ?></div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="players-error">
    <div class="players-error-label">データベース接続エラー</div>
    <div class="players-error-detail">一時的にデータを取得できません。しばらくしてから再度お試しください。</div>
  </div>
<?php else: ?>
  <div class="players-grid">
  <?php foreach ($players as $i => $player): ?>
    <a href="player?id=<?= (int) $player['id'] ?>" class="player-card" style="animation-delay: <?= $i * 0.05 ?>s">
      <?php if ($player['character_icon']): ?>
        <img src="img/chara_deformed/<?= h($player['character_icon']) ?>" alt="<?= h($player['name']) ?>" class="player-icon" width="44" height="44" loading="lazy">
      <?php else: ?>
        <div class="player-icon-placeholder">NO<br>IMAGE</div>
      <?php endif; ?>
      <div class="player-info">
        <div class="player-name"><?= h($player['name']) ?></div>
        <?php if ($player['nickname'] !== null && $player['nickname'] !== ''): ?><div class="player-nickname"><?= h($player['nickname']) ?></div><?php endif; ?>
      </div>
    </a>
  <?php endforeach; ?>
  </div>
<?php endif; ?>

<div style="text-align: center; display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
  <a href="/" class="btn-cancel">&#x2190; トップページに戻る</a>
  <a href="player_new" class="btn-cancel" style="background:var(--btn-secondary-bg);color:var(--btn-text-color);border-color:transparent;box-shadow:0 4px 16px rgba(var(--mint-rgb),0.3);">+ 選手を追加</a>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
