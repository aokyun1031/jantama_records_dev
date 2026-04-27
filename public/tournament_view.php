<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

// --- バリデーション ---
$tournamentId = requireTournamentId();
['data' => $tournamentData] = fetchData(fn() => Tournament::findWithMeta($tournamentId));
if (!$tournamentData || $tournamentData['status'] === TournamentStatus::Preparing->value) {
    abort404();
}
$meta = $tournamentData['meta'];
$tournamentName = $tournamentData['name'];
$isCompleted = $tournamentData['status'] === TournamentStatus::Completed->value;

// --- データ取得 ---
['data' => $finalists] = fetchData(fn() => Standing::finalists($tournamentId));
['data' => $allStandings] = fetchData(fn() => Standing::all($tournamentId));
['data' => $roundsData] = fetchData(fn() => TableInfo::byTournament($tournamentId));
['data' => $interviews] = fetchData(fn() => Interview::byTournament($tournamentId));
['data' => $tournamentRecords] = fetchData(fn() => TournamentRecords::all($tournamentId));

$champion = Standing::champion($tournamentId);

$playerMode = (int) ($meta['player_mode'] ?? 4);

// ラウンド数を動的に決定
$roundNumbers = !empty($roundsData) ? array_keys($roundsData) : [];
$totalRounds = count($roundNumbers);
$lastRound = $totalRounds > 0 ? max($roundNumbers) : 0;

// ラウンド設定を取得
$roundSettings = [];
foreach ($roundNumbers as $rn) {
    $rk = 'round_' . $rn;
    $roundSettings[$rn] = [
        'is_final' => ($meta[$rk . '_is_final'] ?? '0') === '1',
        'advance_count' => (int) ($meta[$rk . '_advance_count'] ?? 0),
        'advance_mode' => $meta[$rk . '_advance_mode'] ?? 'per_table',
        'game_count' => (int) ($meta[$rk . '_game_count'] ?? 0),
        'game_type' => $meta[$rk . '_game_type'] ?? '',
    ];
}

// 各ラウンドの参加人数を計算
$roundPlayerCounts = [];
foreach ($roundNumbers as $rn) {
    $playerSet = [];
    foreach ($roundsData[$rn] as $t) {
        foreach ($t['players'] as $p) {
            $playerSet[$p['name']] = true;
        }
    }
    $roundPlayerCounts[$rn] = count($playerSet);
}

// standings の eliminated_round をベースに勝ち抜け/敗退を判定
$eliminatedMap = [];
foreach ($allStandings ?? [] as $s) {
    $eliminatedMap[$s['name']] = (int) $s['eliminated_round'];
}

// ラウンドごとの勝ち抜け/敗退リストと、ラウンド別スコアを構築
$roundScores = [];
$roundAbove = [];
$roundBelow = [];

foreach ($roundNumbers as $r) {
    ['data' => $results] = fetchData(fn() => RoundResult::byRound($tournamentId, $r));

    foreach ($results ?? [] as $res) {
        $roundScores[$res['name']][] = (float) $res['score'];
    }

    $above = [];
    $below = [];
    foreach ($results ?? [] as $res) {
        $displayName = $res['nickname'] ?? $res['name'];
        $icon = $res['character_icon'] ?? '';
        $entry = ['name' => $displayName, 'score' => (float) $res['score'], 'icon' => $icon];
        $elimRound = $eliminatedMap[$res['name']] ?? 0;
        if ($elimRound === $r) {
            $below[] = $entry;
        } else {
            $above[] = $entry;
        }
    }
    $roundAbove[$r] = $above;
    $roundBelow[$r] = $below;
}

// ルールタグ
$ruleTags = buildRuleTags($meta);
$eventType = EventType::tryFrom($meta['event_type'] ?? '');
$eventLabel = $eventType ? $eventType->label() : '';

// スコアフォーマットヘルパー
function fmtScore(float $score): string
{
    return ($score >= 0 ? '+' : '') . number_format($score, 1);
}

function scoreCls(float $score): string
{
    return $score >= 0 ? 'plus' : 'minus';
}

// --- テンプレート変数 ---
$pageTitle = h($tournamentName) . ' - ' . SITE_NAME;
$pageDescription = h($tournamentName) . 'の全対局結果と最終順位を掲載しています。';
$pageOgp = [
    'title' => $tournamentName . ' - ' . SITE_NAME,
    'description' => $tournamentName . 'の全対局結果と最終順位を掲載しています。',
    'url' => SITE_URL . '/tournament_view?id=' . $tournamentId,
];
$pageCss = ['css/finals.css', 'css/mahjong-deco.css', 'css/champion.css', 'css/tournament-view.css'];
$pageScripts = ['js/effects.js'];

