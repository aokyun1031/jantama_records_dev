<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

$isTopPage = true;

// --- データ取得 ---
['data' => $tournaments] = fetchData(fn() => Tournament::allWithDetails());
['data' => $stats] = fetchData(fn() => HallOfFame::siteStats());
['data' => $championCounts] = fetchData(fn() => HallOfFame::championCounts(3));
['data' => $pointLeaders] = fetchData(fn() => HallOfFame::totalPointLeaders(10));
['data' => $highestScore] = fetchData(fn() => HallOfFame::highestRoundScoreAllTime());
['data' => $mostTops] = fetchData(fn() => HallOfFame::mostTopFinishesAllTime());
['data' => $latestByEvent] = fetchData(fn() => HallOfFame::latestByEventType());
['data' => $latestInterview] = fetchData(fn() => HallOfFame::latestInterview());

$tournaments = $tournaments ?? [];
$stats = $stats ?? [
    'total_tournaments' => 0, 'completed_tournaments' => 0, 'total_players' => 0,
    'total_tables' => 0, 'done_tables' => 0, 'total_rounds' => 0,
];
$championCounts = $championCounts ?? [];
$pointLeaders = $pointLeaders ?? [];
$latestByEvent = $latestByEvent ?? [];

$completedTournaments = array_values(array_filter($tournaments, fn($t) => $t['status'] === TournamentStatus::Completed->value));
$activeTournaments = array_values(array_filter($tournaments, fn($t) => $t['status'] === TournamentStatus::InProgress->value));
$preparingTournaments = array_values(array_filter($tournaments, fn($t) => $t['status'] === TournamentStatus::Preparing->value));

$latestCompleted = $completedTournaments[0] ?? null;
$latestChampion = $latestCompleted ? Standing::champion((int) $latestCompleted['id']) : null;

// 進行中大会詳細
$liveCards = [];
foreach ($activeTournaments as $t) {
    $tid = (int) $t['id'];
    ['data' => $standings] = fetchData(fn() => Standing::all($tid));
    ['data' => $currentRound] = fetchData(fn() => HallOfFame::currentRound($tid));
    $alive = array_values(array_filter($standings ?? [], fn($s) => (int) $s['eliminated_round'] === 0));
    $liveCards[] = [
        'tournament' => $t,
        'current_round' => $currentRound,
        'top3' => array_slice($alive, 0, 3),
        'alive_count' => count($alive),
    ];
}

// イベント種別ごとの開催数
$eventCounts = [];
foreach ($tournaments as $t) {
    $et = $t['event_type'] ?? '';
    if ($et === '') {
        continue;
    }
    $eventCounts[$et] = ($eventCounts[$et] ?? 0) + 1;
}

// キャラ画像ファイル一覧（装飾用）
$charaFiles = [];
$charaGlob = glob(__DIR__ . '/img/chara_deformed/*.png') ?: [];
foreach ($charaGlob as $p) {
    $charaFiles[] = basename($p);
}
sort($charaFiles);

// band別キャラ割当（ローテーション）
$charaPick = function (int $offset, int $count) use ($charaFiles): array {
    $n = count($charaFiles);
    if ($n === 0) {
        return [];
    }
    $out = [];
    for ($i = 0; $i < $count; $i++) {
        $out[] = $charaFiles[($offset + $i) % $n];
    }
    return $out;
};
// 各band境界のdivider用キャラ（3体中央配置）
$divHeroStats     = $charaPick(4, 3);
$divStatsLive     = $charaPick(7, 3);
$divLiveChamp     = $charaPick(10, 3);
$divChampVault    = $charaPick(13, 3);
$divVaultSeries   = $charaPick(1, 3);
$divSeriesRoster  = $charaPick(5, 3);
$divRosterArchive = $charaPick(8, 3);
$divArchiveSpot   = $charaPick(11, 3);
$divSpotCta       = $charaPick(14, 3);

