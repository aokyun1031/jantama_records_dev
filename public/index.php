<?php
declare(strict_types=1);
require __DIR__ . '/../config/database.php';

$tournamentId = 1;
['data' => $finalists] = fetchData(fn() => Standing::finalists($tournamentId));
['data' => $meta] = fetchData(fn() => TournamentMeta::all($tournamentId));
['data' => $allStandings] = fetchData(fn() => Standing::all($tournamentId));
$champion = $finalists[0] ?? null;
$seeds = ['1ST', '2ND', '3RD', '4TH'];

// JS用データ構築
$roundScores = [];
$roundTables = [];
$roundAbove = [];
$roundBelow = [];
for ($r = 1; $r <= 4; $r++) {
    ['data' => $results] = fetchData(fn() => RoundResult::byRound($tournamentId, $r));
    ['data' => $tables] = fetchData(fn() => TableInfo::byRound($tournamentId, $r));

    foreach ($results ?? [] as $res) {
        $roundScores[$res['name']][] = (float)$res['score'];
    }

    $above = [];
    $below = [];
    foreach ($results ?? [] as $res) {
        $entry = [$res['name'], (float)$res['score']];
        if ($res['is_above_cutoff']) {
            $above[] = $entry;
        } else {
            $below[] = $entry;
        }
    }
    $roundAbove[$r] = $above;
    $roundBelow[$r] = $below;

    $jsTables = [];
    foreach ($tables ?? [] as $t) {
        $jsTable = [
            'name' => $t['table_name'],
            'sched' => $t['schedule'],
            'players' => array_map(fn($p) => $p['name'], $t['players']),
        ];
        if ($r >= 3) {
            $jsTable['done'] = (bool)$t['done'];
        }
        $jsTables[] = $jsTable;
    }
    $roundTables[$r] = $jsTables;
}

$jsStandings = [];
foreach ($allStandings ?? [] as $s) {
    $jsStandings[] = [
        'rank' => (int)$s['rank'],
        'name' => $s['name'],
        'total' => (float)$s['total'],
        'r' => $roundScores[$s['name']] ?? [],
        'pending' => (bool)$s['pending'],
        'elim' => (int)$s['eliminated_round'],
    ];
}
$pageTitle = '最強位戦 - 麻雀トーナメント';
$pageDescription = '2026年 麻雀トーナメント「最強位戦」の全対局結果と最終順位を掲載しています。';
$pageOgp = [
    'title' => '最強位戦 - 麻雀トーナメント',
    'description' => '2026年 麻雀トーナメント「最強位戦」の全対局結果と最終順位を掲載しています。',
    'url' => 'https://jantama-records.onrender.com/',
];
$pageCss = ['css/finals.css', 'css/mahjong-deco.css', 'css/champion.css'];
$pageScripts = ['js/render.js', 'js/effects.js'];

