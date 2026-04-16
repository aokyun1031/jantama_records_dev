<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

// --- バリデーション ---
$playerId = requirePlayerId();
$player = requirePlayer($playerId);

['data' => $summary, 'error' => $e2]      = fetchData(fn() => PlayerAnalysis::summary($playerId));
['data' => $avgRank, 'error' => $e3]       = fetchData(fn() => PlayerAnalysis::avgTableRank($playerId));
['data' => $headToHead, 'error' => $e4]    = fetchData(fn() => PlayerAnalysis::headToHead($playerId));
['data' => $scoreHistory, 'error' => $e5]  = fetchData(fn() => PlayerAnalysis::scoreHistory($playerId));
$error = $e2 || $e3 || $e4 || $e5;

// --- テンプレート変数 ---
$pageTitle = h($player['name']) . ' 戦績分析 - ' . SITE_NAME;
$pageDescription = h($player['name']) . ' の戦績分析ページです。';
$pageCss = ['css/player-analysis.css'];
$pageStyle = '';

// --- 表示 ---
require __DIR__ . '/../templates/header.php';

// スコア推移バー幅計算用
$maxAbsScore = 1;
if ($scoreHistory) {
    foreach ($scoreHistory as $sh) {
        $abs = abs((float) $sh['score']);
        if ($abs > $maxAbsScore) $maxAbsScore = $abs;
    }
}
?>

<div class="analysis-hero">
  <div class="analysis-badge">ANALYSIS</div>
  <div class="analysis-identity">
    <?php if ($player['character_icon']): ?>
      <img src="img/chara_deformed/<?= h($player['character_icon']) ?>" alt="<?= h($player['name']) ?>" class="analysis-hero-icon" width="88" height="88">
    <?php endif; ?>
    <h1 class="analysis-title"><?= h($player['name']) ?></h1>
    <?php if ($player['nickname'] !== null && $player['nickname'] !== ''): ?><div class="analysis-nickname"><?= h($player['nickname']) ?></div><?php endif; ?>
  </div>
</div>

<?php if ($error): ?>
  <div class="analysis-error">
    データベース接続エラー。しばらくしてから再度お試しください。
  </div>
<?php elseif (!$summary || (int) $summary['total_rounds'] === 0): ?>
  <div class="analysis-error">
    参加した大会はまだありません。戦績データが登録されると分析結果が表示されます。
  </div>
