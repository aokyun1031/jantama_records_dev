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
$pageStyle = <<<'CSS'
.analysis-hero {
  text-align: center;
  padding: 48px 20px 32px;
}

.analysis-badge {
  display: inline-block;
  background: var(--badge-bg);
  color: var(--badge-color);
  font-size: 0.7rem;
  font-weight: 700;
  padding: 4px 14px;
  border-radius: 20px;
  margin-bottom: 20px;
  letter-spacing: 2px;
  box-shadow: 0 2px 12px rgba(var(--accent-rgb),0.3);
  animation: fadeDown 0.8s ease both;
}

.analysis-identity {
  margin-bottom: 12px;
  animation: fadeUp 1s ease both;
}

.analysis-hero-icon {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  object-fit: cover;
  box-shadow: 0 4px 20px rgba(0,0,0,0.15);
  margin-bottom: 16px;
}

.analysis-title {
  font-family: 'Noto Sans JP', sans-serif;
  font-size: clamp(1.8rem, 6vw, 2.5rem);
  font-weight: 900;
  background: var(--title-gradient);
  background-size: 300% 300%;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  animation: titleGrad 6s ease infinite;
  margin-bottom: 4px;
}

.analysis-nickname {
  font-size: 0.85rem;
  color: var(--text-sub);
}

/* --- サマリーカード --- */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 10px;
  margin-bottom: 32px;
  opacity: 0;
  animation: sectionIn 0.6s ease 0.2s forwards;
}

.summary-card {
  background: var(--card);
  backdrop-filter: blur(12px);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-sm);
  padding: 16px;
  text-align: center;
  box-shadow: var(--shadow-sm);
}

.summary-label {
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--text-sub);
  letter-spacing: 1px;
  margin-bottom: 6px;
}

.summary-value {
  font-family: 'Inter', sans-serif;
  font-weight: 800;
  font-size: 1.4rem;
  background: var(--btn-primary-bg);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.summary-sub {
  font-size: 0.7rem;
  color: var(--text-light);
  margin-top: 2px;
}

/* --- セクション --- */
.analysis-section-title {
  font-weight: 800;
  font-size: 0.85rem;
  color: var(--text);
  margin-bottom: 12px;
  padding-left: 12px;
  border-left: 3px solid var(--purple);
}

/* --- 対戦成績テーブル --- */
.h2h-section {
  margin-bottom: 32px;
  opacity: 0;
  animation: sectionIn 0.6s ease 0.4s forwards;
}

.h2h-table {
  width: 100%;
  border-collapse: collapse;
  background: var(--card);
  backdrop-filter: blur(12px);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-sm);
  overflow: hidden;
  box-shadow: var(--shadow-sm);
}

.h2h-table th {
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--text-sub);
  letter-spacing: 1px;
  padding: 10px 16px;
  background: linear-gradient(135deg, rgba(var(--accent-rgb),0.08), rgba(var(--accent-rgb),0.05));
}

.h2h-table td {
  padding: 12px 16px;
  border-top: 1px solid rgba(var(--accent-rgb),0.06);
  font-size: 0.85rem;
}

/* 対戦相手: テキスト → 左寄せ */
.h2h-table th:nth-child(1),
.h2h-table td:nth-child(1) {
  text-align: left;
}

/* 戦績: カテゴリ → 中央寄せ */
.h2h-table th:nth-child(2),
.h2h-table td:nth-child(2) {
  text-align: center;
}

/* 同卓数: 数値 → 右寄せ */
.h2h-table th:nth-child(3),
.h2h-table td:nth-child(3) {
  text-align: right;
}

/* 自分の平均着順: 数値 → 右寄せ */
.h2h-table th:nth-child(4),
.h2h-table td:nth-child(4) {
  text-align: right;
}

/* 相手の平均着順: 数値 → 右寄せ */
.h2h-table th:nth-child(5),
.h2h-table td:nth-child(5) {
  text-align: right;
}

/* 平均スコア差: 数値 → 右寄せ */
.h2h-table th:nth-child(6),
.h2h-table td:nth-child(6) {
  text-align: right;
}

.h2h-name {
  font-weight: 700;
  color: var(--text);
}

.h2h-record {
  font-family: 'Inter', sans-serif;
  font-weight: 700;
}

.h2h-wins { color: var(--coral); }
.h2h-losses { color: var(--blue); }

.h2h-games {
  font-size: 0.8rem;
  color: var(--text-sub);
}

.h2h-rank {
  font-family: 'Inter', sans-serif;
  font-weight: 700;
  font-size: 0.85rem;
  color: var(--text);
}