$pageInlineScript = <<<'JS'
(function(){
  function switchTab(idx){
    var btns=document.querySelectorAll('.tab-btn');
    var tabs=document.querySelectorAll('.tab-content');
    for(var i=0;i<btns.length;i++){
      btns[i].classList.toggle('active',i===idx);
      tabs[i].classList.toggle('active',i===idx);
    }
  }
  document.querySelectorAll('.tab-btn[data-tab-index]').forEach(function(btn){
    btn.addEventListener('click',function(){
      switchTab(parseInt(btn.dataset.tabIndex,10));
    });
  });
})();
JS;

require __DIR__ . '/../templates/header.php';

// 決勝のファイナリスト
$seeds = ['1ST', '2ND', '3RD', '4TH'];
$finalRound = null;
foreach ($roundSettings as $rn => $rs) {
    if ($rs['is_final']) {
        $finalRound = $rn;
        break;
    }
}
?>

<!-- Hero -->
<section class="hero">
  <div class="hero-badge"><?= $eventLabel ? h($eventLabel) : '麻雀トーナメント' ?></div>
  <h1 class="hero-title"><?= h($tournamentName) ?></h1>
  <?php if (!$isCompleted): ?>
    <div style="margin-bottom: 8px;"><span style="display:inline-block;font-size:0.75rem;font-weight:700;padding:4px 14px;border-radius:12px;background:rgba(var(--mint-rgb),0.15);color:var(--success);letter-spacing:1px;"><?= h(TournamentStatus::InProgress->label()) ?></span></div>
  <?php endif; ?>
  <div class="hero-rules">
    <?php foreach ($ruleTags as $tag): ?>
      <span><?= h($tag) ?></span>
    <?php endforeach; ?>
  </div>

  <?php if ($isCompleted && $champion): ?>
    <!-- Champion Celebration -->
    <section class="champion-section reveal">
      <div class="champion-container">
        <div class="champion-glow"></div>
        <div class="champion-header">
          <div class="champion-pretitle">&#x1F3C6; CHAMPION &#x1F3C6;</div>
          <h2 class="champion-title">優勝おめでとう！</h2>
          <div class="champion-subtitle"><?= h($tournamentName) ?> 優勝者</div>
        </div>
        <div class="champion-content">
          <div class="champion-avatar">
            <?php if (!empty($champion['character_icon'])): ?>
              <img src="img/chara_deformed/<?= h($champion['character_icon']) ?>" alt="優勝者 <?= h($champion['nickname'] ?? $champion['name']) ?>" class="champion-image" width="200" height="200" loading="lazy">
            <?php endif; ?>
            <div class="champion-crown">&#x1F451;</div>
          </div>
          <div class="champion-info">
            <div class="champion-name"><?= h($champion['nickname'] ?? $champion['name']) ?></div>
            <div class="champion-score">総得点: <?= (float) $champion['total'] >= 0 ? '+' : '' ?><?= h((string) $champion['total']) ?></div>
            <div class="champion-message">見事な戦いぶりで優勝！<br>おめでとうございます！</div>
          </div>
        </div>
        <?php if (!empty($interviews)): ?>
          <div class="champion-interview-link">
            <a href="interview?id=<?= $tournamentId ?>" class="interview-link-btn">&#x1F3A4; 優勝インタビューを読む</a>
          </div>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

</section>

<?php if ($totalRounds > 0): ?>
<!-- Divider -->
<div class="tile-divider">
  <div class="tile-divider-line"></div>
  <div class="tile-divider-tiles">&#x1F000;&#x1F001;&#x1F002;&#x1F003;</div>
  <div class="tile-divider-line"></div>
</div>
<?php endif; ?>

<?php if ($totalRounds === 0 && !$isCompleted): ?>
  <div style="text-align:center;padding:40px 20px;color:var(--text-sub);font-size:0.9rem;">大会はまだ開始されていません。</div>
<?php endif; ?>