// divider出力ヘルパー（animクラスでセクション別アニメ切替）
$dividerHtml = function (array $files, string $animClass = ''): string {
    if (empty($files)) {
        return '';
    }
    $cls = 'lp3-divider' . ($animClass !== '' ? ' ' . $animClass : '');
    $html = '<div class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true">';
    foreach ($files as $i => $f) {
        $slot = $i + 1;
        $html .= '<img src="img/chara_deformed/' . htmlspecialchars($f, ENT_QUOTES, 'UTF-8')
            . '" alt="" class="lp3-divider-char v-' . $slot . '" width="72" height="72" loading="lazy">';
    }
    $html .= '</div>';
    return $html;
};

// --- OGP ---
$ogpDesc = '開催大会 ' . (int) $stats['total_tournaments'] . ' ・ 登録選手 ' . (int) $stats['total_players'] . '名 ・ 総半荘数 ' . (int) $stats['total_rounds'];
if ($latestChampion && $latestCompleted) {
    $champName = $latestChampion['nickname'] ?? $latestChampion['name'];
    $ogpDesc = '最新王者「' . $champName . '」- ' . $latestCompleted['name'] . ' / ' . $ogpDesc;
}

$pageTitle = SITE_NAME . ' - POP ARENA';
$pageDescription = $ogpDesc;
$pageOgp = [
    'title' => SITE_NAME . ' - POP ARENA',
    'description' => $ogpDesc,
    'url' => 'https://jantama-records.onrender.com/landing2',
];

$pageCss = ['css/landing-pop.css'];
$pageScripts = ['js/landing-pop.js'];

// ライト固定（theme-darkを強制disable）
$pageInlineScript = <<<'JS'
(function(){
  var d = document.getElementById('theme-dark');
  if (d) d.disabled = true;
  try { localStorage.setItem('saikyo-theme','light'); } catch(e) {}
})();
JS;

require __DIR__ . '/../templates/header.php';
?>

<?php
// 装飾SVG（リボン・麻雀牌）描画ヘルパー
$svgRibbon = function (string $color, int $w = 80): string {
    return '<svg class="deco-ribbon" width="' . $w . '" height="20" viewBox="0 0 72 20" aria-hidden="true">'
        . '<path d="M0 10 Q12 0 24 10 T48 10 T72 10" stroke="' . $color . '" stroke-width="4" fill="none" stroke-linecap="round"/></svg>';
};
$svgTilePin = function (int $size = 56): string {
    return '<svg class="deco-tile" width="' . $size . '" height="' . (int) ($size * 1.28) . '" viewBox="0 0 56 72" aria-hidden="true">'
        . '<rect x="2" y="2" width="52" height="68" rx="8" fill="#fff" stroke="#ffb8d6" stroke-width="3"/>'
        . '<circle cx="28" cy="22" r="4" fill="#ff6aa6"/><circle cx="18" cy="38" r="4" fill="#ff6aa6"/>'
        . '<circle cx="38" cy="38" r="4" fill="#ff6aa6"/><circle cx="28" cy="54" r="4" fill="#ff6aa6"/></svg>';
};
$svgTileSou = function (int $size = 52): string {
    return '<svg class="deco-tile" width="' . $size . '" height="' . (int) ($size * 1.3) . '" viewBox="0 0 52 68" aria-hidden="true">'
        . '<rect x="2" y="2" width="48" height="64" rx="8" fill="#fff" stroke="#d4b8ff" stroke-width="3"/>'
        . '<path d="M26 16 L34 28 L22 28 Z" fill="#a56cff"/><path d="M26 52 L18 40 L34 40 Z" fill="#a56cff"/></svg>';
};
$svgTileMan = function (int $size = 50): string {
    return '<svg class="deco-tile" width="' . $size . '" height="' . (int) ($size * 1.28) . '" viewBox="0 0 50 64" aria-hidden="true">'
        . '<rect x="2" y="2" width="46" height="60" rx="7" fill="#fff" stroke="#f5c95c" stroke-width="3"/>'
        . '<text x="25" y="42" font-family="Inter" font-weight="900" font-size="34" text-anchor="middle" fill="#f5c95c">發</text></svg>';
};
?>

<div class="lp3">

