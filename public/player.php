<?php
require __DIR__ . '/../config/database.php';

// --- バリデーション ---
$playerId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$playerId) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// --- データ取得 ---
['data' => $player, 'error' => $playerError] = fetchData(fn() => Player::find($playerId));
if ($playerError || !$player) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

['data' => $tournaments, 'error' => $error] = fetchData(fn() => Tournament::byPlayer($playerId));

// --- テンプレート変数 ---
$pageTitle = h($player['name']) . ' - 最強位戦';
$pageStyle = <<<'CSS'
.player-hero {
  text-align: center;
  padding: 48px 20px 32px;
}

.player-badge {
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

.player-title {
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

.player-nickname {
  font-weight: 400;
  font-size: 0.6em;
  -webkit-text-fill-color: var(--text-sub);
}

.player-subtitle {
  font-size: 0.85rem;
  color: var(--text-sub);
  animation: fadeUp 1s ease 0.3s both;
}

.tournament-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 40px;
}

.tournament-card {
  background: var(--card);
  backdrop-filter: blur(12px);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-sm);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
  opacity: 0;
  transform: translateY(16px);
  animation: cardFadeIn 0.5s ease forwards;
}

/* --- カード上段: 情報エリア --- */
.tournament-body {
  padding: 28px 24px 22px;
  text-align: center;
}

.tournament-header {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-bottom: 18px;
}

.tournament-name {
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--text-sub);
}

.tournament-status {
  font-size: 0.7rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 10px;
  letter-spacing: 1px;
}

.status-completed {
  background: linear-gradient(135deg, rgba(92,200,176,0.15), rgba(92,200,176,0.05));
  color: var(--mint);
}

.status-in-progress {
  background: linear-gradient(135deg, rgba(232,140,173,0.15), rgba(232,140,173,0.05));
  color: var(--pink);
}

.tournament-progress {
  display: block;
  font-family: 'Noto Sans JP', sans-serif;
  font-weight: 900;
  font-size: 1.8rem;
  line-height: 1;
  margin-bottom: 10px;
}

.tournament-total {
  display: block;
  font-family: 'Inter', sans-serif;
  font-weight: 700;
  font-size: 1rem;
  color: var(--text-sub);
}

.progress-champion {
  font-size: 2rem;
  background: linear-gradient(135deg, #c49a3c, #e8c84c, #d4a84c, #e8c84c);
  background-size: 300% 300%;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  animation: titleGrad 4s ease infinite;
  filter: drop-shadow(0 1px 2px rgba(212,168,76,0.3));
}

.progress-finals {
  color: var(--mint);
}

.progress-eliminated {
  color: var(--text);
}

/* --- カード下段: リンクエリア --- */
.tournament-links {
  display: grid;
  grid-template-columns: 1fr 1fr;
  background: rgba(155,140,232,0.05);
}

.tournament-link {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 14px 12px;
  min-height: 44px;
  font-size: 0.85rem;
  font-weight: 700;
  text-decoration: none;
  color: var(--text-sub);
  transition: background 0.2s, color 0.2s;
}

.tournament-link:first-child {
  border-right: 1px solid rgba(155,140,232,0.1);
}

.tournament-link:hover {
  background: rgba(155,140,232,0.08);
  color: var(--purple);
}

.tournament-link .link-arrow {
  font-size: 0.7rem;
  opacity: 0.4;
  transition: transform 0.2s, opacity 0.2s;
}

.tournament-link:hover .link-arrow {
  transform: translateX(2px);
  opacity: 1;
}

.player-error {
  text-align: center;
  padding: 24px;
  background: linear-gradient(135deg, rgba(232,112,112,0.1), rgba(232,112,112,0.05));
  border: 1px solid rgba(232,112,112,0.3);
  border-radius: var(--radius);
  color: var(--text);
  font-size: 0.9rem;
  margin-bottom: 24px;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  color: #fff;
  text-decoration: none;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.85rem;
  transition: transform 0.3s, box-shadow 0.3s;
  box-shadow: 0 4px 16px rgba(155, 140, 232, 0.3);
}

.btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 24px rgba(155, 140, 232, 0.4);
}

.btn-primary {
  background: linear-gradient(135deg, var(--purple), var(--pink));
}

.btn-secondary {
  background: linear-gradient(135deg, var(--mint), var(--blue));
}

@keyframes cardFadeIn {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
CSS;

// --- 表示 ---
require __DIR__ . '/../templates/header.php';
?>

<div class="player-hero">
  <div class="player-badge">PLAYER</div>
  <h1 class="player-title"><?= h($player['name']) ?><?php if ($player['nickname']): ?><span class="player-nickname">（<?= h($player['nickname']) ?>）</span><?php endif; ?></h1>
  <div class="player-subtitle"><?= count($tournaments ?? []) ?> 大会に参加</div>
  <a href="player_analysis.php?id=<?= $playerId ?>" class="btn btn-secondary" style="margin-top: 20px;">個人戦績分析</a>
</div>

<?php if ($error): ?>
  <div class="player-error">
    データベース接続エラー。しばらくしてから再度お試しください。
  </div>
<?php elseif (empty($tournaments)): ?>
  <div class="player-error">
    参加した大会はまだありません。
  </div>
<?php else: ?>
  <div class="tournament-list">
  <?php foreach ($tournaments as $i => $t): ?>
    <div class="tournament-card" style="animation-delay: <?= $i * 0.08 ?>s">
      <div class="tournament-body">
        <div class="tournament-header">
          <span class="tournament-name"><?= h($t['name']) ?></span>
          <span class="tournament-status <?= $t['status'] === 'completed' ? 'status-completed' : 'status-in-progress' ?>">
            <?= $t['status'] === 'completed' ? '大会終了' : '開催中' ?>
          </span>
        </div>
        <?php
          $isChampion = (int)$t['rank'] === 1 && $t['status'] === 'completed';
          $elimRound  = (int)$t['eliminated_round'];
          $lastRound  = (int)$t['last_round'];
          if ($isChampion):
        ?>
          <span class="tournament-progress progress-champion">優勝</span>
        <?php elseif ($elimRound > 0): ?>
          <span class="tournament-progress progress-eliminated"><?= $elimRound ?>回戦敗退</span>
        <?php else: ?>
          <span class="tournament-progress progress-finals"><?= $lastRound ?>回戦進出</span>
        <?php endif; ?>
        <span class="tournament-total"><?= number_format((float)$t['total'], 1) ?>pt</span>
      </div>
      <div class="tournament-links">
        <a href="player_tournament.php?player_id=<?= $playerId ?>&amp;tournament_id=<?= (int)$t['id'] ?>" class="tournament-link">個人戦績 <span class="link-arrow">&#x203A;</span></a>
        <a href="index.php" class="tournament-link">全体戦績 <span class="link-arrow">&#x203A;</span></a>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>

<div style="text-align: center;">
  <a href="players.php" class="btn btn-primary" style="margin-bottom: 40px;">&#x2190; 選手一覧に戻る</a>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