<!-- Progress Tracker -->
<?php if ($totalRounds > 0): ?>
<section class="progress-section">
  <div class="progress-track">
    <?php foreach ($roundNumbers as $i => $rn):
      $rSettings = $roundSettings[$rn] ?? [];
      $allDone = !empty($roundsData[$rn]) && empty(array_filter($roundsData[$rn], fn($t) => !$t['done']));
      $prevDone = $i > 0 && !empty($roundsData[$roundNumbers[$i - 1]]) && empty(array_filter($roundsData[$roundNumbers[$i - 1]], fn($t) => !$t['done']));
      $label = $rSettings['is_final'] ? '決勝' : $rn . '回戦';
      $count = $roundPlayerCounts[$rn] ?? 0;
      $isCurrentRound = !$allDone && ($i === 0 || $prevDone);
    ?>
      <?php if ($i > 0): ?><div class="step-line <?= $prevDone ? 'done' : '' ?>"></div><?php endif; ?>
      <div class="progress-step">
        <div class="step-circle <?= $allDone ? 'done' : ($isCurrentRound ? 'current' : '') ?>"><?= $allDone ? '&#10003;' : $rn ?></div>
        <div class="step-label"><?= h($label) ?></div>
        <div class="step-count"><?= $count ?>名</div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($finalRound && !empty($finalists)): ?>
<!-- Finals Showdown -->
<section class="finals-section reveal" id="finals-section">
  <div class="finals-stage">
    <div class="finals-header">
      <h2 class="finals-title">決勝卓</h2>
      <div class="finals-subtitle">予選を勝ち抜いた<span><?= count($finalists) ?>名</span>が最強位の座を賭けて激突</div>
    </div>
    <div class="finals-grid" id="finals-grid">
      <?php foreach ($finalists as $i => $f): ?>
        <div class="finalist-card" data-delay="<?= 1.2 + $i * 0.3 ?>">
          <?php if (!empty($f['character_icon'])): ?>
            <img src="img/chara_deformed/<?= h($f['character_icon']) ?>" alt="" width="56" height="56" style="border-radius:50%;" loading="lazy">
          <?php endif; ?>
          <div class="finalist-name"><?= h($f['nickname'] ?? $f['name']) ?></div>
          <div class="finalist-score <?= $f['total'] >= 0 ? 'plus' : 'minus' ?>"><?= $f['total'] >= 0 ? '+' : '' ?><?= h((string) $f['total']) ?></div>
          <div class="finalist-trend"><?= h($f['trend']) ?></div>
        </div>
      <?php endforeach; ?>
      <div class="finals-vs">VS</div>
    </div>
  </div>
</section>

<div class="tile-divider">
  <div class="tile-divider-line"></div>
  <div class="tile-divider-tiles">&#x1F010;&#x1F011;&#x1F012;&#x1F013;</div>
  <div class="tile-divider-line"></div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../templates/partials/tournament_view/round_details.php'; ?>

<?php
  $hasAnyResults = false;
  foreach ($roundAbove as $above) { if (!empty($above)) { $hasAnyResults = true; break; } }
?>
<?php if ($hasAnyResults): ?>
<div class="tile-divider">
  <div class="tile-divider-line"></div>
  <div class="tile-divider-tiles">&#x1F019;&#x1F01A;&#x1F01B;&#x1F01C;</div>
  <div class="tile-divider-line"></div>
</div>

<!-- Cumulative Standings -->
<section class="section reveal" id="standings-section">
  <div class="section-header">
    <div class="section-title">総合ポイント</div>
  </div>
  <div id="standings">
    <?php
      $maxBar = 130;
      $shownDivider = false;
      foreach ($allStandings ?? [] as $i => $s):
        $displayName = $s['nickname'] ?? $s['name'];
        $total = (float) $s['total'];
        $elim = (int) $s['eliminated_round'];
        $rank = (int) $s['rank'];
        $pending = (bool) $s['pending'];
        $icon = $s['character_icon'] ?? '';
        $scores = $roundScores[$s['name']] ?? [];
        $barW = min(abs($total) / $maxBar * 100, 100);

        $detail = implode(' → ', array_map(fn($v) => ($v >= 0 ? '+' : '') . number_format($v, 1), $scores));
        if ($elim > 0) $detail .= ' → ' . $elim . '回戦敗退';

        $topCls = $i < 3 ? ' top-' . ($i + 1) : '';
        $elimCls = $elim > 0 ? ' eliminated' : '';
    ?>
      <?php if ($elim === 0 && $total < 0 && !$shownDivider): $shownDivider = true; ?>
        <div class="standing-divider">&plusmn; 0</div>
      <?php endif; ?>
      <a href="player?id=<?= (int) $s['player_id'] ?>&amp;from=tournament_view&amp;tournament_id=<?= $tournamentId ?>" class="standing-item<?= $topCls . $elimCls ?>" data-delay="<?= $i * 0.08 ?>" data-bar="<?= $barW ?>">
        <div class="standing-bar <?= scoreCls($total) ?>"></div>
        <div class="standing-rank"><?php if ($i < 3): ?><span class="medal"><?= $medals[$i] ?></span><?php else: ?><?= $i + 1 ?><?php endif; ?></div>
        <div class="standing-info">
          <div class="standing-name">
            <?= charaIcon($icon ?: null, 28) ?>
            <?= h($displayName) ?>
            <?php if ($pending): ?>
              <span class="badge-pending">未対戦</span>
            <?php elseif ($elim > 0): ?>
              <span class="badge-elim"><?= $elim ?>回戦敗退</span>
            <?php endif; ?>
          </div>
          <div class="standing-detail"><?= h($detail) ?></div>
        </div>
        <div class="standing-score <?= scoreCls($total) ?>" data-target="<?= $total ?>"><?= fmtScore($total) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php
  $recHigh = $tournamentRecords['highest_score'] ?? null;
  $recTops = $tournamentRecords['most_tops'] ?? null;
  if ($recHigh || $recTops):
    $startPt = (int) ($meta['starting_points'] ?? 25000);
    $retPt = (int) ($meta['return_points'] ?? 30000);
    $pMode = (int) ($meta['player_mode'] ?? 4);
    $umaTop = $pMode === 3 ? 15 : 20;
    $oka = ($retPt - $startPt) * $pMode / 1000;
    $topRawScore = null;
    if ($recHigh) {
        $topPoint = (float) $recHigh['score'];
        $topRawScore = (int) round(($topPoint - $umaTop - $oka) * 1000 + $retPt);
    }
    $playerLink = fn(int $pid) => 'player?id=' . $pid . '&amp;from=tournament_view&amp;tournament_id=' . $tournamentId;
