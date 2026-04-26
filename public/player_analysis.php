<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

// --- バリデーション ---
$playerId = requirePlayerId();
$player = requirePlayer($playerId);

// 大会種別フィルタ: GET パラメータを EventType で検証
$rawEventTypes = filter_input(INPUT_GET, 'event_types', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
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
['data' => $rankDist, 'error' => $e7]         = fetchData(fn() => PlayerAnalysis::rankDistribution($playerId, $selectedEventTypes));
['data' => $timeline, 'error' => $e8]         = fetchData(fn() => PlayerAnalysis::scoreTimeline($playerId, $selectedEventTypes));
['data' => $bestFinalRank, 'error' => $e9]    = fetchData(fn() => PlayerAnalysis::bestFinalRank($playerId, $selectedEventTypes));
['data' => $roundPerf, 'error' => $e10]       = fetchData(fn() => PlayerAnalysis::roundPerformance($playerId, $selectedEventTypes));
['data' => $eventStats, 'error' => $e11]      = fetchData(fn() => PlayerAnalysis::eventTypeStats($playerId, $selectedEventTypes));
$error = $e2 || $e3 || $e4 || $e5 || $e7 || $e8 || $e9 || $e10 || $e11;

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
  <form method="get" id="event-filter-form" class="event-filter-form<?= $isFiltered ? ' is-active' : '' ?>">
    <input type="hidden" name="id" value="<?= $playerId ?>">
    <div class="event-filter-header">
      <svg class="event-filter-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
      </svg>
      <span class="event-filter-title">フィルタ</span>
      <span class="event-filter-sub">大会種別</span>
      <?php if ($isFiltered): ?>
        <span class="event-filter-badge" aria-label="<?= count($selectedEventTypes) ?>件選択中"><?= count($selectedEventTypes) ?></span>
      <?php endif; ?>
      <div class="event-filter-actions">
        <?php if ($isFiltered): ?>
          <a href="player_analysis?id=<?= $playerId ?>" class="event-filter-clear" aria-label="フィルタをクリア">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
            クリア
          </a>
        <?php endif; ?>
        <button type="submit" class="event-filter-submit" data-pending-count="0" hidden>
          適用<span class="event-filter-submit-count" aria-hidden="true"></span>
        </button>
      </div>
    </div>
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

  <?php if ($isFiltered): ?>
  <div class="filter-context" role="status" aria-live="polite">
    <span class="filter-context-label">集計対象</span>
    <span class="filter-context-types">
      <?php foreach ($selectedEventTypes as $v): ?>
        <span class="filter-context-tag"><?= h(EventType::from($v)->label()) ?></span>
      <?php endforeach; ?>
    </span>
    <span class="filter-context-count"><?= (int) $summary['total_rounds'] ?>回戦 / <?= (int) $summary['total_tournaments'] ?>大会</span>
  </div>
  <?php endif; ?>

  <!-- 通算成績サマリー -->
  <?php if ($summary && (int) $summary['total_rounds'] > 0):
    $passRate = (int) $summary['qualifying_rounds'] > 0
        ? round((int) $summary['qualifying_passes'] / (int) $summary['qualifying_rounds'] * 100)
        : 0;

    $tableGames = (int) ($summary['table_games'] ?? 0);
    $topCount = (int) ($summary['top_count'] ?? 0);
    $lastCount = (int) ($summary['last_count'] ?? 0);
    $renpaiCount = (int) ($summary['second_or_better'] ?? 0);
    $topRate = $tableGames > 0 ? round($topCount / $tableGames * 100) : 0;
    $lastRate = $tableGames > 0 ? round($lastCount / $tableGames * 100) : 0;
    $renpaiRate = $tableGames > 0 ? round($renpaiCount / $tableGames * 100) : 0;
    $scoreStddev = $summary['score_stddev'] ?? null;
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
  $hasEventStats = !empty($eventStats);

  $distCounts = [0, 0, 0, 0];
  foreach (($rankDist ?? []) as $row) {
      $r = (int) $row['rank'];
      if ($r >= 1 && $r <= 4) {
          $distCounts[$r - 1] = (int) $row['cnt'];
      }
  }
  $distTotal = array_sum($distCounts);
  $distPcts = array_map(fn(int $c) => $distTotal > 0 ? $c / $distTotal * 100 : 0, $distCounts);

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
  $bestEventIdx = null;
  $bestAvgRank = PHP_FLOAT_MAX;
  foreach (($eventStats ?? []) as $i => $row) {
      $label = EventType::tryFrom($row['event_type'])?->label() ?? $row['event_type'];
      $avgRank = (float) $row['avg_rank'];
      $eventData[] = [
          'label' => $label,
          'avg_rank' => $avgRank,
          'games' => (int) $row['games'],
          'top_rate' => (int) $row['games'] > 0 ? (int) $row['tops'] / (int) $row['games'] * 100 : 0,
      ];
      if ($avgRank < $bestAvgRank) {
          $bestAvgRank = $avgRank;
          $bestEventIdx = $i;
      }
  }
  $highlightBestEvent = count($eventData) >= 2;
  ?>

  <?php if ($hasRankDist && $distTotal > 0):
    $distAriaLabel = implode(' / ', array_map(
        fn(int $rank) => $rank . '位 ' . round($distPcts[$rank - 1]) . '%',
        [1, 2, 3, 4]
    ));
  ?>
  <div class="rank-share-section">
    <div class="analysis-section-title">着順シェア</div>
    <div class="rank-share-card">
      <div class="rank-share-bar" role="img" aria-label="卓内着順の内訳: <?= h($distAriaLabel) ?>">
        <?php foreach ([1, 2, 3, 4] as $rank):
          $pct = $distPcts[$rank - 1];
          if ($pct <= 0) continue;
          $cnt = $distCounts[$rank - 1];
        ?>
        <div class="rank-share-segment rank-<?= $rank ?>" style="width: <?= number_format($pct, 2, '.', '') ?>%" title="<?= $rank ?>位 <?= $cnt ?>回（<?= round($pct) ?>%）">
          <span class="rank-share-segment-label"><?= $rank ?>位 <?= round($pct) ?>%</span>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="rank-share-legend">
        <?php foreach ([1, 2, 3, 4] as $rank):
          $cnt = $distCounts[$rank - 1];
        ?>
        <div class="rank-share-legend-item">
          <span class="rank-share-swatch rank-<?= $rank ?>" aria-hidden="true"></span>
          <span class="rank-share-legend-label"><?= $rank ?>位</span>
          <span class="rank-share-legend-value"><?= $cnt ?>回</span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($hasEventStats): ?>
  <div class="event-stats-section">
    <div class="analysis-section-title">イベント種別別の成績</div>
    <div class="event-type-cards">
      <?php foreach ($eventData as $i => $d):
        $isBest = $highlightBestEvent && $i === $bestEventIdx;
      ?>
      <div class="event-type-card<?= $isBest ? ' is-best' : '' ?>">
        <?php if ($isBest): ?><span class="event-type-card-badge" aria-label="最も良い種別">BEST</span><?php endif; ?>
        <div class="event-type-card-name"><?= h($d['label']) ?></div>
        <div class="event-type-card-metrics">
          <div class="event-type-metric">
            <span class="event-type-metric-value"><?= number_format($d['avg_rank'], 2) ?></span>
            <span class="event-type-metric-label">平均着順</span>
          </div>
          <div class="event-type-metric">
            <span class="event-type-metric-value"><?= round($d['top_rate']) ?>%</span>
            <span class="event-type-metric-label">トップ率</span>
          </div>
          <div class="event-type-metric">
            <span class="event-type-metric-value"><?= $d['games'] ?></span>
            <span class="event-type-metric-label">卓</span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($hasRoundPerf): ?>
  <div class="round-perf-section">
    <div class="analysis-section-title">回戦別パフォーマンス</div>
    <div class="chart-card">
      <div class="chart-canvas-wrap"><canvas id="roundPerfChart" aria-label="回戦別平均スコア" role="img"></canvas></div>
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
    <?php if ($hasTimeline): ?>
    <div class="history-chart-card">
      <div class="history-chart-subtitle">累計スコア推移</div>
      <div class="chart-canvas-wrap"><canvas id="cumulativeChart" aria-label="累計スコア推移" role="img"></canvas></div>
    </div>
    <?php endif; ?>
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
        $aboveCutoff = $sh['is_advanced'] === true || $sh['is_advanced'] === 't' || $sh['is_advanced'] === '1';
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
    'cumulative' => $hasTimeline ?? false ? $cumulative : null,
    'roundPerf' => $hasRoundPerf ?? false ? $roundData : null,
];
$chartDataJson = json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

