<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

startSecureSession();
$flash = consumeFlash();

// --- バリデーション ---
$playerId = requirePlayerId();
$player = requirePlayer($playerId);

['data' => $tournaments, 'error' => $error] = fetchData(fn() => Tournament::byPlayer($playerId));

// --- テンプレート変数 ---
$pageTitle = h($player['name']) . ' - ' . SITE_NAME;
$pageDescription = h($player['name']) . ' の大会戦績ページです。';
$pageCss = ['css/forms.css'];
$pageStyle = <<<'CSS'
.player-hero {
  text-align: center;
  padding: 48px 20px 32px;
}

.player-badge {
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
  animation: fadeDown 0.8s ease both;
}

.player-identity {
  margin-bottom: 12px;
  animation: fadeUp 1s ease both;
}

.player-hero-icon {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  object-fit: cover;
  box-shadow: 0 4px 20px rgba(0,0,0,0.15);
  margin-bottom: 16px;
}

.player-name-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.player-edit-link {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: rgba(var(--accent-rgb), 0.1);
  border: 1px solid rgba(var(--accent-rgb), 0.2);
  color: var(--text-sub);
  text-decoration: none;
  transition: background 0.2s, border-color 0.2s, transform 0.2s;
  flex-shrink: 0;
}
.player-edit-link:hover {
  background: rgba(var(--accent-rgb), 0.2);
  border-color: rgba(var(--accent-rgb), 0.4);
  transform: scale(1.1);
}
.player-edit-icon {
  width: 14px;
  height: 14px;
}

.player-title {
  font-family: 'Noto Sans JP', sans-serif;
  font-size: clamp(1.8rem, 6vw, 2.5rem);
  font-weight: 900;
  background: var(--title-gradient);
  background-size: 300% 300%;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  animation: titleGrad 6s ease infinite;
  margin-bottom: 4px;
}

.player-nickname {
  font-size: 0.85rem;
  color: var(--text-sub);
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
.tournament-event-type {
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--text-sub);
  margin-bottom: 14px;
}

.tournament-status {
  display: inline-block;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 4px;
  white-space: nowrap;
  flex-shrink: 0;
  letter-spacing: 0.5px;
}
.tournament-status.preparing { background: rgba(var(--gold-rgb), 0.15); color: var(--gold); }
.tournament-status.active { background: rgba(var(--mint-rgb), 0.15); color: var(--success); }
.tournament-status.completed { background: rgba(var(--accent-rgb), 0.1); color: var(--text-sub); }

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
  background: var(--champion-progress-gradient);
  background-size: 300% 300%;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  animation: titleGrad 4s ease infinite;
  filter: drop-shadow(0 1px 2px rgba(var(--gold-rgb),0.3));
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
  background: rgba(var(--accent-rgb),0.05);
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
  border-right: 1px solid rgba(var(--accent-rgb),0.1);
}

.tournament-link:hover {
  background: rgba(var(--accent-rgb),0.08);
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

.tournament-link.disabled {
  color: var(--text-light);
  opacity: 0.5;
  cursor: not-allowed;
  pointer-events: none;
}

.player-error {
  text-align: center;
  padding: 24px;
  background: linear-gradient(135deg, rgba(var(--coral-rgb),0.1), rgba(var(--coral-rgb),0.05));
  border: 1px solid rgba(var(--coral-rgb),0.3);
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
  color: var(--btn-text-color);
  text-decoration: none;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.85rem;
  transition: transform 0.3s, box-shadow 0.3s;
  box-shadow: 0 4px 16px rgba(var(--accent-rgb), 0.3);
}

.btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 24px rgba(var(--accent-rgb), 0.4);
}

.btn-primary {
  background: var(--btn-primary-bg);
}

.btn-secondary {
  background: var(--btn-secondary-bg);
}