<!-- ======================== -->
<!-- HERO -->
<!-- ======================== -->
<section class="lp3-band band-hero lp3-hero">
  <div class="lp3-band-deco" aria-hidden="true">
    <div style="position:absolute;bottom:10%;left:46%;"><?= $svgRibbon('#ff6aa6', 90) ?></div>
    <div style="position:absolute;top:44%;right:4%;"><?= $svgTilePin(52) ?></div>
    <div style="position:absolute;top:60%;left:2%;"><?= $svgTileSou(48) ?></div>
  </div>
  <div class="lp3-inner">
    <div class="lp3-hero-badge">&#x1F3AE; POP ARENA</div>
    <h1 class="lp3-hero-title">雀魂部屋主催</h1>
    <p class="lp3-hero-sub">
      雀魂で開催する麻雀トーナメントの<br>
      戦績・対局結果・選手情報を一望できるダッシュボード
    </p>

    <div class="lp3-hero-actions">
      <a href="tournaments" class="lp3-btn lp3-btn-primary lp3-btn-arrow">大会一覧を見る</a>
      <a href="players" class="lp3-btn lp3-btn-secondary">選手一覧を見る</a>
    </div>
  </div>
</section>

<?= $dividerHtml($divHeroStats, 'anim-1') ?>

<!-- ======================== -->
<!-- STATS -->
<!-- ======================== -->
<section class="lp3-band band-stats lp3-reveal">
  <div class="lp3-band-deco" aria-hidden="true">
  </div>
  <div class="lp3-inner">
    <div class="lp3-stats">
    <div class="lp3-stat">
      <div class="lp3-stat-num lp3-count" data-count="<?= (int) $stats['total_tournaments'] ?>"><?= (int) $stats['total_tournaments'] ?></div>
      <div class="lp3-stat-label">Tournaments</div>
    </div>
    <div class="lp3-stat">
      <div class="lp3-stat-num lp3-count" data-count="<?= (int) $stats['completed_tournaments'] ?>"><?= (int) $stats['completed_tournaments'] ?></div>
      <div class="lp3-stat-label">Completed</div>
    </div>
    <div class="lp3-stat">
      <div class="lp3-stat-num lp3-count" data-count="<?= (int) $stats['total_players'] ?>"><?= (int) $stats['total_players'] ?></div>
      <div class="lp3-stat-label">Players</div>
    </div>
    <div class="lp3-stat">
      <div class="lp3-stat-num lp3-count" data-count="<?= (int) $stats['done_tables'] ?>"><?= (int) $stats['done_tables'] ?></div>
      <div class="lp3-stat-label">Tables</div>
    </div>
    <div class="lp3-stat">
      <div class="lp3-stat-num lp3-count" data-count="<?= (int) $stats['total_rounds'] ?>"><?= (int) $stats['total_rounds'] ?></div>
      <div class="lp3-stat-label">Hanchan</div>
    </div>
    <div class="lp3-stat">
      <div class="lp3-stat-num lp3-count" data-count="<?= count($activeTournaments) ?>"><?= count($activeTournaments) ?></div>
      <div class="lp3-stat-label">Live</div>
    </div>
    </div>
  </div>
</section>

<?= $dividerHtml($divStatsLive, 'anim-2') ?>