$pageInlineScript = 'window.__playerAnalysisData = ' . $chartDataJson . ";\n" . <<<'JS'
(function() {
  var filterForm = document.getElementById('event-filter-form');
  if (filterForm) {
    var submitBtn = filterForm.querySelector('.event-filter-submit');
    var submitCount = filterForm.querySelector('.event-filter-submit-count');
    var clearEl = filterForm.querySelector('.event-filter-clear');
    var checkboxes = filterForm.querySelectorAll('input[name="event_types[]"]');
    var initial = {};
    checkboxes.forEach(function(cb) { initial[cb.value] = cb.checked; });

    function updatePending() {
      var pending = 0;
      checkboxes.forEach(function(cb) {
        if (cb.checked !== initial[cb.value]) pending++;
      });
      filterForm.classList.toggle('has-pending', pending > 0);
      if (submitBtn) {
        submitBtn.hidden = pending === 0;
        submitBtn.setAttribute('data-pending-count', String(pending));
      }
      if (submitCount) {
        submitCount.textContent = pending > 0 ? ' (' + pending + ')' : '';
      }
      if (clearEl) clearEl.hidden = pending > 0;
    }

    checkboxes.forEach(function(cb) {
      cb.addEventListener('change', function() {
        var chip = cb.closest('.event-chip');
        if (chip) chip.classList.toggle('is-selected', cb.checked);
        updatePending();
      });
    });
    updatePending();
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
    var accentRgb = cssVar('--accent-rgb') || '155,140,232';
    var purpleColor = cssVar('--purple') || '#9b8ce8';
    var coralColor = cssVar('--coral') || '#e8907c';
    var blueColor = cssVar('--blue') || '#7ca8e8';
    var gridColor = 'rgba(128, 128, 128, 0.15)';
    Chart.defaults.color = subColor;
    Chart.defaults.font.family = "'Inter', 'Noto Sans JP', sans-serif";

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
            borderColor: purpleColor,
            backgroundColor: 'rgba(' + accentRgb + ', 0.12)',
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

    // 回戦別パフォーマンス（平均スコアの線、点サイズ=試合数、0pt基準線強調）
    var rpEl = document.getElementById('roundPerfChart');
    if (rpEl && data.roundPerf) {
      var rpLabels = data.roundPerf.map(function(d) { return d.round + '回戦'; });
      var rpAvg = data.roundPerf.map(function(d) { return d.avg; });
      var rpGames = data.roundPerf.map(function(d) { return d.games; });
      var maxGames = rpGames.reduce(function(m, g) { return g > m ? g : m; }, 0);
      var pointRadii = rpGames.map(function(g) {
        if (maxGames <= 0) return 4;
        return 4 + (g / maxGames) * 10;
      });
      var pointColors = rpAvg.map(function(v) {
        return v >= 0 ? coralColor : blueColor;
      });
      new Chart(rpEl, {
        type: 'line',
        data: {
          labels: rpLabels,
          datasets: [{
            label: '平均スコア',
            data: rpAvg,
            borderColor: purpleColor,
            backgroundColor: 'rgba(' + accentRgb + ', 0.12)',
            borderWidth: 2.5,
            pointRadius: pointRadii,
            pointHoverRadius: pointRadii.map(function(r) { return r + 2; }),
            pointBackgroundColor: pointColors,
            pointBorderColor: pointColors,
            pointBorderWidth: 0,
            tension: 0.3,
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
                label: function(ctx) {
                  var games = rpGames[ctx.dataIndex];
                  var sign = ctx.parsed.y >= 0 ? '+' : '';
                  return '平均 ' + sign + ctx.parsed.y.toFixed(1) + 'pt（' + games + '試合）';
                }
              }
            }
          },
          scales: {
            x: { grid: { color: gridColor } },
            y: {
              title: { display: true, text: '平均スコア (pt)', color: subColor },
              grid: {
                color: function(ctx) {
                  return ctx.tick && ctx.tick.value === 0 ? 'rgba(' + accentRgb + ', 0.5)' : gridColor;
                },
                lineWidth: function(ctx) {
                  return ctx.tick && ctx.tick.value === 0 ? 2 : 1;
                }
              }
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
