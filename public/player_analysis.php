<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

// --- バリデーション ---
$playerId = requirePlayerId();
$player = requirePlayer($playerId);

// 大会種別フィルタ: GET パラメータを EventType で検証
$rawEventTypes = $_GET['event_types'] ?? [];
if (!is_array($rawEventTypes)) {
    $rawEventTypes = [];
}
$selectedEventTypes = [];
foreach ($rawEventTypes as $v) {
    if (is_string($v) && EventType::tryFrom($v) !== null && !in_array($v, $selectedEventTypes, true)) {
        $selectedEventTypes[] = $v;
    }
}
$isFiltered = !empty($selectedEventTypes);

['data' => $summary, 'error' => $e2]          = fetchData(fn() => PlayerAnalysis::summary($playerId, $selectedEventTypes));
['data' => $avgRank, 'error' => $e3]          = fetchData(fn() => PlayerAnalysis::avgTableRank($playerId, $selectedEventTypes));
['data' => $headToHead, 'error' => $e4]       = fetchData(fn() => PlayerAnalysis::headToHead($playerId, $selectedEventTypes));
['data' => $scoreHistory, 'error' => $e5]     = fetchData(fn() => PlayerAnalysis::scoreHistory($playerId, $selectedEventTypes));
['data' => $rankStats, 'error' => $e6]        = fetchData(fn() => PlayerAnalysis::rankStats($playerId, $selectedEventTypes));
['data' => $rankDist, 'error' => $e7]         = fetchData(fn() => PlayerAnalysis::rankDistribution($playerId, $selectedEventTypes));
['data' => $timeline, 'error' => $e8]         = fetchData(fn() => PlayerAnalysis::scoreTimeline($playerId, $selectedEventTypes));
['data' => $bestFinalRank, 'error' => $e9]    = fetchData(fn() => PlayerAnalysis::bestFinalRank($playerId, $selectedEventTypes));
['data' => $roundPerf, 'error' => $e10]       = fetchData(fn() => PlayerAnalysis::roundPerformance($playerId, $selectedEventTypes));
['data' => $eventStats, 'error' => $e11]      = fetchData(fn() => PlayerAnalysis::eventTypeStats($playerId, $selectedEventTypes));
['data' => $elimDist, 'error' => $e12]        = fetchData(fn() => PlayerAnalysis::eliminationDistribution($playerId, $selectedEventTypes));
$error = $e2 || $e3 || $e4 || $e5 || $e6 || $e7 || $e8 || $e9 || $e10 || $e11 || $e12;

// --- テンプレート変数 ---
$pageTitle = h($player['name']) . ' 戦績分析 - ' . SITE_NAME;
$pageDescription = h($player['name']) . ' の戦績分析ページです。';
$pageCss = ['css/player-analysis.css'];
$pageScripts = ['js/vendor/chart.umd.min.js'];
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

<?php if (!$error): ?>
<div class="event-filter-wrap" role="region" aria-label="大会種別で絞り込み">
  <form method="get" id="event-filter-form" class="event-filter-form">
    <input type="hidden" name="id" value="<?= $playerId ?>">
    <div class="event-filter-label">大会種別で絞り込み<span class="event-filter-hint">（未選択で全て）</span></div>
    <div class="event-filter-chips" role="group" aria-label="大会種別">
      <?php foreach (EventType::cases() as $type):
        $checked = in_array($type->value, $selectedEventTypes, true);
      ?>
      <label class="event-chip<?= $checked ? ' is-selected' : '' ?>">
        <input type="checkbox" name="event_types[]" value="<?= h($type->value) ?>"<?= $checked ? ' checked' : '' ?>>
        <span class="event-chip-text"><?= h($type->label()) ?></span>
      </label>
      <?php endforeach; ?>
    </div>
    <?php if ($isFiltered): ?>
    <a href="player_analysis?id=<?= $playerId ?>" class="event-filter-clear">クリア</a>
    <?php endif; ?>
    <noscript><button type="submit" class="event-filter-submit">適用</button></noscript>
  </form>
</div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="analysis-error">
    データベース接続エラー。しばらくしてから再度お試しください。
  </div>
<?php elseif (!$summary || (int) $summary['total_rounds'] === 0): ?>
  <div class="analysis-error">
    <?= $isFiltered ? '選択した大会種別の参加データがありません。「クリア」を押すと全ての大会が表示されます。' : '参加した大会はまだありません。戦績データが登録されると分析結果が表示されます。' ?>
  </div>
