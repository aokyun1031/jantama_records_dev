<?php
/**
 * tournament_view.php の Round Details セクション。
 *
 * 親スコープから受け取る変数:
 *   $totalRounds, $roundNumbers, $roundSettings, $roundsData,
 *   $roundPlayerCounts, $roundAbove, $roundBelow, $lastRound, $isCompleted
 */
?>
<!-- Round Details -->
<?php if ($totalRounds > 0): ?>
<section class="section reveal">
  <div class="section-header">
    <div class="section-title">対戦結果</div>
  </div>

  <div class="tabs">
    <?php foreach ($roundNumbers as $i => $rn):
      $rSettings = $roundSettings[$rn] ?? [];
      $label = $rSettings['is_final'] ? '決勝' : $rn . '回戦';
      $tableCount = count($roundsData[$rn] ?? []);
      $pCount = $roundPlayerCounts[$rn] ?? 0;
      $isLast = ($i === $totalRounds - 1);
    ?>
      <button class="tab-btn <?= $isLast ? 'active' : '' ?>" data-tab-index="<?= $i ?>"><?= h($label) ?><br><small><?= $tableCount ?>卓 <?= $pCount ?>名</small></button>
    <?php endforeach; ?>
  </div>

  <?php
    $posLabels = ['1st', '2nd', '3rd', '4th'];
  ?>
  <?php foreach ($roundNumbers as $i => $rn):
    $rSettings = $roundSettings[$rn] ?? [];
    $tables = $roundsData[$rn] ?? [];
    $above = $roundAbove[$rn] ?? [];
    $below = $roundBelow[$rn] ?? [];
    $showDone = $lastRound > 0 ? $rn >= ($lastRound - 1) : true;
    $isFinal = $rSettings['is_final'] || ($i === $totalRounds - 1 && $isCompleted);
    $isLast = ($i === $totalRounds - 1);

    // スコアマップ構築（卓別結果でプレイヤー名→スコア+アイコンを引く）
    $scoreMap = [];
    foreach ($above as $a) {
        $scoreMap[$a['name']] = $a;
    }
    foreach ($below as $b) {
        $scoreMap[$b['name']] = $b;
    }
  ?>
    <div class="tab-content <?= $isLast ? 'active' : '' ?>">
      <!-- 卓カード一覧 -->
      <div class="table-grid">
        <?php foreach ($tables as $t):
          $cardCls = 'table-card';
          $schedHtml = '';
          if ($showDone || $isFinal) {
              $cardCls .= $t['done'] ? ' table-done' : ' table-pending';
              $schedHtml = $t['done'] ? '&#10003; 完了' : (!empty($t['played_date']) ? '対戦待ち' : '未対戦');
          } elseif (!empty($t['day_of_week'])) {
              $schedHtml = h($t['day_of_week']);
          }
          $dateHtml = '';
          if (!empty($t['played_date'])) {
              $d = new DateTime($t['played_date']);
              $dow = !empty($t['day_of_week']) ? mb_substr($t['day_of_week'], 0, 1) : '';
              $dateHtml = (int) $d->format('n') . '/' . (int) $d->format('j');
              if ($dow !== '') {
                  $dateHtml .= '(' . h($dow) . ')';
              }
              if (!empty($t['played_time'])) {
                  $dateHtml .= ' ' . h($t['played_time']);
              }
          }
        ?>
          <a href="table?id=<?= $t['table_id'] ?>&amp;from=view" class="<?= $cardCls ?>">
            <div class="table-card-head">
              <div class="table-card-head-left">
                <span class="table-card-name"><?= h($t['table_name']) ?></span>
                <?php if ($dateHtml): ?><span class="table-card-schedule"><?= $dateHtml ?></span><?php endif; ?>
              </div>
              <?php if ($schedHtml): ?><span class="table-card-sched"><?= $schedHtml ?></span><?php endif; ?>
            </div>
            <?php if ($t['done']):
              $sorted = $t['players'];
              usort($sorted, fn($a, $b) => (float) ($b['score'] ?? 0) <=> (float) ($a['score'] ?? 0));
            ?>
              <div class="table-card-results">
                <?php foreach ($sorted as $j => $p):
                  $score = (float) ($p['score'] ?? 0);
                ?>
                  <div class="table-card-result-row">
                    <span class="table-card-pos <?= $j === 0 ? 'pos-1' : '' ?>"><?= $posLabels[$j] ?? ($j + 1) ?></span>
                    <span class="table-card-player"><?= charaIcon($p['icon'] ?? null, 22) ?><?= h($p['name']) ?></span>
                    <span class="table-card-score <?= scoreCls($score) ?>"><?= fmtScore($score) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <ul class="table-card-players">
                <?php foreach ($t['players'] as $p): ?>
                  <li><?= charaIcon($p['icon'] ?? null, 22) ?><?= h($p['name']) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if (!$isFinal): ?>
        <!-- 全体順位 -->
        <?php if (!empty($above)): ?>
          <div class="results-list">
            <div class="results-sub">全体順位</div>
            <?php foreach ($above as $rank => $r): ?>
              <div class="result-row result-advance">
                <div class="result-rank"><?= $rank + 1 ?></div>
                <div class="result-name"><?= charaIcon($r['icon'] ?? null, 24) ?><?= h($r['name']) ?><span class="result-advance-badge">&#x2714; 勝ち抜け</span></div>
                <div class="result-score <?= scoreCls($r['score']) ?>"><?= fmtScore($r['score']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($below)): ?>
          <div class="elim-section">
            <div class="elim-title">&#x25BC; 敗退</div>
            <?php foreach ($below as $rank => $r): ?>
              <div class="result-row elim-row">
                <div class="result-rank"><?= count($above) + $rank + 1 ?></div>
                <div class="result-name"><?= charaIcon($r['icon'] ?? null, 24) ?><?= h($r['name']) ?></div>
                <div class="result-score <?= scoreCls($r['score']) ?>"><?= fmtScore($r['score']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>
