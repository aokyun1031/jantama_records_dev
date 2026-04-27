<?php
/**
 * index.php の LIVE セクション。
 * 親スコープから受け取る変数: $liveCards, $preparingTournaments, $latestCompleted
 */
?>
<!-- ======================== -->
<!-- LIVE -->
<!-- ======================== -->
<section class="lp3-band band-live lp3-reveal">
  <div class="lp3-inner">
    <h2 class="lp3-heading">開催中の大会</h2>
  <?php if (!empty($liveCards)): ?>
    <div class="lp3-live-scroller">
      <?php foreach ($liveCards as $lc):
          $lt = $lc['tournament'];
          $lEvent = EventType::tryFrom($lt['event_type'] ?? '');
      ?>
        <a href="tournament_view?id=<?= (int) $lt['id'] ?>" class="lp3-card lp3-live-card">
          <span class="lp3-live-pulse">LIVE</span>
          <div class="lp3-live-name"><?= h($lt['name']) ?></div>
          <div class="lp3-live-chips">
            <?php if ($lEvent): ?>
              <span class="lp3-chip is-pink"><?= h($lEvent->label()) ?></span>
            <?php endif; ?>
            <span class="lp3-chip is-mint"><?= (int) $lt['player_count'] ?>名参加</span>
          </div>
          <?php if ($lc['current_round']): ?>
            <div class="lp3-live-progress">
              <div>
                <div class="lp3-live-progress-sub">進行中</div>
                <div class="lp3-live-progress-round">第<?= (int) $lc['current_round'] ?>回戦</div>
              </div>
              <div class="lp3-live-alive">
                勝ち残り <strong><?= (int) $lc['alive_count'] ?></strong> 名
              </div>
            </div>
          <?php endif; ?>
          <?php if (!empty($lc['top3'])): ?>
            <div class="lp3-live-top">
              <?php foreach ($lc['top3'] as $i => $s): ?>
                <div class="lp3-live-top-row">
                  <span class="lp3-live-top-rank">#<?= $i + 1 ?></span>
                  <?php if (!empty($s['character_icon'])): ?>
                    <img src="img/chara_deformed/<?= h($s['character_icon']) ?>" alt="" class="lp3-live-top-icon" width="32" height="32" loading="lazy">
                  <?php else: ?>
                    <span class="lp3-avatar-ph" style="width:32px;height:32px;font-size:0.5rem;">NO<br>IMG</span>
                  <?php endif; ?>
                  <span class="lp3-live-top-name"><?= h($s['nickname'] ?? $s['name']) ?></span>
                  <span class="lp3-live-top-pt"><?= ((float) $s['total'] >= 0 ? '+' : '') . number_format((float) $s['total'], 1) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php elseif (!empty($preparingTournaments)):
    $pt = $preparingTournaments[0];
    $pEvent = EventType::tryFrom($pt['event_type'] ?? '');
  ?>
    <div class="lp3-card lp3-live-empty">
      <strong>&#x2728; 次回大会 準備中</strong>
      <?php if ($pEvent): ?>
        <span class="lp3-chip is-pink" style="margin-right:6px;"><?= h($pEvent->label()) ?></span>
      <?php endif; ?>
      <a href="tournament?id=<?= (int) $pt['id'] ?>" class="lp3-btn lp3-btn-ghost lp3-btn-sm lp3-btn-arrow" style="margin-top:14px;"><?= h($pt['name']) ?></a>
    </div>
  <?php else: ?>
    <div class="lp3-card lp3-live-empty">
      <strong>現在開催中の大会はありません</strong>
      <?php if ($latestCompleted): ?>
        <div class="lp3-live-empty-sub">直近の終了大会はこちら</div>
        <a href="tournament_view?id=<?= (int) $latestCompleted['id'] ?>" class="lp3-btn lp3-btn-primary lp3-btn-sm lp3-btn-arrow" style="margin-top:10px;"><?= h($latestCompleted['name']) ?></a>
      <?php else: ?>
        <a href="tournaments" class="lp3-btn lp3-btn-ghost lp3-btn-sm lp3-btn-arrow" style="margin-top:14px;">大会一覧へ</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  </div>
</section>