<?php else: ?>

  <!-- 通算成績サマリー -->
  <?php if ($summary && (int) $summary['total_rounds'] > 0):
    $passRate = (int) $summary['qualifying_rounds'] > 0
        ? round((int) $summary['qualifying_passes'] / (int) $summary['qualifying_rounds'] * 100)
        : 0;

    $tableGames = (int) ($rankStats['table_games'] ?? 0);
    $topCount = (int) ($rankStats['top_count'] ?? 0);
    $lastCount = (int) ($rankStats['last_count'] ?? 0);
    $renpaiCount = (int) ($rankStats['second_or_better'] ?? 0);
    $topRate = $tableGames > 0 ? round($topCount / $tableGames * 100) : 0;
    $lastRate = $tableGames > 0 ? round($lastCount / $tableGames * 100) : 0;
    $renpaiRate = $tableGames > 0 ? round($renpaiCount / $tableGames * 100) : 0;
    $scoreStddev = $rankStats['score_stddev'] ?? null;
  ?>
  <div class="summary-grid">
    <!-- 規模 -->
    <div class="summary-card" data-hint="この選手が1回戦以上出場した大会の総数。">
      <div class="summary-label">参加大会<span class="summary-info" aria-hidden="true">i</span></div>
      <div class="summary-value"><?= (int) $summary['total_tournaments'] ?></div>
      <div class="summary-sub">大会</div>
    </div>

    <!-- スコア統計 -->
    <div class="summary-card" data-hint="全回戦の素点（pt）の単純平均。1回戦ごとに集計。">
      <div class="summary-label">平均スコア<span class="summary-info" aria-hidden="true">i</span></div>
      <div class="summary-value"><?= number_format((float) $summary['avg_score'], 1) ?></div>
      <div class="summary-sub">pt</div>
    </div>
    <div class="summary-card" data-hint="全回戦の中で最も高かった1回戦分の素点（pt）。">
      <div class="summary-label">最高スコア<span class="summary-info" aria-hidden="true">i</span></div>
      <div class="summary-value"><?= number_format((float) $summary['best_score'], 1) ?></div>
    </div>
    <div class="summary-card" data-hint="回戦スコアのばらつきを示す標本標準偏差。小さいほど安定している。">
      <div class="summary-label">スコア標準偏差<span class="summary-info" aria-hidden="true">i</span></div>
      <div class="summary-value"><?= $scoreStddev !== null ? number_format((float) $scoreStddev, 1) : '-' ?></div>
      <div class="summary-sub">安定度</div>
    </div>

    <!-- 着順統計 -->
    <div class="summary-card" data-hint="各卓内でのスコア順位（1〜4位）の平均。小さいほど良い。">
      <div class="summary-label">卓内平均着順<span class="summary-info" aria-hidden="true">i</span></div>
      <div class="summary-value"><?= $avgRank ? number_format((float) $avgRank, 2) : '-' ?></div>
      <div class="summary-sub">/4人中</div>
    </div>
    <div class="summary-card" data-hint="卓内で1位となった卓数 ÷ 全参加卓数。3麻・4麻を合算。">
      <div class="summary-label">トップ率<span class="summary-info" aria-hidden="true">i</span></div>
      <div class="summary-value"><?= $topRate ?>%</div>
      <div class="summary-sub"><?= $tableGames ?>卓中<?= $topCount ?>回</div>
    </div>
    <div class="summary-card" data-hint="卓内で2位以内に入った卓数 ÷ 全参加卓数。">
      <div class="summary-label">連帯率<span class="summary-info" aria-hidden="true">i</span></div>
      <div class="summary-value"><?= $renpaiRate ?>%</div>
      <div class="summary-sub">2位以内</div>
    </div>
    <div class="summary-card" data-hint="卓内で最下位となった卓数 ÷ 全参加卓数。3麻は3位、4麻は4位が最下位。">
      <div class="summary-label">ラス率<span class="summary-info" aria-hidden="true">i</span></div>
      <div class="summary-value"><?= $lastRate ?>%</div>
      <div class="summary-sub"><?= $tableGames ?>卓中<?= $lastCount ?>回</div>
    </div>

    <!-- 大会成績 -->
    <div class="summary-card" data-hint="最終回戦を除く予選回戦のうち、ボーダー以上で通過した回戦の割合。">
      <div class="summary-label">予選突破率<span class="summary-info" aria-hidden="true">i</span></div>
      <div class="summary-value"><?= $passRate ?>%</div>
    </div>
    <div class="summary-card" data-hint="参加した大会ごとの最終順位（合計pt降順）の最良値。">
      <div class="summary-label">最高順位<span class="summary-info" aria-hidden="true">i</span></div>
      <div class="summary-value"><?= $bestFinalRank !== null ? $bestFinalRank . '位' : '-' ?></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- チャートセクション -->
  <?php
  $hasRankDist = !empty($rankDist);
  $hasTimeline = !empty($timeline);
  $hasRoundPerf = !empty($roundPerf);
  $hasEventStats = !empty($eventStats) && count($eventStats) >= 2;
  $hasElimDist = !empty($elimDist);
  if ($hasRankDist || $hasTimeline || $hasRoundPerf || $hasEventStats || $hasElimDist):
    $distCounts = [0, 0, 0, 0];
    foreach (($rankDist ?? []) as $row) {
        $r = (int) $row['rank'];
        if ($r >= 1 && $r <= 4) {
            $distCounts[$r - 1] = (int) $row['cnt'];
        }
    }

    $cumulative = [];
    $running = 0.0;
    $idx = 0;
    foreach (($timeline ?? []) as $row) {
        $idx++;
        $running += (float) $row['score'];
        $cumulative[] = [
            'idx' => $idx,
            'score' => (float) $row['score'],
            'cumulative' => $running,
            'label' => $row['tournament_name'] . ' ' . (int) $row['round_number'] . '回戦',
        ];
    }

    $roundData = [];
    foreach (($roundPerf ?? []) as $row) {
        $roundData[] = [
            'round' => (int) $row['round_number'],
            'avg' => (float) $row['avg_score'],
            'games' => (int) $row['games'],
        ];
    }

    $eventData = [];
    foreach (($eventStats ?? []) as $row) {
        $label = EventType::tryFrom($row['event_type'])?->label() ?? $row['event_type'];
        $eventData[] = [
            'label' => $label,
            'avg_rank' => (float) $row['avg_rank'],
            'games' => (int) $row['games'],
            'top_rate' => (int) $row['games'] > 0 ? (int) $row['tops'] / (int) $row['games'] * 100 : 0,
        ];
    }

    $elimData = [];
    foreach (($elimDist ?? []) as $row) {
        $er = (int) $row['eliminated_round'];
        $elimData[] = [
            'round' => $er,
            'cnt' => (int) $row['cnt'],
            'label' => $er === 0 ? '優勝' : $er . '回戦敗退',
        ];
    }
  ?>
  <div class="chart-grid">
    <?php if ($hasRankDist): ?>
    <div class="chart-card chart-card-small">
      <div class="analysis-section-title">着順分布</div>
      <div class="chart-canvas-wrap"><canvas id="rankDistChart" aria-label="卓内着順分布" role="img"></canvas></div>
    </div>
    <?php endif; ?>

    <?php if ($hasTimeline): ?>
    <div class="chart-card chart-card-wide">
      <div class="analysis-section-title">累計スコア推移</div>
      <div class="chart-canvas-wrap"><canvas id="cumulativeChart" aria-label="累計スコア推移" role="img"></canvas></div>
    </div>
    <?php endif; ?>

    <?php if ($hasEventStats): ?>
    <div class="chart-card chart-card-small">
      <div class="analysis-section-title">イベント種別別の成績</div>
      <div class="chart-canvas-wrap"><canvas id="eventRadarChart" aria-label="イベント種別別の平均着順" role="img"></canvas></div>
    </div>
    <?php endif; ?>

    <?php if ($hasRoundPerf): ?>
    <div class="chart-card chart-card-wide">
      <div class="analysis-section-title">回戦別パフォーマンス</div>
      <div class="chart-canvas-wrap"><canvas id="roundPerfChart" aria-label="回戦別平均スコア" role="img"></canvas></div>
    </div>
    <?php endif; ?>

    <?php if ($hasElimDist): ?>
    <div class="chart-card chart-card-full">
      <div class="analysis-section-title">敗退ラウンド分布（完了大会のみ）</div>
      <div class="chart-canvas-wrap"><canvas id="elimDistChart" aria-label="敗退ラウンド分布" role="img"></canvas></div>
    </div>
    <?php endif; ?>
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
$chartData = [
    'rankDist' => $hasRankDist ?? false ? $distCounts : null,
    'cumulative' => $hasTimeline ?? false ? $cumulative : null,
    'roundPerf' => $hasRoundPerf ?? false ? $roundData : null,
    'eventStats' => $hasEventStats ?? false ? $eventData : null,
    'elimDist' => $hasElimDist ?? false ? $elimData : null,
];
$chartDataJson = json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

