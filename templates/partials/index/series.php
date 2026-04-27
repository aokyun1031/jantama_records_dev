<?php
/**
 * index.php の SERIES HANGAR セクション。
 * 親スコープから受け取る変数: $latestByEvent, $eventCounts, $stats, $activeTournaments
 *                          ヘルパー: $dividerHtml クロージャ
 */
?>
<!-- ======================== -->
<!-- SERIES HANGAR (+ 大会全体の記録 を統合) -->
<!-- ======================== -->
<?= $dividerHtml('series') ?>
<section class="lp3-band band-series lp3-reveal">
  <div class="lp3-inner">
    <h2 class="lp3-heading">大会シリーズ</h2>
  <div class="lp3-series">
    <?php
    $seriesOrder = [
        EventType::Saikyoi->value => 'S',
        EventType::Hooh->value => 'H',
        EventType::Masters->value => 'M',
        EventType::Hyakudanisen->value => '100',
        EventType::Petit->value => 'P',
    ];
    foreach ($seriesOrder as $etv => $code):
        $eventEnum = EventType::tryFrom($etv);
        if (!$eventEnum) {
            continue;
        }
        $latest = $latestByEvent[$etv] ?? null;
        $count = $eventCounts[$etv] ?? 0;
        $tsLatest = $latest ? TournamentStatus::tryFrom($latest['status']) : null;
        $viewable = $latest && $latest['status'] !== TournamentStatus::Preparing->value;
        $href = $latest ? ($viewable ? 'tournament_view?id=' . (int) $latest['id'] : 'tournament?id=' . (int) $latest['id']) : 'tournaments';
    ?>
      <a href="<?= h($href) ?>" class="lp3-card lp3-series-tile<?= $count === 0 ? ' is-empty' : '' ?>" data-code="<?= h((string) $code) ?>">
        <span class="lp3-series-count">開催 <?= (int) $count ?> 回</span>
        <div class="lp3-series-label"><?= h($eventEnum->label()) ?></div>
        <?php if ($latest): ?>
          <div class="lp3-series-latest-title">最新</div>
          <div class="lp3-series-latest-name"><?= h($latest['name']) ?></div>
          <?php if ($tsLatest && $latest['status'] !== TournamentStatus::Completed->value): ?>
            <div class="lp3-series-status"><?= h($tsLatest->label()) ?></div>
          <?php endif; ?>
        <?php else: ?>
          <div class="lp3-series-empty-note">開催予定なし</div>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="lp3-stats">
    <a href="tournaments" class="lp3-card lp3-stat">
      <div class="lp3-stat-num lp3-count" data-count="<?= (int) $stats['total_tournaments'] ?>"><?= (int) $stats['total_tournaments'] ?></div>
      <div class="lp3-stat-label">開催大会</div>
    </a>
    <a href="tournaments" class="lp3-card lp3-stat">
      <div class="lp3-stat-num lp3-count" data-count="<?= (int) $stats['completed_tournaments'] ?>"><?= (int) $stats['completed_tournaments'] ?></div>
      <div class="lp3-stat-label">終了大会</div>
    </a>
    <a href="players" class="lp3-card lp3-stat">
      <div class="lp3-stat-num lp3-count" data-count="<?= (int) $stats['total_players'] ?>"><?= (int) $stats['total_players'] ?></div>
      <div class="lp3-stat-label">登録選手</div>
    </a>
    <div class="lp3-card lp3-stat">
      <div class="lp3-stat-num lp3-count" data-count="<?= (int) $stats['done_tables'] ?>"><?= (int) $stats['done_tables'] ?></div>
      <div class="lp3-stat-label">消化卓数</div>
    </div>
    <div class="lp3-card lp3-stat">
      <div class="lp3-stat-num lp3-count" data-count="<?= (int) $stats['total_rounds'] ?>"><?= (int) $stats['total_rounds'] ?></div>
      <div class="lp3-stat-label">半荘数</div>
    </div>
    <a href="tournaments" class="lp3-card lp3-stat">
      <div class="lp3-stat-num lp3-count" data-count="<?= count($activeTournaments) ?>"><?= count($activeTournaments) ?></div>
      <div class="lp3-stat-label">開催中</div>
    </a>
  </div>
  </div>
</section>
