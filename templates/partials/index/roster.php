<?php
/**
 * index.php の ROSTER (ランダム選手ピックアップ) セクション。
 * 親スコープから受け取る変数: $randomPlayers
 *                          ヘルパー: $dividerHtml クロージャ
 */
?>
<!-- ======================== -->
<!-- ROSTER — ランダム選手ピックアップ -->
<!-- ======================== -->
<?php if (!empty($randomPlayers)): ?>
<?= $dividerHtml('roster') ?>
<section class="lp3-band band-roster lp3-reveal">
  <div class="lp3-inner">
    <h2 class="lp3-heading">選手紹介</h2>
  <div class="lp3-roster-scroller">
    <?php foreach ($randomPlayers as $pl):
        $bestScore = isset($pl['best_score']) ? (float) $pl['best_score'] : null;
        $winCount = (int) ($pl['win_count'] ?? 0);
        $tCount = (int) ($pl['tournament_count'] ?? 0);
    ?>
      <a href="player?id=<?= (int) $pl['player_id'] ?>" class="lp3-card lp3-roster-card">
        <?php if (!empty($pl['character_icon'])): ?>
          <img src="img/chara_deformed/<?= h($pl['character_icon']) ?>" alt="" class="lp3-roster-avatar" width="90" height="90" loading="lazy">
        <?php else: ?>
          <span class="lp3-roster-avatar-ph">NO<br>IMG</span>
        <?php endif; ?>
        <div class="lp3-roster-name"><?= h($pl['player_name']) ?></div>
        <div class="lp3-roster-badges">
          <?php if ($winCount > 0): ?>
            <span class="lp3-roster-badge">👑 優勝 <?= $winCount ?>回</span>
          <?php endif; ?>
          <span class="lp3-roster-badge is-ghost">出場 <?= $tCount ?>大会</span>
        </div>
        <div class="lp3-roster-best">
          <?php if ($bestScore !== null): ?>
            <span class="lp3-roster-best-label">最高スコア</span>
            <span class="lp3-roster-best-num"><?= ($bestScore >= 0 ? '+' : '') . number_format($bestScore, 1) ?><small>pt</small></span>
          <?php else: ?>
            <span class="lp3-roster-best-label">最高スコア</span>
            <span class="lp3-roster-best-num is-na">—</span>
          <?php endif; ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
  <div style="text-align:right;margin-top:14px;">
    <a href="players" class="lp3-btn lp3-btn-ghost lp3-btn-sm lp3-btn-arrow">選手一覧を見る</a>
  </div>
  </div>
</section>
<?php endif; ?>