$pageInlineScript = 'window.__playerAnalysisData = ' . $chartDataJson . ";\n" . <<<'JS'
(function() {
  var filterForm = document.getElementById('event-filter-form');
  if (filterForm) {
    filterForm.querySelectorAll('input[name="event_types[]"]').forEach(function(cb) {
      cb.addEventListener('change', function() {
        var chip = cb.closest('.event-chip');
        if (chip) chip.classList.toggle('is-selected', cb.checked);
        filterForm.submit();
      });
    });
  }

  var table = document.querySelector('.h2h-table');
  if (table) {
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
  }

  // サマリーカードのヒント: モバイル tap で開閉、外側クリックで閉じる
  var hintCards = document.querySelectorAll('.summary-card[data-hint]');
  hintCards.forEach(function(card) {
    card.addEventListener('click', function(e) {
      var wasOpen = card.classList.contains('hint-open');
      hintCards.forEach(function(c) { c.classList.remove('hint-open'); });
      if (!wasOpen) card.classList.add('hint-open');
      e.stopPropagation();
    });
  });
  document.addEventListener('click', function() {
    hintCards.forEach(function(c) { c.classList.remove('hint-open'); });
  });

  function cssVar(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  }
  function renderCharts() {
    if (typeof Chart === 'undefined') return;
    var data = window.__playerAnalysisData || {};
    var textColor = cssVar('--text') || '#222';
    var subColor = cssVar('--text-sub') || '#666';
    var gridColor = 'rgba(128, 128, 128, 0.15)';
    Chart.defaults.color = subColor;
    Chart.defaults.font.family = "'Inter', 'Noto Sans JP', sans-serif";

    // 着順分布ドーナツ
    var distEl = document.getElementById('rankDistChart');
    if (distEl && data.rankDist) {
      new Chart(distEl, {
        type: 'doughnut',
        data: {
          labels: ['1位', '2位', '3位', '4位'],
          datasets: [{
            data: data.rankDist,
            backgroundColor: ['#ff6b6b', '#ffa94d', '#74c0fc', '#4c6ef5'],
            borderWidth: 0,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom', labels: { color: textColor, boxWidth: 12, padding: 10 } },
            tooltip: {
              callbacks: {
                label: function(ctx) {
                  var total = ctx.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                  var pct = total > 0 ? Math.round(ctx.parsed / total * 100) : 0;
                  return ctx.label + ': ' + ctx.parsed + '回 (' + pct + '%)';
                }
              }
            }
          }
        }
      });
    }

    // 累計スコア推移折れ線
    var cumEl = document.getElementById('cumulativeChart');
    if (cumEl && data.cumulative) {
      var labels = data.cumulative.map(function(d) { return d.label; });
      var cumValues = data.cumulative.map(function(d) { return d.cumulative; });
      new Chart(cumEl, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [{
            label: '累計スコア',
            data: cumValues,
            borderColor: '#845ef7',
            backgroundColor: 'rgba(132, 94, 247, 0.1)',
            borderWidth: 2,
            pointRadius: 2,
            pointHoverRadius: 5,
            tension: 0.25,
            fill: true,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                title: function(items) { return items[0].label; },
                label: function(ctx) {
                  var s = data.cumulative[ctx.dataIndex];
                  var sign = s.score >= 0 ? '+' : '';
                  return '累計: ' + s.cumulative.toFixed(1) + ' (当回戦 ' + sign + s.score.toFixed(1) + ')';
                }
              }
            }
          },
          scales: {
            x: { ticks: { display: false }, grid: { color: gridColor } },
            y: { grid: { color: gridColor } }
          }
        }
      });
    }

    // イベント種別別の成績（レーダー: 平均着順を反転して外側=良）
    var evEl = document.getElementById('eventRadarChart');
    if (evEl && data.eventStats) {
      var evLabels = data.eventStats.map(function(d) { return d.label; });
      var evAvgRanks = data.eventStats.map(function(d) { return d.avg_rank; });
      new Chart(evEl, {
        type: 'radar',
        data: {
          labels: evLabels,
          datasets: [{
            label: '平均着順',
            data: evAvgRanks,
            borderColor: '#845ef7',
            backgroundColor: 'rgba(132, 94, 247, 0.2)',
            borderWidth: 2,
            pointBackgroundColor: '#845ef7',
            pointRadius: 3,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function(ctx) {
                  var d = data.eventStats[ctx.dataIndex];
                  return '平均 ' + d.avg_rank.toFixed(2) + '位 / トップ率 ' + d.top_rate.toFixed(0) + '% (' + d.games + '卓)';
                }
              }
            }
          },
          scales: {
            r: {
              reverse: true,
              min: 1,
              max: 4,
              ticks: { stepSize: 1, backdropColor: 'transparent', color: subColor },
              grid: { color: gridColor },
              angleLines: { color: gridColor },
              pointLabels: { color: textColor, font: { size: 11 } }
            }
          }
        }
      });
    }

    // 回戦別パフォーマンス（折れ線 + 試合数の棒）
    var rpEl = document.getElementById('roundPerfChart');
    if (rpEl && data.roundPerf) {
      var rpLabels = data.roundPerf.map(function(d) { return d.round + '回戦'; });
      var rpAvg = data.roundPerf.map(function(d) { return d.avg; });
      var rpGames = data.roundPerf.map(function(d) { return d.games; });
      new Chart(rpEl, {
        data: {
          labels: rpLabels,
          datasets: [
            {
              type: 'bar',
              label: '試合数',
              data: rpGames,
              backgroundColor: 'rgba(132, 94, 247, 0.18)',
              borderRadius: 4,
              yAxisID: 'yGames',
              order: 2,
            },
            {
              type: 'line',
              label: '平均スコア',
              data: rpAvg,
              borderColor: '#ff6b6b',
              backgroundColor: 'rgba(255, 107, 107, 0.1)',
              borderWidth: 2,
              pointRadius: 4,
              pointHoverRadius: 6,
              tension: 0.25,
              yAxisID: 'yScore',
              order: 1,
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { position: 'bottom', labels: { color: textColor, boxWidth: 12, padding: 10 } },
            tooltip: {
              callbacks: {
                label: function(ctx) {
                  if (ctx.dataset.type === 'line') {
                    var sign = ctx.parsed.y >= 0 ? '+' : '';
                    return '平均スコア: ' + sign + ctx.parsed.y.toFixed(1);
                  }
                  return '試合数: ' + ctx.parsed.y;
                }
              }
            }
          },
          scales: {
            x: { grid: { color: gridColor } },
            yScore: {
              type: 'linear',
              position: 'left',
              title: { display: true, text: '平均スコア (pt)', color: subColor },
              grid: { color: gridColor }
            },
            yGames: {
              type: 'linear',
              position: 'right',
              beginAtZero: true,
              title: { display: true, text: '試合数', color: subColor },
              grid: { display: false },
              ticks: { stepSize: 1 }
            }
          }
        }
      });
    }

    // 敗退ラウンド分布（棒）
    var edEl = document.getElementById('elimDistChart');
    if (edEl && data.elimDist) {
      var edLabels = data.elimDist.map(function(d) { return d.label; });
      var edCounts = data.elimDist.map(function(d) { return d.cnt; });
      var edColors = data.elimDist.map(function(d) {
        return d.round === 0 ? '#ffd43b' : 'rgba(132, 94, 247, 0.6)';
      });
      new Chart(edEl, {
        type: 'bar',
        data: {
          labels: edLabels,
          datasets: [{
            label: '大会数',
            data: edCounts,
            backgroundColor: edColors,
            borderRadius: 4,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function(ctx) { return ctx.parsed.y + '大会'; }
              }
            }
          },
          scales: {
            x: { grid: { display: false } },
            y: {
              beginAtZero: true,
              ticks: { stepSize: 1 },
              grid: { color: gridColor }
            }
          }
        }
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderCharts);
  } else {
    renderCharts();
  }
})();
JS;
require __DIR__ . '/../templates/footer.php'; ?>