ob_start();
?>
var MAX_BAR=130;
var MEDALS=['\u{1F947}','\u{1F948}','\u{1F949}'];
var standings=<?= json_encode($jsStandings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
var r1Tables=<?= json_encode($roundTables[1] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
var r1Above=<?= json_encode($roundAbove[1] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
var r1Below=<?= json_encode($roundBelow[1] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
var r2Tables=<?= json_encode($roundTables[2] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
var r2Above=<?= json_encode($roundAbove[2] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
var r2Below=<?= json_encode($roundBelow[2] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
var r3Tables=<?= json_encode($roundTables[3] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
var r3Above=<?= json_encode($roundAbove[3] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
var r3Below=<?= json_encode($roundBelow[3] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
var r4Tables=<?= json_encode($roundTables[4] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
var r4Above=<?= json_encode($roundAbove[4] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
var r4Below=<?= json_encode($roundBelow[4] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
<?php
$pageInlineScript = ob_get_clean();

require __DIR__ . '/../templates/header.php';
?>

<!-- Floating Tile Scatter (page-level) -->
<div class="tile-scatter" id="tile-scatter"></div>

<!-- Hero -->
<section class="hero">
  <!-- <div class="hero-tiles">&#x1F000;&#x1F001;&#x1F002;&#x1F003;&#x1F004;&#x1F005;&#x1F006;&#x1F007;&#x1F008;&#x1F009;&#x1F00A;&#x1F00B;&#x1F00C;&#x1F00D;&#x1F00E;&#x1F00F;&#x1F010;&#x1F011;&#x1F012;&#x1F013;&#x1F014;&#x1F015;&#x1F016;&#x1F017;&#x1F018;&#x1F019;&#x1F01A;&#x1F01B;&#x1F01C;&#x1F01D;&#x1F01E;&#x1F01F;&#x1F020;&#x1F021;&#x1F000;&#x1F001;&#x1F002;&#x1F003;&#x1F004;&#x1F005;&#x1F006;&#x1F007;&#x1F008;&#x1F009;&#x1F00A;&#x1F00B;&#x1F00C;&#x1F00D;</div> -->
  <span class="hero-tile-accent top-left">&#x1F004;</span>
  <span class="hero-tile-accent top-right">&#x1F005;</span>
  <span class="hero-tile-accent bot-left">&#x1F000;</span>
  <span class="hero-tile-accent bot-right">&#x1F006;</span>
  <div class="hero-badge">麻雀トーナメント</div>
  <h1 class="hero-title">最強位戦</h1>
  <div class="hero-rules">
    <span>赤4</span><span>60秒</span><span>トビ無</span>
  </div>

  <!-- Champion Celebration -->
  <section class="champion-section reveal">
    <div class="champion-container">
      <div class="champion-glow"></div>
      <!-- <div class="champion-tiles-bg">&#x1F000;&#x1F001;&#x1F002;&#x1F003;&#x1F004;&#x1F005;&#x1F006;&#x1F007;&#x1F008;&#x1F009;&#x1F00A;&#x1F00B;&#x1F00C;&#x1F00D;&#x1F00E;&#x1F00F;&#x1F010;&#x1F011;&#x1F012;&#x1F013;&#x1F014;&#x1F015;&#x1F016;&#x1F017;&#x1F018;&#x1F019;&#x1F01A;&#x1F01B;&#x1F01C;&#x1F01D;&#x1F01E;&#x1F01F;&#x1F020;&#x1F021;&#x1F000;&#x1F001;&#x1F002;&#x1F003;&#x1F004;&#x1F005;&#x1F006;&#x1F007;&#x1F008;&#x1F009;&#x1F00A;&#x1F00B;&#x1F00C;&#x1F00D;</div> -->
      <div class="champion-header">
        <div class="champion-pretitle">🏆 CHAMPION 🏆</div>
        <h2 class="champion-title">優勝おめでとう！</h2>
        <div class="champion-subtitle">最強位戦 優勝者</div>
      </div>
      <div class="champion-content">
        <div class="champion-avatar">
          <img src="img/chara_deformed/<?= $champion && $champion['character_icon'] ? h($champion['character_icon']) : '' ?>" alt="優勝者 <?= $champion ? h($champion['name']) : '' ?>" class="champion-image" width="200" height="200" loading="lazy">
          <div class="champion-crown">👑</div>
        </div>
        <div class="champion-info">
          <div class="champion-name"><?= $champion ? h($champion['name']) : '' ?></div>
          <div class="champion-score">総得点: <?= $champion ? ($champion['total'] >= 0 ? '+' : '') . h((string)$champion['total']) : '' ?></div>
          <div class="champion-message">見事な戦いぶりで、最強位の座を獲得！<br>おめでとうございます！</div>
        </div>
      </div>
      <div class="champion-footer">
        <div class="champion-trophy">🏆</div>
        <div class="champion-text">2026 麻雀トーナメント 最強位戦 優勝</div>
      </div>
      <div class="champion-interview-link">
        <a href="interview" class="interview-link-btn">🎤 優勝インタビューを読む</a>
      </div>
    </div>
  </section>

  <!-- Tournament Ended Message -->
  <!-- <div class="tournament-ended-message">
    <div class="ended-container">
      <div class="ended-icon">🏁</div>
      <div class="ended-text">大会は終了しました</div>
    </div>
  </div> -->

</section>

<!-- Divider -->
<div class="tile-divider">
  <div class="tile-divider-line"></div>
  <div class="tile-divider-tiles">&#x1F000;&#x1F001;&#x1F002;&#x1F003;</div>
  <div class="tile-divider-line"></div>
</div>

<!-- Progress Tracker -->
<section class="progress-section">
  <div class="progress-track">
    <!-- <div class="progress-tile-bg">&#x1F007;&#x1F008;&#x1F009;&#x1F00A;&#x1F00B;</div> -->
    <div class="progress-step">
      <div class="step-circle done">&#10003;</div>
      <div class="step-label">1回戦</div>
      <div class="step-count">20名</div>
    </div>
    <div class="step-line done"></div>
    <div class="progress-step">
      <div class="step-circle done">&#10003;</div>
      <div class="step-label">2回戦</div>
      <div class="step-count">16名</div>
    </div>
    <div class="step-line done"></div>
    <div class="progress-step">
      <div class="step-circle done">&#10003;</div>
      <div class="step-label">3回戦</div>
      <div class="step-count">12名</div>
    </div>
    <div class="step-line done"></div>
    <div class="progress-step">
      <div class="step-circle done">&#10003;</div>
      <div class="step-label">決勝</div>
      <div class="step-count">4名</div>
    </div>
  </div>
</section>

<!-- Finals Showdown -->
<section class="finals-section reveal" id="finals-section">
  <div class="finals-stage">
    <div class="finals-corner tl"></div>
    <div class="finals-corner tr"></div>
    <div class="finals-corner bl"></div>
    <div class="finals-corner br"></div>

    <div class="finals-header">
      <!-- <div class="finals-pretitle">THE FINAL TABLE</div> -->
      <h2 class="finals-title">決勝卓</h2>
      
      <div class="finals-subtitle">予選を勝ち抜いた<span>4名</span>が最強位の座を賭けて激突</div>
      <!-- <div class="finals-sparkle-line"></div> -->
    </div>

    <div class="finals-grid" id="finals-grid">
      <?php if ($finalists): ?>
        <?php foreach ($finalists as $i => $f): ?>
          <div class="finalist-card" data-delay="<?= 1.2 + $i * 0.3 ?>">
            <div class="finalist-seed"><?= $seeds[$i] ?? '' ?> SEED</div>
            <div class="finalist-name"><?= h($f['name']) ?></div>
            <div class="finalist-score <?= $f['total'] >= 0 ? 'plus' : 'minus' ?>"><?= $f['total'] >= 0 ? '+' : '' ?><?= h((string)$f['total']) ?></div>
            <div class="finalist-trend"><?= h($f['trend']) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      <div class="finals-vs">VS</div>
    </div>

  </div>
</section>

<!-- Divider -->
<div class="tile-divider">
  <div class="tile-divider-line"></div>
  <div class="tile-divider-tiles">&#x1F010;&#x1F011;&#x1F012;&#x1F013;</div>
  <div class="tile-divider-line"></div>
</div>

<!-- Round Details -->
<section class="section section--decorated reveal">
  <div class="section-tiles" id="section-tiles-rounds"></div>
  <div class="section-header">
    <div class="section-title">&#x1F004; 対戦結果</div>
  </div>

  <div class="tabs">
    <button class="tab-btn" onclick="switchTab(0)">1回戦<br><small>5卓 20名</small></button>
    <button class="tab-btn" onclick="switchTab(1)">2回戦<br><small>4卓 16名</small></button>
    <button class="tab-btn" onclick="switchTab(2)">3回戦<br><small>3卓 12名</small></button>
    <button class="tab-btn active" onclick="switchTab(3)">決勝<br><small>1卓 4名</small></button>
  </div>

  <div class="tab-content" id="tab0"></div>
  <div class="tab-content" id="tab1"></div>
  <div class="tab-content" id="tab2"></div>
  <div class="tab-content active" id="tab3"></div>
</section>

<!-- Divider -->
<div class="tile-divider">
  <div class="tile-divider-line"></div>
  <div class="tile-divider-tiles">&#x1F019;&#x1F01A;&#x1F01B;&#x1F01C;</div>
  <div class="tile-divider-line"></div>
</div>

<!-- Cumulative Standings -->
<section class="section section--decorated reveal" id="standings-section">
  <div class="section-tiles" id="section-tiles-standings"></div>
  <div class="section-header">
    <div class="section-title">&#x1F005; 総合ポイント</div>
  </div>
  <div id="standings"></div>
  <div style="text-align:center; margin-top:24px;">
    <a href="players" class="players-link-btn">参加者一覧を見る &#x203A;</a>
  </div>
</section>

<!-- Divider -->
<div class="tile-divider">
  <div class="tile-divider-line"></div>
  <div class="tile-divider-tiles">&#x1F006;&#x1F004;&#x1F005;&#x1F006;</div>
  <div class="tile-divider-line"></div>
</div>

<!-- Records -->
<section class="records reveal">
  <div class="records-tile-frame" id="records-tile-frame"></div>
  <div class="records-title">&#x1F000; トーナメントレコード &#x1F000;</div>

  <div class="record-highlight">
    <span class="record-label">大会最高得点</span>
    <span class="record-score" data-count="71200">0</span>
    <span class="record-player"><?= $meta ? h($meta['record_player'] ?? '') : '' ?></span>
  </div>
  <!-- <div class="records-stats">
    <div class="stat"><div class="stat-num" data-count="20">0</div><div class="stat-label">参加者</div></div>
    <div class="stat"><div class="stat-num" data-count="0">0</div><div class="stat-label">残り</div></div>
    <div class="stat"><div class="stat-num" data-count="4">0</div><div class="stat-label">回戦目</div></div>
  </div> -->
</section>

<!-- <div class="footer-tiles">&#x1F007;&#x1F008;&#x1F009;&#x1F00A;&#x1F00B;&#x1F00C;&#x1F00D;&#x1F00E;&#x1F00F;</div> -->

<?php require __DIR__ . '/../templates/footer.php'; ?>