.h2h-avg {
  font-family: 'Inter', sans-serif;
  font-weight: 700;
  font-size: 0.85rem;
}

.h2h-none td {
  color: var(--text-sub);
  opacity: 0.65;
}

.h2h-sortable {
  cursor: pointer;
  user-select: none;
  transition: color 0.2s;
}

.h2h-sortable:hover {
  color: var(--purple);
}

.sort-icon {
  font-size: 0.6rem;
  opacity: 0.3;
  margin-left: 2px;
}

.sort-icon::after {
  content: '\25B2\25BC';
}

.h2h-sortable.sort-asc .sort-icon {
  opacity: 1;
}

.h2h-sortable.sort-asc .sort-icon::after {
  content: '\25B2';
}

.h2h-sortable.sort-desc .sort-icon {
  opacity: 1;
}

.h2h-sortable.sort-desc .sort-icon::after {
  content: '\25BC';
}

/* --- スコア推移 --- */
.history-section {
  margin-bottom: 32px;
  opacity: 0;
  animation: sectionIn 0.6s ease 0.6s forwards;
}

.history-table {
  width: 100%;
  border-collapse: collapse;
  background: var(--card);
  backdrop-filter: blur(12px);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-sm);
  overflow: hidden;
  box-shadow: var(--shadow-sm);
}

.history-table th {
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--text-sub);
  letter-spacing: 1px;
  padding: 10px 16px;
  background: linear-gradient(135deg, rgba(var(--accent-rgb),0.08), rgba(var(--accent-rgb),0.05));
}

.history-table td {
  padding: 12px 16px;
  border-top: 1px solid rgba(var(--accent-rgb),0.06);
  font-size: 0.85rem;
}

/* 回戦: テキスト → 左寄せ */
.history-table th:nth-child(1),
.history-table td:nth-child(1) {
  text-align: left;
}

/* 日時: 右寄せ */
.history-table th:nth-child(2),
.history-table td:nth-child(2) {
  text-align: right;
}

/* スコア: 数値 → 右寄せ */
.history-table th:nth-child(3),
.history-table td:nth-child(3) {
  text-align: right;
}

/* バー: 中央 */
.history-table th:nth-child(4),
.history-table td:nth-child(4) {
  text-align: center;
  width: 100px;
}

/* 結果: 中央 */
.history-table th:nth-child(5),
.history-table td:nth-child(5) {
  text-align: center;
}

.history-round {
  font-weight: 600;
  color: var(--text);
}

.history-date {
  font-size: 0.8rem;
  color: var(--text-sub);
  white-space: nowrap;
}

.history-score {
  font-family: 'Inter', sans-serif;
  font-weight: 700;
  font-size: 0.9rem;
}

.score-plus { color: var(--coral); }
.score-minus { color: var(--blue); }

.history-bar-wrap {
  width: 100%;
  display: flex;
}

.history-bar {
  height: 6px;
  border-radius: 3px;
  transition: width 0.6s ease;
}

.bar-plus {
  background: linear-gradient(90deg, var(--coral), var(--pink));
}

.bar-minus {
  background: linear-gradient(90deg, var(--blue), var(--lavender));
}

.history-tag {
  font-size: 0.65rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
  display: inline-block;
  white-space: nowrap;
}

.tag-pass {
  background: linear-gradient(135deg, rgba(var(--mint-rgb),0.12), rgba(var(--mint-rgb),0.05));
  color: var(--mint);
}

.tag-fail {
  background: linear-gradient(135deg, rgba(var(--coral-rgb),0.12), rgba(var(--coral-rgb),0.05));
  color: var(--coral);
}

/* --- 共通 --- */
.analysis-error {
  text-align: center;
  padding: 24px;
  background: linear-gradient(135deg, rgba(var(--coral-rgb),0.1), rgba(var(--coral-rgb),0.05));
  border: 1px solid rgba(var(--coral-rgb),0.3);
  border-radius: var(--radius);
  color: var(--text);
  font-size: 0.9rem;
  margin-bottom: 24px;
}

.back-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  background: var(--btn-primary-bg);
  color: var(--btn-text-color);
  text-decoration: none;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.85rem;
  transition: transform 0.3s, box-shadow 0.3s;
  box-shadow: 0 4px 16px rgba(var(--accent-rgb), 0.3);
  margin-bottom: 40px;
}

.back-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 24px rgba(var(--accent-rgb), 0.4);
}

@keyframes sectionIn {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}

@media (max-width: 480px) {
  .summary-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .h2h-table th,
  .h2h-table td {
    padding: 10px 10px;
    font-size: 0.78rem;
  }
}
CSS;

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