<!-- ======================== -->
<!-- LIVE -->
<!-- ======================== -->
<section class="lp3-band band-live lp3-reveal">
  <div class="lp3-band-deco" aria-hidden="true">
    <div style="position:absolute;top:40%;right:6%;"><?= $svgTileMan(44) ?></div>
    <div style="position:absolute;bottom:12%;right:30%;"><?= $svgRibbon('#4fb3f0', 80) ?></div>
    <div style="position:absolute;bottom:24%;left:10%;"><?= $svgTileSou(42) ?></div>
  </div>
  <div class="lp3-inner">
    <span class="lp3-eyebrow">Live Now</span>
    <h2 class="lp3-heading">開催中の大会<span class="dot">.</span></h2>
  <?php if (!empty($liveCards)): ?>
    <div class="lp3-live-scroller">
      <?php foreach ($liveCards as $lc):
          $lt = $lc['tournament'];
          $lEvent = EventType::tryFrom($lt['event_type'] ?? '');
      ?>
        <a href="tournament_view?id=<?= (int) $lt['id'] ?>" class="lp3-live-card">
          <span class="lp3-live-pulse">LIVE</span>
          <div class="lp3-live-name"><?= h($lt['name']) ?></div>
          <div class="lp3-live-chips">
            <?php if ($lEvent): ?>
              <span class="lp3-chip is-pink"><?= h($lEvent->label()) ?></span>
            <?php endif; ?>
            <span class="lp3-chip is-mint"><?= (int) $lt['player_count'] ?>名参加</span>
          </div>
          <?php if ($lc['current_round']): ?>
            <div class="lp3-live-progress">
              <div>
                <div class="lp3-live-progress-sub">Current Round</div>
                <div class="lp3-live-progress-round">第<?= (int) $lc['current_round'] ?>回戦</div>
              </div>
              <div class="lp3-live-alive">
                勝ち残り <strong><?= (int) $lc['alive_count'] ?></strong> 名
              </div>
            </div>
          <?php endif; ?>
          <?php if (!empty($lc['top3'])): ?>
            <div class="lp3-live-top">
              <?php foreach ($lc['top3'] as $i => $s): ?>
                <div class="lp3-live-top-row">
                  <span class="lp3-live-top-rank">#<?= $i + 1 ?></span>
                  <?php if (!empty($s['character_icon'])): ?>
                    <img src="img/chara_deformed/<?= h($s['character_icon']) ?>" alt="" class="lp3-live-top-icon" width="32" height="32" loading="lazy">
                  <?php else: ?>
                    <span class="lp3-avatar-ph" style="width:32px;height:32px;font-size:0.5rem;">NO<br>IMG</span>
                  <?php endif; ?>
                  <span class="lp3-live-top-name"><?= h($s['nickname'] ?? $s['name']) ?></span>
                  <span class="lp3-live-top-pt"><?= ((float) $s['total'] >= 0 ? '+' : '') . number_format((float) $s['total'], 1) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php elseif (!empty($preparingTournaments)):
    $pt = $preparingTournaments[0];
    $pEvent = EventType::tryFrom($pt['event_type'] ?? '');
  ?>
    <div class="lp3-live-empty">
      <strong>&#x2728; 次回大会 準備中</strong>
      <?php if ($pEvent): ?>
        <span class="lp3-chip is-pink" style="margin-right:6px;"><?= h($pEvent->label()) ?></span>
      <?php endif; ?>
      <a href="tournament?id=<?= (int) $pt['id'] ?>" class="lp3-btn lp3-btn-ghost lp3-btn-sm lp3-btn-arrow" style="margin-top:14px;"><?= h($pt['name']) ?></a>
    </div>
  <?php else: ?>
    <div class="lp3-live-empty">
      <strong>&#x1F4AC; 現在開催中の大会はありません</strong>
      <a href="tournaments" class="lp3-btn lp3-btn-ghost lp3-btn-sm lp3-btn-arrow" style="margin-top:14px;">大会一覧へ</a>
    </div>
  <?php endif; ?>
  </div>
</section>

<?= $dividerHtml($divLiveChamp, 'anim-3') ?>

