<?php
require __DIR__ . '/../config/database.php';

// --- データ取得 ---
['data' => $players, 'error' => $error] = fetchData(function (PDO $pdo) {
    return $pdo->query('SELECT id, name FROM players ORDER BY id')->fetchAll();
});

// --- テンプレート変数 ---
$pageTitle = '選手一覧 - 最強位戦';
$pageStyle = <<<'CSS'
.players-hero {
  text-align: center;
  padding: 48px 20px 32px;
}

.players-badge {
  display: inline-block;
  background: linear-gradient(135deg, var(--lavender), var(--pink));
  color: #fff;
  font-size: 0.7rem;
  font-weight: 700;
  padding: 4px 14px;
  border-radius: 20px;
  margin-bottom: 16px;
  letter-spacing: 2px;
  box-shadow: 0 2px 12px rgba(184,160,232,0.3);
  animation: fadeDown 0.8s ease both;
}

.players-title {
  font-family: 'Noto Sans JP', sans-serif;
  font-size: clamp(1.8rem, 6vw, 2.5rem);
  font-weight: 900;
  background: linear-gradient(135deg, #9b8ce8, #e88cad, #d4a84c, #5cc8b0);
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
  background: var(--card);
  backdrop-filter: blur(12px);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-sm);
  padding: 16px 20px;
  box-shadow: var(--shadow-sm);
  display: flex;
  align-items: center;
  gap: 12px;
  transition: transform 0.3s, box-shadow 0.3s;
  opacity: 0;
  transform: translateY(16px);
  animation: playerFadeIn 0.5s ease forwards;
}

.player-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow);
}

.player-id {
  font-family: 'Inter', sans-serif;
  font-weight: 800;
  font-size: 0.85rem;
  color: var(--text-light);
  min-width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, rgba(155,140,232,0.15), rgba(155,140,232,0.05));
  border-radius: 50%;
  flex-shrink: 0;
}

.player-name {
  font-weight: 700;
  font-size: 1rem;
  color: var(--text);
}

.players-back {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  background: linear-gradient(135deg, var(--purple), var(--pink));
  color: #fff;
  text-decoration: none;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.85rem;
  transition: transform 0.3s, box-shadow 0.3s;
  box-shadow: 0 4px 16px rgba(155, 140, 232, 0.3);
  margin-bottom: 40px;
}

.players-back:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 24px rgba(155, 140, 232, 0.4);
}

.players-error {
  text-align: center;
  padding: 24px;
  background: linear-gradient(135deg, rgba(232,112,112,0.1), rgba(232,112,112,0.05));
  border: 1px solid rgba(232,112,112,0.3);
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
CSS;

// --- 表示 ---
require __DIR__ . '/../templates/header.php';
?>

<div class="players-hero">
  <div class="players-badge">PLAYERS</div>
  <h1 class="players-title">選手一覧</h1>
  <div class="players-count"><?= count($players ?? []) ?> 名の選手が登録されています</div>
</div>

<?php if ($error): ?>
  <div class="players-error">
    <div class="players-error-label">データベース接続エラー</div>
    <div class="players-error-detail">一時的にデータを取得できません。しばらくしてから再度お試しください。</div>
  </div>
<?php else: ?>
  <div class="players-grid">
  <?php foreach ($players as $i => $player): ?>
    <div class="player-card" style="animation-delay: <?= $i * 0.05 ?>s">
      <div class="player-id"><?= (int)$player['id'] ?></div>
      <div class="player-name"><?= h($player['name']) ?></div>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>

<div style="text-align: center;">
  <a href="index.html" class="players-back">&#x2190; トップページに戻る</a>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
