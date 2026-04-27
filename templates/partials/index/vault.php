<?php
/**
 * index.php の VAULT セクション。
 * 親スコープから受け取る変数: $championCounts, $highestScores, $mostTops
 *                          ヘルパー: $dividerHtml クロージャ
 */
?>
<!-- ======================== -->
<!-- VAULT -->
<!-- ======================== -->
<?= $dividerHtml('vault') ?>
<section class="lp3-band band-vault lp3-reveal">
  <div class="lp3-inner">
    <h2 class="lp3-heading">大会記録</h2>
  <div class="lp3-vault">

    <!-- 通算優勝回数 TOP3 -->
    <div class="lp3-card lp3-vault-cell is-wide">
      <div class="lp3-vault-title">通算優勝回数 TOP3</div>
      <div class="lp3-vault-sub">大会を制した累計回数</div>
      <?php if (!empty($championCounts)):
          $medals = ['🥇', '🥈', '🥉'];
      ?>
        <div class="lp3-champion-list">
          <?php foreach ($championCounts as $i => $ch): $rank = $i + 1; ?>
            <a href="player?id=<?= (int) $ch['player_id'] ?>" class="lp3-card lp3-champion-row">
              <span class="lp3-rank" aria-label="<?= h((string) $rank) ?>位"><?= h($medals[$i] ?? '') ?></span>
              <?php if (!empty($ch['character_icon'])): ?>
                <img src="img/chara_deformed/<?= h($ch['character_icon']) ?>" alt="" class="lp3-avatar" width="48" height="48" loading="lazy">
              <?php else: ?>
                <span class="lp3-avatar-ph">NO<br>IMG</span>
              <?php endif; ?>
              <span class="lp3-champion-name-text"><?= h($ch['player_name']) ?></span>
              <span class="lp3-champion-count"><?= (int) $ch['win_count'] ?><small>回優勝</small></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="lp3-vault-empty">完了大会がまだありません</div>
      <?php endif; ?>
    </div>

    <!-- 最高ラウンド得点 TOP3 -->
    <div class="lp3-card lp3-vault-cell is-half">
      <div class="lp3-vault-title">最高ラウンド得点 TOP3</div>
      <div class="lp3-vault-sub">1試合（半荘1回）で記録された歴代最高スコア</div>
      <?php if (!empty($highestScores)):
          $medals = ['🥇', '🥈', '🥉'];
      ?>
        <div class="lp3-champion-list">
          <?php foreach ($highestScores as $i => $hs): $rank = $i + 1; ?>
            <a href="player?id=<?= (int) $hs['player_id'] ?>" class="lp3-card lp3-champion-row">
              <span class="lp3-rank" aria-label="<?= h((string) $rank) ?>位"><?= h($medals[$i] ?? '') ?></span>
              <?php if (!empty($hs['character_icon'])): ?>
                <img src="img/chara_deformed/<?= h($hs['character_icon']) ?>" alt="" class="lp3-avatar" width="48" height="48" loading="lazy">
              <?php else: ?>
                <span class="lp3-avatar-ph">NO<br>IMG</span>
              <?php endif; ?>
              <span class="lp3-champion-name-text"><?= h($hs['player_name']) ?></span>
              <span class="lp3-champion-count"><?= ((float) $hs['score'] >= 0 ? '+' : '') . number_format((float) $hs['score'], 1) ?><small>pt</small></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="lp3-vault-empty">記録なし</div>
      <?php endif; ?>
    </div>

    <!-- 最多卓1位 TOP3 -->
    <div class="lp3-card lp3-vault-cell is-half">
      <div class="lp3-vault-title">最多卓1位 TOP3</div>
      <div class="lp3-vault-sub">卓内で1位を取った累計回数</div>
      <?php if (!empty($mostTops)):
          $medals = ['🥇', '🥈', '🥉'];
      ?>
        <div class="lp3-champion-list">
          <?php foreach ($mostTops as $i => $mt): $rank = $i + 1; ?>
            <a href="player?id=<?= (int) $mt['player_id'] ?>" class="lp3-card lp3-champion-row">
              <span class="lp3-rank" aria-label="<?= h((string) $rank) ?>位"><?= h($medals[$i] ?? '') ?></span>
              <?php if (!empty($mt['character_icon'])): ?>
                <img src="img/chara_deformed/<?= h($mt['character_icon']) ?>" alt="" class="lp3-avatar" width="48" height="48" loading="lazy">
              <?php else: ?>
                <span class="lp3-avatar-ph">NO<br>IMG</span>
              <?php endif; ?>
              <span class="lp3-champion-name-text"><?= h($mt['player_name']) ?></span>
              <span class="lp3-champion-count"><?= (int) $mt['top_count'] ?><small>回</small></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="lp3-vault-empty">記録なし</div>
      <?php endif; ?>
    </div>

  </div>
  </div>
</section>