<!-- ======================== -->
<!-- CHAMPION SPOTLIGHT -->
<!-- ======================== -->
<?php if ($latestChampion && $latestCompleted): ?>
<section class="lp3-band band-champion lp3-reveal">
  <div class="lp3-band-deco" aria-hidden="true">
    <div style="position:absolute;top:50%;left:4%;"><?= $svgRibbon('#f5c95c', 96) ?></div>
    <div style="position:absolute;bottom:16%;right:8%;"><?= $svgTilePin(48) ?></div>
  </div>
  <div class="lp3-inner">
    <span class="lp3-eyebrow">Reigning Champion</span>
    <h2 class="lp3-heading">最新王者<span class="dot">.</span></h2>
  <div class="lp3-champion">
    <div class="lp3-champion-grid">
      <div class="lp3-champion-avatar">
        <?php if (!empty($latestChampion['character_icon'])): ?>
          <img src="img/chara_deformed/<?= h($latestChampion['character_icon']) ?>" alt="" width="220" height="220" loading="eager">
        <?php else: ?>
          <div class="lp3-avatar-ph" style="width:100%;height:100%;font-size:1.2rem;">NO<br>IMG</div>
        <?php endif; ?>
      </div>
      <div class="lp3-champion-body">
        <?php
        $champEvent = EventType::tryFrom($latestCompleted['event_type'] ?? '');
        $champName = $latestChampion['nickname'] ?? $latestChampion['name'];
        ?>
        <div class="lp3-champion-tag">Latest Winner</div>
        <h3 class="lp3-champion-name"><?= h($champName) ?></h3>
        <div class="lp3-champion-meta">
          <?php if ($champEvent): ?>
            <span class="lp3-chip is-pink"><?= h($champEvent->label()) ?></span>
          <?php endif; ?>
          <span class="lp3-chip"><?= h($latestCompleted['name']) ?></span>
          <span class="lp3-chip is-mint"><?= (int) $latestCompleted['player_count'] ?>名参加</span>
        </div>
        <div class="lp3-champion-score">
          <span class="lp3-champion-score-num"><?= number_format((float) $latestChampion['total'], 1) ?></span>
          <span class="lp3-champion-score-unit">PT</span>
        </div>
        <div class="lp3-champion-actions">
          <a href="tournament_view?id=<?= (int) $latestCompleted['id'] ?>" class="lp3-btn lp3-btn-primary lp3-btn-arrow">大会結果を見る</a>
          <a href="player?id=<?= (int) $latestChampion['player_id'] ?>" class="lp3-btn lp3-btn-secondary">選手プロフィール</a>
        </div>
      </div>
    </div>
  </div>
  </div>
</section>
<?php endif; ?>

<?= $dividerHtml($divChampVault, 'anim-4') ?>

