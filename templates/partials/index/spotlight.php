<?php
/**
 * index.php の SPOTLIGHT (優勝インタビュー) セクション。
 * 親スコープから受け取る変数: $latestInterviews
 *                          ヘルパー: $dividerHtml クロージャ
 */
?>
<!-- ======================== -->
<!-- SPOTLIGHT (Interview) -->
<!-- ======================== -->
<?php if (!empty($latestInterviews)): ?>
<?= $dividerHtml('spotlight') ?>
<section class="lp3-band band-spotlight lp3-reveal">
  <div class="lp3-inner">
    <h2 class="lp3-heading">優勝インタビュー</h2>
  <div class="lp3-spotlight-list">
    <?php foreach ($latestInterviews as $iv):
        $spEvent = EventType::tryFrom($iv['event_type'] ?? '');
    ?>
      <div class="lp3-card lp3-spotlight">
        <div class="lp3-spotlight-grid">
          <?php if (!empty($iv['winner_icon'])): ?>
            <img src="img/chara_deformed/<?= h($iv['winner_icon']) ?>" alt="" class="lp3-spotlight-avatar" width="64" height="64" loading="lazy">
          <?php elseif (!empty($iv['winner_name'])): ?>
            <span class="lp3-avatar-ph lp3-spotlight-avatar" style="font-size:0.7rem;">NO<br>IMG</span>
          <?php endif; ?>
          <div class="lp3-spotlight-body">
            <div class="lp3-spotlight-meta">
              <?php if ($spEvent): ?>
                <span class="lp3-chip is-pink"><?= h($spEvent->label()) ?></span>
              <?php endif; ?>
              <span class="lp3-spotlight-tournament"><?= h($iv['tournament_name']) ?></span>
            </div>
            <?php if (!empty($iv['winner_name'])): ?>
              <div class="lp3-spotlight-name"><?= h($iv['winner_name']) ?></div>
            <?php endif; ?>
          </div>
          <a href="interview?id=<?= (int) $iv['tournament_id'] ?>" class="lp3-btn lp3-btn-primary lp3-btn-arrow">インタビューを読む</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  </div>
</section>
<?php endif; ?>
