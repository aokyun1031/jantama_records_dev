<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';

startSecureSession();

// --- フラッシュメッセージ ---
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// --- データ取得 ---
['data' => $players, 'error' => $error] = fetchData(fn() => Player::all());

// --- テンプレート変数 ---
$pageTitle = '選手一覧 - 最強位戦';
$pageDescription = '最強位戦に参加する選手の一覧です。';
$pageStyle = <<<'CSS'
.players-hero {
  text-align: center;
  padding: 48px 20px 32px;
}

.players-badge {
  display: inline-block;
  background: var(--badge-bg);
  color: var(--badge-color);
  font-size: 0.7rem;
  font-weight: 700;
  padding: 4px 14px;
  border-radius: 20px;
  margin-bottom: 16px;
  letter-spacing: 2px;
  box-shadow: 0 2px 12px rgba(var(--accent-rgb),0.3);
  animation: fadeDown 0.8s ease both;
}

.players-title {
  font-family: 'Noto Sans JP', sans-serif;
  font-size: clamp(1.8rem, 6vw, 2.5rem);
  font-weight: 900;
  background: var(--title-gradient);
  background-size: 300% 300%;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  animation: titleGrad 6s ease infinite, fadeUp 1s ease both;
  margin-bottom: 8px;
}

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

.players-back {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  background: var(--btn-primary-bg);
  color: var(--btn-text-color);
  text-decoration: none;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.85rem;
  transition: transform 0.3s, box-shadow 0.3s;
  box-shadow: 0 4px 16px rgba(var(--accent-rgb), 0.3);
  margin-bottom: 40px;
}

.players-back:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 24px rgba(var(--accent-rgb), 0.4);
}

.players-new {
  background: var(--btn-secondary-bg);
  box-shadow: 0 4px 16px rgba(var(--mint-rgb), 0.3);
}
.players-new:hover {
  box-shadow: 0 6px 24px rgba(var(--mint-rgb), 0.4);
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

.players-message {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  max-width: 600px;
  margin: 0 auto 20px;
  padding: 16px 24px;
  border-radius: var(--radius);
  font-size: 0.9rem;
  font-weight: 700;
  background: linear-gradient(135deg, rgba(var(--mint-rgb),0.12), rgba(var(--mint-rgb),0.04));
  color: var(--success);
  border: 1px solid rgba(var(--mint-rgb),0.3);
  box-shadow: 0 2px 12px rgba(var(--mint-rgb),0.1);
  animation: msgIn 0.4s ease, msgOut 0.6s ease 3s forwards;
}
.players-message::before {
  content: '\2714';
  font-size: 1.2rem;
  flex-shrink: 0;
}
@keyframes msgIn {
  from { opacity: 0; transform: translateY(-8px); }
  to { opacity: 1; transform: translateY(0); }
}
@keyframes msgOut {
  from { opacity: 1; max-height: 60px; margin-bottom: 20px; padding: 16px 24px; }
  80% { opacity: 0; }
  to { opacity: 0; max-height: 0; margin-bottom: 0; padding: 0 24px; overflow: hidden; }
}
CSS;

// --- 表示 ---
require __DIR__ . '/../templates/header.php';
?>

<div class="players-hero">
  <div class="players-badge">PLAYERS</div>
  <h1 class="players-title">選手一覧</h1>
  <div class="players-count"><?= count($players ?? []) ?> 名の選手が登録されています</div>
</div>

<?php if ($flash): ?>
  <div class="players-message"><?= h($flash) ?></div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="players-error">
    <div class="players-error-label">データベース接続エラー</div>
    <div class="players-error-detail">一時的にデータを取得できません。しばらくしてから再度お試しください。</div>
  </div>
<?php else: ?>
  <div class="players-grid">
  <?php foreach ($players as $i => $player): ?>
    <a href="player?id=<?= (int)$player['id'] ?>" class="player-card" style="animation-delay: <?= $i * 0.05 ?>s">
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
  <a href="/" class="players-back">&#x2190; トップページに戻る</a>
  <a href="player_new" class="players-back players-new">+ 選手を追加</a>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