<!-- ======================== -->
<!-- VAULT -->
<!-- ======================== -->
<section class="lp3-band band-vault lp3-reveal">
  <div class="lp3-band-deco" aria-hidden="true">
    <div style="position:absolute;bottom:18%;right:34%;"><?= $svgTileSou(44) ?></div>
    <div style="position:absolute;bottom:10%;left:22%;"><?= $svgRibbon('#a56cff', 76) ?></div>
  </div>
  <div class="lp3-inner">
    <span class="lp3-eyebrow">Hall of Fame</span>
    <h2 class="lp3-heading">殿堂<span class="dot">.</span></h2>
  <div class="lp3-vault">

    <!-- 通算優勝数 Top3 -->
    <div class="lp3-vault-cell is-wide">
      <div class="lp3-vault-label">Career Wins</div>
      <div class="lp3-vault-title">通算優勝数 Top3</div>
      <?php if (!empty($championCounts)): ?>
        <div class="lp3-champion-list">
          <?php foreach ($championCounts as $i => $ch): $rank = $i + 1; ?>
            <a href="player?id=<?= (int) $ch['player_id'] ?>" class="lp3-champion-row rank-<?= $rank ?>">
              <span class="lp3-rank">#<?= $rank ?></span>
              <?php if (!empty($ch['character_icon'])): ?>
                <img src="img/chara_deformed/<?= h($ch['character_icon']) ?>" alt="" class="lp3-avatar" width="48" height="48" loading="lazy">
              <?php else: ?>
                <span class="lp3-avatar-ph">NO<br>IMG</span>
              <?php endif; ?>
              <span class="lp3-champion-name-text"><?= h($ch['player_name']) ?></span>
              <span class="lp3-champion-count"><?= (int) $ch['win_count'] ?><small>WIN<?= ((int) $ch['win_count']) > 1 ? 'S' : '' ?></small></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="lp3-vault-empty">完了大会がまだありません</div>
      <?php endif; ?>
    </div>

    <!-- 最高スコア -->
    <div class="lp3-vault-cell is-half">
      <div class="lp3-vault-label">Highest Score</div>
      <div class="lp3-vault-title">歴代最高ラウンド得点</div>
      <?php if ($highestScore): ?>
        <a href="player?id=<?= (int) $highestScore['player_id'] ?>" class="lp3-record">
          <?php if (!empty($highestScore['character_icon'])): ?>
            <img src="img/chara_deformed/<?= h($highestScore['character_icon']) ?>" alt="" class="lp3-avatar" width="60" height="60" loading="lazy">
          <?php else: ?>
            <span class="lp3-avatar-ph" style="width:60px;height:60px;">NO<br>IMG</span>
          <?php endif; ?>
          <div class="lp3-record-info">
            <span class="lp3-record-num"><?= ((float) $highestScore['score'] >= 0 ? '+' : '') . number_format((float) $highestScore['score'], 1) ?><small>PT</small></span>
            <div class="lp3-record-name"><?= h($highestScore['player_name']) ?></div>
            <div class="lp3-record-sub"><?= h($highestScore['tournament_name']) ?> ・ 第<?= (int) $highestScore['round_number'] ?>回戦</div>
          </div>
        </a>
      <?php else: ?>
        <div class="lp3-vault-empty">記録なし</div>
      <?php endif; ?>
    </div>

    <!-- 最多トップ -->
    <div class="lp3-vault-cell is-half">
      <div class="lp3-vault-label">Most Table Tops</div>
      <div class="lp3-vault-title">歴代最多卓1位</div>
      <?php if ($mostTops && !empty($mostTops['winners'])):
          $tw = $mostTops['winners'][0];
      ?>
        <a href="player?id=<?= (int) $tw['player_id'] ?>" class="lp3-record">
          <?php if (!empty($tw['character_icon'])): ?>
            <img src="img/chara_deformed/<?= h($tw['character_icon']) ?>" alt="" class="lp3-avatar" width="60" height="60" loading="lazy">
          <?php else: ?>
            <span class="lp3-avatar-ph" style="width:60px;height:60px;">NO<br>IMG</span>
          <?php endif; ?>
          <div class="lp3-record-info">
            <span class="lp3-record-num"><?= (int) $mostTops['top_count'] ?><small>TOPS</small></span>
            <div class="lp3-record-name"><?= h($tw['player_name']) ?><?= count($mostTops['winners']) > 1 ? ' 他' . (count($mostTops['winners']) - 1) . '名' : '' ?></div>
            <div class="lp3-record-sub">全大会横断</div>
          </div>
        </a>
      <?php else: ?>
        <div class="lp3-vault-empty">記録なし</div>
      <?php endif; ?>
    </div>

  </div>
  </div>
</section>

<?= $dividerHtml($divVaultSeries, 'anim-5') ?>

<!-- ======================== -->
<!-- SERIES HANGAR -->
<!-- ======================== -->
<section class="lp3-band band-series lp3-reveal">
  <div class="lp3-band-deco" aria-hidden="true">
    <div style="position:absolute;top:50%;right:32%;"><?= $svgRibbon('#a56cff', 72) ?></div>
    <div style="position:absolute;top:10%;left:24%;"><?= $svgTilePin(42) ?></div>
  </div>
  <div class="lp3-inner">
    <span class="lp3-eyebrow">Series</span>
    <h2 class="lp3-heading">大会シリーズ<span class="dot">.</span></h2>
  <div class="lp3-series">
    <?php
    $seriesOrder = [
        EventType::Saikyoi->value => 'S',
        EventType::Hooh->value => 'H',
        EventType::Masters->value => 'M',
        EventType::Hyakudanisen->value => '100',
        EventType::Petit->value => 'P',
    ];
    foreach ($seriesOrder as $etv => $code):
        $eventEnum = EventType::tryFrom($etv);
        if (!$eventEnum) {
            continue;
        }
        $latest = $latestByEvent[$etv] ?? null;
        $count = $eventCounts[$etv] ?? 0;
        $tsLatest = $latest ? TournamentStatus::tryFrom($latest['status']) : null;
        $viewable = $latest && $latest['status'] !== TournamentStatus::Preparing->value;
        $href = $latest ? ($viewable ? 'tournament_view?id=' . (int) $latest['id'] : 'tournament?id=' . (int) $latest['id']) : 'tournaments';
    ?>
      <a href="<?= $href ?>" class="lp3-series-tile<?= $count === 0 ? ' is-empty' : '' ?>" data-code="<?= h((string) $code) ?>">
        <span class="lp3-series-count">開催 <?= (int) $count ?> 回</span>
        <div class="lp3-series-label"><?= h($eventEnum->label()) ?></div>
        <?php if ($latest): ?>
          <div class="lp3-series-latest-title">Latest</div>
          <div class="lp3-series-latest-name"><?= h($latest['name']) ?></div>
          <?php if (!empty($latest['winner_name']) && $latest['status'] === TournamentStatus::Completed->value): ?>
            <div class="lp3-series-winner">&#x1F451; <?= h($latest['winner_name']) ?></div>
          <?php elseif ($tsLatest): ?>
            <div class="lp3-series-winner" style="color:var(--ink-sub);">
              <?= h($tsLatest->label()) ?>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="lp3-series-empty-note">開催予定なし</div>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
  </div>