@keyframes cardFadeIn {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.edit-message { max-width: 600px; margin-left: auto; margin-right: auto; }
CSS;

// --- 表示 ---
require __DIR__ . '/../templates/header.php';
?>

<div class="player-hero">
  <div class="player-badge">PLAYER</div>
  <div class="player-identity">
    <?php if ($player['character_icon']): ?>
      <img src="img/chara_deformed/<?= h($player['character_icon']) ?>" alt="<?= h($player['name']) ?>" class="player-hero-icon" width="88" height="88">
    <?php endif; ?>
    <div class="player-name-row">
      <h1 class="player-title"><?= h($player['name']) ?></h1>
      <a href="player_edit?id=<?= $playerId ?>" class="player-edit-link" title="プロフィール編集">
        <svg class="player-edit-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
      </a>
    </div>
    <?php if ($player['nickname'] !== null && $player['nickname'] !== ''): ?><div class="player-nickname"><?= h($player['nickname']) ?></div><?php endif; ?>
  </div>
  <a href="player_analysis?id=<?= $playerId ?>" class="btn btn-secondary" style="margin-top: 20px;">個人戦績分析</a>
</div>

<?php if ($flash): ?>
  <div class="edit-message success"><?= h($flash) ?></div>
<?php endif; ?>

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
          <?php $tsEnum = TournamentStatus::tryFrom($t['status']); ?>
          <span class="tournament-status <?= $tsEnum?->cssClass() ?? '' ?>">
            <?= h($tsEnum?->label() ?? $t['status']) ?>
          </span>
        </div>
        <?php $eventType = EventType::tryFrom($t['event_type'] ?? ''); ?>
        <?php if ($eventType): ?>
          <div class="tournament-event-type"><?= h($eventType->label()) ?></div>
        <?php endif; ?>
        <?php
          $elimRound  = (int) $t['eliminated_round'];
          $lastRound  = (int) $t['last_round'];
          $isChampion = ($t['is_champion'] === true || $t['is_champion'] === 't' || $t['is_champion'] === '1')
                        && $t['status'] === TournamentStatus::Completed->value;
          if ($isChampion):
        ?>
          <span class="tournament-progress progress-champion">優勝</span>
        <?php elseif ($elimRound > 0): ?>
          <span class="tournament-progress progress-eliminated"><?= $elimRound ?>回戦敗退</span>
        <?php elseif ($t['last_round'] === null): ?>
          <span class="tournament-progress progress-finals">参加予定</span>
        <?php elseif ($t['max_round'] !== null && $lastRound === (int) $t['max_round'] && $t['status'] === TournamentStatus::Completed->value): ?>
          <span class="tournament-progress progress-eliminated">決勝敗退</span>
        <?php elseif ($t['max_round'] !== null && $lastRound === (int) $t['max_round']): ?>
          <span class="tournament-progress progress-finals">決勝進出</span>
        <?php else: ?>
          <span class="tournament-progress progress-finals"><?= $lastRound ?>回戦進出</span>
        <?php endif; ?>
        <span class="tournament-total"><?= number_format((float) $t['total'], 1) ?>pt</span>
      </div>
      <div class="tournament-links">
        <a href="player_tournament?player_id=<?= $playerId ?>&amp;tournament_id=<?= (int) $t['id'] ?>" class="tournament-link">個人戦績 <span class="link-arrow">&#x203A;</span></a>
        <?php if ($t['status'] === TournamentStatus::Preparing->value): ?>
          <span class="tournament-link disabled">大会閲覧ページ</span>
        <?php else: ?>
          <a href="tournament_view?id=<?= (int) $t['id'] ?>" class="tournament-link">大会閲覧ページ <span class="link-arrow">&#x203A;</span></a>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>

<div style="text-align: center;">
  <?php if (filter_input(INPUT_GET, 'from') === 'tournament_view' && filter_input(INPUT_GET, 'tournament_id', FILTER_VALIDATE_INT)): ?>
    <a href="tournament_view?id=<?= (int) filter_input(INPUT_GET, 'tournament_id', FILTER_VALIDATE_INT) ?>" class="btn-cancel" style="margin-bottom: 40px;">&#x2190; 大会閲覧ページに戻る</a>
  <?php else: ?>
    <a href="players" class="btn-cancel" style="margin-bottom: 40px;">&#x2190; 選手一覧に戻る</a>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
