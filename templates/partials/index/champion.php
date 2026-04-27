<?php
/**
 * index.php の CHAMPION SPOTLIGHT セクション。
 * 親スコープから受け取る変数: $latestChampion, $latestCompleted
 *                          ヘルパー: $dividerHtml クロージャ
 */
?>
<!-- ======================== -->
<!-- CHAMPION SPOTLIGHT -->
<!-- ======================== -->
<?php if ($latestChampion && $latestCompleted): ?>
<?= $dividerHtml('champion') ?>
<section class="lp3-band band-champion lp3-reveal">
  <div class="lp3-inner">
    <h2 class="lp3-heading">最新王者</h2>
  <div class="lp3-card lp3-champion">
    <div class="lp3-champion-grid">
      <div class="lp3-champion-avatar">
        <?php if (!empty($latestChampion['character_icon'])): ?>
          <img src="img/chara_deformed/<?= h($latestChampion['character_icon']) ?>" alt="" width="120" height="120" loading="eager">
        <?php else: ?>
          <div class="lp3-avatar-ph" style="width:100%;height:100%;font-size:1.2rem;">NO<br>IMG</div>
        <?php endif; ?>
      </div>
      <div class="lp3-champion-body">
        <?php
        $champEvent = EventType::tryFrom($latestCompleted['event_type'] ?? '');
        $champName = $latestChampion['nickname'] ?? $latestChampion['name'];
        $champDate = !empty($latestCompleted['start_date']) ? date('Y.m.d', strtotime($latestCompleted['start_date'])) : '';
        $champScore = (float) $latestChampion['total'];
        ?>
        <div class="lp3-champion-tournament-line">
          <?php if ($champEvent): ?>
            <span class="lp3-chip is-pink"><?= h($champEvent->label()) ?></span>
          <?php endif; ?>
          <span class="lp3-champion-tournament"><?= h($latestCompleted['name']) ?></span>
          <?php if ($champDate !== ''): ?>
            <span class="lp3-champion-date"><?= h($champDate) ?></span>
          <?php endif; ?>
        </div>
        <div class="lp3-champion-main">
          <h3 class="lp3-champion-name"><?= h($champName) ?></h3>
          <div class="lp3-champion-score">
            <span class="lp3-champion-score-num"><?= ($champScore >= 0 ? '+' : '') . number_format($champScore, 1) ?></span>
            <span class="lp3-champion-score-unit">pt</span>
          </div>
        </div>
        <div class="lp3-champion-actions">
          <a href="tournament_view?id=<?= (int) $latestCompleted['id'] ?>" class="lp3-btn lp3-btn-primary lp3-btn-arrow">大会の詳細</a>
          <a href="player?id=<?= (int) $latestChampion['player_id'] ?>" class="lp3-btn lp3-btn-secondary">選手ページへ</a>
        </div>
      </div>
    </div>
  </div>
  </div>
</section>
<?php endif; ?>
