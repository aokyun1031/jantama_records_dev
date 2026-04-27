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
$pageTitle = $player['name'] . ' - ' . SITE_NAME;
$pageDescription = $player['name'] . ' の大会戦績ページです。';
$pageCss = ['css/forms.css', 'css/player.css'];

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
  <a href="player_analysis?id=<?= $playerId ?>" class="player-btn player-btn-secondary" style="margin-top: 20px;">個人戦績分析</a>
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