?>
<div class="tile-divider">
  <div class="tile-divider-line"></div>
  <div class="tile-divider-tiles">&#x1F006;&#x1F004;&#x1F005;&#x1F006;</div>
  <div class="tile-divider-line"></div>
</div>

<!-- Records -->
<section class="section reveal">
  <div class="section-header">
    <div class="section-title">トーナメントレコード</div>
  </div>
  <div class="record-list">
    <?php if ($recHigh && $topRawScore !== null):
      $highPid = (int) $recHigh['player_id'];
    ?>
      <a href="<?= $playerLink($highPid) ?>" class="record-card">
        <div class="record-card-info">
          <span class="record-card-label">大会最高得点</span>
          <span class="record-card-player"><?= charaIcon($recHigh['character_icon'] ?? null, 22) ?><?= h($recHigh['player_name']) ?></span>
        </div>
        <div class="record-card-value">
          <span class="record-card-num" data-count="<?= $topRawScore ?>">0</span>
        </div>
      </a>
      <a href="<?= $playerLink($highPid) ?>" class="record-card">
        <div class="record-card-info">
          <span class="record-card-label">大会最高ポイント</span>
          <span class="record-card-player"><?= charaIcon($recHigh['character_icon'] ?? null, 22) ?><?= h($recHigh['player_name']) ?></span>
        </div>
        <div class="record-card-value">
          <span class="record-card-num"><?= fmtScore((float) $recHigh['score']) ?></span>
        </div>
      </a>
    <?php endif; ?>
    <?php if ($recTops):
      $winners = $recTops['winners'];
      $topCount = (int) $recTops['top_count'];
      $singleWinner = count($winners) === 1;
    ?>
      <<?= $singleWinner ? 'a href="' . $playerLink((int) $winners[0]['player_id']) . '"' : 'div' ?> class="record-card">
        <div class="record-card-info">
          <span class="record-card-label">最多トップ</span>
          <span class="record-card-player">
            <?php foreach ($winners as $k => $w): ?>
              <?php if ($k > 0): ?><span class="record-card-player-sep">・</span><?php endif; ?>
              <?php if ($singleWinner): ?>
                <?= charaIcon($w['character_icon'] ?? null, 22) ?><?= h($w['player_name']) ?>
              <?php else: ?>
                <a href="<?= $playerLink((int) $w['player_id']) ?>" class="record-card-player-link"><?= charaIcon($w['character_icon'] ?? null, 22) ?><?= h($w['player_name']) ?></a>
              <?php endif; ?>
            <?php endforeach; ?>
          </span>
        </div>
        <div class="record-card-value">
          <span class="record-card-num" data-count="<?= $topCount ?>">0</span>
          <span class="record-card-unit">回</span>
        </div>
      </<?= $singleWinner ? 'a' : 'div' ?>>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<div style="text-align:center;padding:32px 0 16px;">
  <a href="tournaments" class="btn-cancel">&#x2190; 大会一覧に戻る</a>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