<?php else: ?>

  <!-- 通算成績サマリー -->
  <?php if ($summary && (int) $summary['total_rounds'] > 0):
    $qualifyingRounds = (int) $summary['qualifying_rounds'];
    $qualifyingPasses = (int) $summary['qualifying_passes'];
    $passRate = $qualifyingRounds > 0 ? round($qualifyingPasses / $qualifyingRounds * 100) : 0;
  ?>
  <div class="summary-grid">
    <div class="summary-card">
      <div class="summary-label">参加大会</div>
      <div class="summary-value"><?= (int) $summary['total_tournaments'] ?></div>
      <div class="summary-sub">大会</div>
    </div>
    <div class="summary-card">
      <div class="summary-label">平均スコア</div>
      <div class="summary-value"><?= number_format((float) $summary['avg_score'], 1) ?></div>
      <div class="summary-sub">pt/回戦</div>
    </div>
    <div class="summary-card">
      <div class="summary-label">卓内平均着順</div>
      <div class="summary-value"><?= $avgRank ? number_format((float) $avgRank, 2) : '-' ?></div>
      <div class="summary-sub">/4人中</div>
    </div>
    <div class="summary-card">
      <div class="summary-label">予選突破率</div>
      <div class="summary-value"><?= $passRate ?>%</div>
      <div class="summary-sub">予選<?= $qualifyingRounds ?>回戦中<?= $qualifyingPasses ?>回突破</div>
    </div>
    <div class="summary-card">
      <div class="summary-label">最高スコア</div>
      <div class="summary-value"><?= number_format((float) $summary['best_score'], 1) ?></div>
    </div>
    <div class="summary-card">
      <div class="summary-label">最低スコア</div>
      <div class="summary-value"><?= number_format((float) $summary['worst_score'], 1) ?></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- 同卓対戦成績 -->
  <?php if ($headToHead): ?>
  <div class="h2h-section">
    <div class="analysis-section-title">同卓対戦成績</div>
    <table class="h2h-table">
      <thead>
        <tr>
          <th class="h2h-sortable" data-col="0">対戦相手 <span class="sort-icon"></span></th>
          <th class="h2h-sortable" data-col="1">戦績 <span class="sort-icon"></span></th>
          <th class="h2h-sortable" data-col="2">同卓数 <span class="sort-icon"></span></th>
          <th class="h2h-sortable" data-col="3">自分の平均着順 <span class="sort-icon"></span></th>
          <th class="h2h-sortable" data-col="4">相手の平均着順 <span class="sort-icon"></span></th>
          <th class="h2h-sortable" data-col="5">平均スコア差 <span class="sort-icon"></span></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($headToHead as $h):
        $games = (int) $h['games'];
        $hasPlayed = $games > 0;
        $scoreDiff = $hasPlayed ? (float) $h['avg_my_score'] - (float) $h['avg_opp_score'] : 0;
      ?>
        <tr<?= !$hasPlayed ? ' class="h2h-none"' : '' ?>>
          <td class="<?= $hasPlayed ? 'h2h-name' : '' ?>" data-sort-value="<?= h($h['opponent_name']) ?>"><?= h($h['opponent_name']) ?></td>
          <?php if ($hasPlayed):
            $winRate = $games > 0 ? (int) $h['wins'] / $games : 0;
            $myRank = (float) $h['avg_my_rank'];
            $oppRank = (float) $h['avg_opp_rank'];
          ?>
            <td class="h2h-record" data-sort-value="<?= number_format($winRate, 4) ?>">
              <span class="h2h-wins"><?= (int) $h['wins'] ?></span>
              <span style="color:var(--text-light)"> - </span>
              <span class="h2h-losses"><?= (int) $h['losses'] ?></span>
            </td>
            <td class="h2h-games" data-sort-value="<?= $games ?>"><?= $games ?>回</td>
            <td class="h2h-rank" data-sort-value="<?= number_format($myRank, 4) ?>"><?= number_format($myRank, 1) ?>位</td>
            <td class="h2h-rank" data-sort-value="<?= number_format($oppRank, 4) ?>"><?= number_format($oppRank, 1) ?>位</td>
            <td class="h2h-avg <?= $scoreDiff >= 0 ? 'score-plus' : 'score-minus' ?>" data-sort-value="<?= number_format($scoreDiff, 4) ?>">
              <?= $scoreDiff >= 0 ? '+' : '' ?><?= number_format($scoreDiff, 1) ?>
            </td>
          <?php else: ?>
            <td data-sort-value="-1">-</td>
            <td data-sort-value="-1">0回</td>
            <td data-sort-value="99">-</td>
            <td data-sort-value="99">-</td>
            <td data-sort-value="-9999">-</td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- スコア推移 -->
  <?php if ($scoreHistory): ?>
  <div class="history-section">
    <div class="analysis-section-title">スコア推移</div>
    <table class="history-table">
      <thead>
        <tr>
          <th>回戦</th>
          <th>日時</th>
          <th>スコア</th>
          <th></th>
          <th>結果</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($scoreHistory as $sh):
        $score = (float) $sh['score'];
        $barWidth = round(abs($score) / $maxAbsScore * 100);
        $aboveCutoff = $sh['is_above_cutoff'] === true || $sh['is_above_cutoff'] === 't' || $sh['is_above_cutoff'] === '1';
      ?>
        <tr>
          <td class="history-round"><?= h($sh['tournament_name']) ?> <?= (int) $sh['round_number'] ?>回戦</td>
          <td class="history-date"><?php
            if ($sh['played_date']) {
                $d = date('Y/n/j', strtotime($sh['played_date']));
                $dow = $sh['day_of_week'] ? mb_substr($sh['day_of_week'], 0, 1) : '';
                $time = $sh['played_time'] ?? '';
                echo h($d . ($dow !== '' ? '（' . $dow . '）' : '') . ($time !== '' ? ' ' . $time : ''));
            } else {
                echo '-';
            }
          ?></td>
          <td class="history-score <?= $score >= 0 ? 'score-plus' : 'score-minus' ?>">
            <?= $score >= 0 ? '+' : '' ?><?= number_format($score, 1) ?>
          </td>
          <td>
            <div class="history-bar-wrap">
              <div class="history-bar <?= $score >= 0 ? 'bar-plus' : 'bar-minus' ?>"
                   style="width: <?= $barWidth ?>%"></div>
            </div>
          </td>
          <td><span class="history-tag <?= $aboveCutoff ? 'tag-pass' : 'tag-fail' ?>"><?= $aboveCutoff ? '通過' : '敗退' ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

<?php endif; ?>

<div style="text-align: center;">
  <a href="player?id=<?= $playerId ?>" class="btn-cancel">&#x2190; 個人ページに戻る</a>
</div>

<?php
$pageInlineScript = <<<'JS'
(function() {
  var table = document.querySelector('.h2h-table');
  if (!table) return;
  var headers = table.querySelectorAll('.h2h-sortable');
  var tbody = table.querySelector('tbody');

  headers.forEach(function(th) {
    th.addEventListener('click', function() {
      var col = parseInt(th.dataset.col);
      var isText = col === 0;
      var currentDir = th.classList.contains('sort-asc') ? 'asc' : th.classList.contains('sort-desc') ? 'desc' : null;
      var newDir = currentDir === 'desc' ? 'asc' : 'desc';

      headers.forEach(function(h) { h.classList.remove('sort-asc', 'sort-desc'); });
      th.classList.add('sort-' + newDir);

      var rows = Array.from(tbody.querySelectorAll('tr'));
      rows.sort(function(a, b) {
        var aPlayed = !a.classList.contains('h2h-none');
        var bPlayed = !b.classList.contains('h2h-none');
        if (aPlayed !== bPlayed) return aPlayed ? -1 : 1;

        var aVal = a.children[col].dataset.sortValue;
        var bVal = b.children[col].dataset.sortValue;
        if (isText) {
          return newDir === 'asc' ? aVal.localeCompare(bVal, 'ja') : bVal.localeCompare(aVal, 'ja');
        }
        return newDir === 'asc' ? parseFloat(aVal) - parseFloat(bVal) : parseFloat(bVal) - parseFloat(aVal);
      });
      rows.forEach(function(row) { tbody.appendChild(row); });
    });
  });
})();
JS;
require __DIR__ . '/../templates/footer.php'; ?>
