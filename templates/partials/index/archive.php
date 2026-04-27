<?php
/**
 * index.php の ARCHIVE (過去の大会) セクション。
 * 親スコープから受け取る変数: $tournaments
 *                          ヘルパー: $dividerHtml クロージャ
 */
?>
<!-- ======================== -->
<!-- ARCHIVE -->
<!-- ======================== -->
<?php if (!empty($tournaments)): ?>
<?= $dividerHtml('archive') ?>
<section class="lp3-band band-archive lp3-reveal">
  <div class="lp3-inner">
    <h2 class="lp3-heading">過去の大会</h2>
  <div class="lp3-archive-grid">
    <?php foreach (array_slice($tournaments, 0, 8) as $t):
        $tsEnum = TournamentStatus::tryFrom($t['status']);
        $etEnum = EventType::tryFrom($t['event_type'] ?? '');
        $isViewable = $t['status'] !== TournamentStatus::Preparing->value;
        $href = $isViewable ? 'tournament_view?id=' . (int) $t['id'] : 'tournament?id=' . (int) $t['id'];
        $dateStr = !empty($t['start_date']) ? date('Y.m.d', strtotime($t['start_date'])) : '';
    ?>
      <a href="<?= h($href) ?>" class="lp3-card lp3-archive-row">
        <span class="lp3-archive-date"><?= h($dateStr) ?></span>
        <span class="lp3-archive-name"><?= h($t['name']) ?></span>
        <span class="lp3-archive-event"><?= h($etEnum?->label() ?? '-') ?></span>
        <span class="lp3-archive-winner">
          <?php if ($t['status'] === TournamentStatus::Completed->value && !empty($t['winner_name'])): ?>
            &#x1F451; <strong><?= h($t['winner_name']) ?></strong>
          <?php else: ?>
            <?= (int) $t['player_count'] ?>名参加
          <?php endif; ?>
        </span>
        <span class="lp3-archive-status <?= $tsEnum?->cssClass() ?? '' ?>"><?= h($tsEnum?->label() ?? '') ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if (count($tournaments) > 8): ?>
    <div style="text-align:right;margin-top:14px;">
      <a href="tournaments" class="lp3-btn lp3-btn-ghost lp3-btn-sm lp3-btn-arrow">すべての大会</a>
    </div>
  <?php endif; ?>
  </div>
</section>
<?php endif; ?>