</section>

<?= $dividerHtml($divSeriesRoster, 'anim-6') ?>

<!-- ======================== -->
<!-- ROSTER (horizontal scroll) -->
<!-- ======================== -->
<?php if (!empty($pointLeaders)): ?>
<section class="lp3-band band-roster lp3-reveal">
  <div class="lp3-band-deco" aria-hidden="true">
    <div style="position:absolute;bottom:14%;right:14%;"><?= $svgTileMan(48) ?></div>
    <div style="position:absolute;bottom:30%;left:32%;"><?= $svgRibbon('#3fd1a3', 72) ?></div>
  </div>
  <div class="lp3-inner">
    <span class="lp3-eyebrow">Top Players</span>
    <h2 class="lp3-heading">選手リーダーボード<span class="dot">.</span></h2>
  <div class="lp3-roster-scroller">
    <?php foreach ($pointLeaders as $i => $pl):
        $rank = $i + 1;
        $totalPt = (float) $pl['total_pt'];
    ?>
      <a href="player?id=<?= (int) $pl['player_id'] ?>" class="lp3-roster-card<?= $rank <= 3 ? ' is-top' : '' ?>">
        <div class="lp3-roster-rank">#<?= $rank ?></div>
        <?php if (!empty($pl['character_icon'])): ?>
          <img src="img/chara_deformed/<?= h($pl['character_icon']) ?>" alt="" class="lp3-roster-avatar" width="90" height="90" loading="lazy">
        <?php else: ?>
          <span class="lp3-roster-avatar-ph">NO<br>IMG</span>
        <?php endif; ?>
        <div class="lp3-roster-name"><?= h($pl['player_name']) ?></div>
        <div class="lp3-roster-pt<?= $totalPt < 0 ? ' is-neg' : '' ?>"><?= ($totalPt >= 0 ? '+' : '') . number_format($totalPt, 1) ?></div>
        <div class="lp3-roster-events"><?= (int) $pl['tournament_count'] ?> events</div>
      </a>
    <?php endforeach; ?>
  </div>
  <div style="text-align:right;margin-top:14px;">
    <a href="players" class="lp3-btn lp3-btn-ghost lp3-btn-sm lp3-btn-arrow">選手一覧を見る</a>
  </div>
  </div>
</section>
<?php endif; ?>

<?= $dividerHtml($divRosterArchive, 'anim-7') ?>

<!-- ======================== -->
<!-- ARCHIVE -->
<!-- ======================== -->
<?php if (!empty($tournaments)): ?>
<section class="lp3-band band-archive lp3-reveal">
  <div class="lp3-band-deco" aria-hidden="true">
    <div style="position:absolute;bottom:10%;left:16%;"><?= $svgTilePin(46) ?></div>
    <div style="position:absolute;top:50%;right:32%;"><?= $svgRibbon('#3fd1a3', 72) ?></div>
  </div>
  <div class="lp3-inner">
    <span class="lp3-eyebrow">Archive</span>
    <h2 class="lp3-heading">大会アーカイブ<span class="dot">.</span></h2>
  <div class="lp3-archive-grid">
    <?php foreach (array_slice($tournaments, 0, 8) as $t):
        $tsEnum = TournamentStatus::tryFrom($t['status']);
        $etEnum = EventType::tryFrom($t['event_type'] ?? '');
        $isViewable = $t['status'] !== TournamentStatus::Preparing->value;
        $href = $isViewable ? 'tournament_view?id=' . (int) $t['id'] : 'tournament?id=' . (int) $t['id'];
        $dateStr = !empty($t['start_date']) ? date('Y.m.d', strtotime($t['start_date'])) : '';
    ?>
      <a href="<?= $href ?>" class="lp3-archive-row">
        <span class="lp3-archive-date"><?= h($dateStr) ?></span>
        <span class="lp3-archive-name"><?= h($t['name']) ?></span>
        <span class="lp3-archive-event"><?= h($etEnum?->label() ?? '-') ?></span>
        <span class="lp3-archive-winner">
          <?php if ($t['status'] === TournamentStatus::Completed->value && !empty($t['winner_name'])): ?>
            &#x1F451; <strong><?= h($t['winner_name']) ?></strong>
          <?php else: ?>
            <?= (int) $t['player_count'] ?>名参加
          <?php endif; ?>
        </span>
        <span class="lp3-archive-status <?= $tsEnum?->cssClass() ?? '' ?>"><?= h($tsEnum?->label() ?? '') ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if (count($tournaments) > 8): ?>
    <div style="text-align:right;margin-top:14px;">
      <a href="tournaments" class="lp3-btn lp3-btn-ghost lp3-btn-sm lp3-btn-arrow">すべての大会</a>
    </div>
  <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?= $dividerHtml($divArchiveSpot, 'anim-8') ?>

<!-- ======================== -->
<!-- SPOTLIGHT (Interview) -->
<!-- ======================== -->
<?php if ($latestInterview): ?>
<section class="lp3-band band-spotlight lp3-reveal">
  <div class="lp3-band-deco" aria-hidden="true">
    <div style="position:absolute;top:30%;right:10%;"><?= $svgTileSou(44) ?></div>
    <div style="position:absolute;bottom:30%;left:24%;"><?= $svgRibbon('#ff6aa6', 80) ?></div>
  </div>
  <div class="lp3-inner">
  <div class="lp3-spotlight">
    <div class="lp3-spotlight-grid">
      <div>
        <span class="lp3-eyebrow">Champion Interview</span>
        <div class="lp3-spotlight-name"><?= h($latestInterview['tournament_name']) ?></div>
        <div class="lp3-spotlight-meta">
          優勝者インタビュー <strong><?= (int) $latestInterview['qa_count'] ?></strong> 問
        </div>
      </div>
      <a href="interview?id=<?= (int) $latestInterview['tournament_id'] ?>" class="lp3-btn lp3-btn-primary lp3-btn-arrow">インタビューを読む</a>
    </div>
  </div>
  </div>
</section>
<?php endif; ?>

<?= $dividerHtml($divSpotCta, 'anim-9') ?>

<!-- ======================== -->
<!-- CTA -->
<!-- ======================== -->
<section class="lp3-band band-cta lp3-cta lp3-reveal">
  <div class="lp3-band-deco" aria-hidden="true">
    <div style="position:absolute;top:40%;left:6%;"><?= $svgTileSou(46) ?></div>
    <div style="position:absolute;bottom:14%;right:26%;"><?= $svgRibbon('#a56cff', 88) ?></div>
  </div>
  <div class="lp3-inner">
    <div class="lp3-cta-title">&#x1F3B2; Explore The Archive</div>
    <div class="lp3-cta-sub">過去の対局結果や選手情報をチェックしよう</div>
    <div class="lp3-cta-actions">
      <a href="tournaments" class="lp3-btn lp3-btn-primary lp3-btn-arrow">大会一覧へ</a>
      <a href="players" class="lp3-btn lp3-btn-secondary">選手一覧へ</a>
    </div>
  </div>
</section>

</div><!-- /.lp3 -->

<?php require __DIR__ . '/../templates/footer.php'; ?>
