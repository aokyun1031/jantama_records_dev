<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

$isTopPage = true;

// --- OGP用の高速データのみ先行取得（ローダー早期描画のため） ---
['data' => $stats] = fetchData(fn() => HallOfFame::siteStats());
$stats = $stats ?? [
    'total_tournaments' => 0, 'completed_tournaments' => 0, 'total_players' => 0,
    'total_tables' => 0, 'done_tables' => 0, 'total_rounds' => 0,
];

// キャラ画像ファイル一覧（装飾用）— ローダー用キャラを先に決める
$charaFiles = [];
$charaGlob = glob(__DIR__ . '/img/chara_deformed/*.png') ?: [];
foreach ($charaGlob as $p) {
    $charaFiles[] = basename($p);
}
sort($charaFiles);

// 各band境界のdivider用キャラ（ページロード毎にランダムで重複なし3体選出）
$charaRandomPick = function (int $count) use ($charaFiles): array {
    $n = count($charaFiles);
    if ($n === 0) {
        return [];
    }
    if ($n <= $count) {
        $pool = $charaFiles;
        shuffle($pool);
        return $pool;
    }
    $keys = (array) array_rand($charaFiles, $count);
    shuffle($keys);
    return array_map(fn($k) => $charaFiles[$k], $keys);
};
// セクション区切り（divider）用キャラ：隣接セクション間に 3 体固定で配置（左・中央・右）。
// あくまで区切り装飾として小さく目立たないサイズで置く。
// 順序: Live→Champion, Champion→Series, Series→Vault, Vault→Roster,
//       Roster→Archive, Archive→Spotlight。Hero 直後（Live 手前）は divider 無し。
$dividerKeys = ['champion','series','vault','roster','archive','spotlight'];
$dividerChars = [];
$animPool = ['lp3Fuwafuwa', 'lp3Norinori', 'lp3Gunyon', 'lp3Pyon', 'lp3Indicator', 'lp3Yoisho'];
foreach ($dividerKeys as $key) {
    $trio = $charaRandomPick(3);
    $dividerChars[$key] = [
        [
            'file'  => $trio[0] ?? '',
            'anim'  => $animPool[array_rand($animPool)],
            'delay' => number_format(mt_rand(0, 80) / 100, 2) . 's',
        ],
        [
            'file'  => $trio[1] ?? '',
            'anim'  => $animPool[array_rand($animPool)],
            'delay' => number_format(mt_rand(0, 120) / 100, 2) . 's',
        ],
        [
            'file'  => $trio[2] ?? '',
            'anim'  => $animPool[array_rand($animPool)],
            'delay' => number_format(mt_rand(0, 160) / 100, 2) . 's',
        ],
    ];
}

// Divider 出力ヘルパー：3体固定（left / center / right）で横並び
$dividerHtml = function (string $key) use ($dividerChars): string {
    $trio = $dividerChars[$key] ?? null;
    if (!$trio) {
        return '';
    }
    $char = function (array $info, string $side): string {
        if (empty($info['file'])) {
            return '';
        }
        return '<img src="img/chara_deformed/' . h($info['file'])
            . '" alt="" aria-hidden="true" class="lp3-divider-char is-' . h($side)
            . ' anim-' . h($info['anim'])
            . '" style="animation-delay:' . h($info['delay']) . '"'
            . ' width="56" height="56" loading="lazy">';
    };
    return '<div class="lp3-section-divider" data-for="' . h($key) . '" aria-hidden="true">'
        . $char($trio[0], 'left')
        . $char($trio[1], 'center')
        . $char($trio[2], 'right')
        . '</div>';
};

// ローダー用キャラ（ランダム3体: 左・主役・右）
$loaderChars = $charaRandomPick(3);
// ローダー各キャラに割り当てるアニメ（a〜f からランダム）
$loaderAnimPool = ['a', 'b', 'c', 'd', 'e', 'f'];
$loaderAnims = [
    $loaderAnimPool[array_rand($loaderAnimPool)],
    $loaderAnimPool[array_rand($loaderAnimPool)],
    $loaderAnimPool[array_rand($loaderAnimPool)],
];
// animation-delay もランダムに散らして同期しないようにする
$loaderDelays = [
    number_format(mt_rand(0, 60) / 100, 2) . 's',
    number_format(mt_rand(0, 60) / 100, 2) . 's',
    number_format(mt_rand(0, 60) / 100, 2) . 's',
];

// --- OGP（高速データのみで構築、詳細は後続で追加する省略版） ---
$ogpDesc = '雀魂で開催する麻雀トーナメントの戦績・対局結果・選手情報を掲載。開催大会 '
    . (int) $stats['total_tournaments'] . ' / 登録選手 '
    . (int) $stats['total_players'] . '名 / 総半荘数 '
    . (int) $stats['total_rounds'];

$pageTitle = SITE_NAME . ' | 雀魂トーナメント戦績サイト';
$pageDescription = $ogpDesc;
$pageOgp = [
    'title' => $pageTitle,
    'description' => $ogpDesc,
    'url' => SITE_URL . '/',
    'image' => SITE_URL . '/img/ogp-landing.jpg',
    'image_alt' => SITE_NAME . ' - 雀魂トーナメント戦績ダッシュボード',
];

$pageCss = ['css/landing-pop.css', 'css/sakura.css'];
$pageScripts = ['js/landing-pop.js', 'js/sakura.js'];

// Loader の Critical CSS をインラインで埋める（外部CSSの到着前でも表示できるように）
// 注: 外部CSSが未ロードの段階で描画するため、ここでは CSS 変数が解決できず
//     ハードコード色を使用する。このファイルに限っての意図的な例外。
$pageStyle = <<<CSS
.lp3-loader { position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: #fff8f0; transition: opacity .7s ease, visibility .7s ease; }
.lp3-loader.is-done { opacity: 0; visibility: hidden; pointer-events: none; }
.lp3-loader-inner { text-align: center; padding: 20px; }
.lp3-loader-cast { display: flex; gap: 18px; justify-content: center; align-items: flex-end; margin: 0 auto 22px; }
.lp3-loader-char { display: block; transform-origin: 50% 100%; }
.lp3-loader-char.is-main { width: 136px; height: 136px; }
.lp3-loader-char.is-side { width: 88px; height: 88px; opacity: 0.92; }
.lp3-loader-char.anim-a { animation: lp3LA 1.6s ease-in-out infinite; }
.lp3-loader-char.anim-b { animation: lp3LB 1.8s ease-in-out infinite; }
.lp3-loader-char.anim-c { animation: lp3LC 2.0s ease-in-out infinite; }
.lp3-loader-char.anim-d { animation: lp3LD 1.4s ease-in-out infinite; }
.lp3-loader-char.anim-e { animation: lp3LE 2.2s cubic-bezier(.3,1.3,.6,1) infinite; }
.lp3-loader-char.anim-f { animation: lp3LF 1.7s ease-in-out infinite; }
.lp3-loader-title { font-family: 'M PLUS Rounded 1c','Noto Sans JP',sans-serif; font-size: clamp(1.4rem, 4vw, 2rem); font-weight: 900; color: #3a2d50; letter-spacing: 0.08em; }
.lp3-loader-status { margin-top: 14px; font-family: 'M PLUS Rounded 1c','Noto Sans JP',sans-serif; font-size: 0.88rem; color: #715c88; letter-spacing: 0.04em; min-height: 1.4em; }
.lp3-loader-bar { margin: 14px auto 0; width: 200px; height: 3px; background: rgba(58,45,80,0.08); border-radius: 999px; overflow: hidden; }
.lp3-loader-bar span { display: block; width: 40%; height: 100%; background: linear-gradient(90deg, #ff6aa6, #a56cff); border-radius: 999px; animation: lp3LoaderBar 1.4s cubic-bezier(0.4, 0, 0.2, 1) infinite; }
@keyframes lp3LoaderBar { 0% { transform: translateX(-150%); } 100% { transform: translateX(400%); } }
/* a: 上下バウンス + スクワッシュ */
@keyframes lp3LA { 0%,100%{transform:translateY(0) scale(1,1);} 30%{transform:translateY(-14px) scale(.95,1.05);} 50%{transform:translateY(0) scale(1.18,.82);} 70%{transform:translateY(-6px) scale(1.02,.98);} }
/* b: 左右スウェイ */
@keyframes lp3LB { 0%,100%{transform:translateY(0) rotate(-8deg);} 50%{transform:translateY(-10px) rotate(8deg);} }
/* c: ふわふわ浮遊 */
@keyframes lp3LC { 0%,100%{transform:translateY(0) rotate(-2deg);} 25%{transform:translateY(-8px) rotate(1deg);} 50%{transform:translateY(-14px) rotate(-1deg);} 75%{transform:translateY(-6px) rotate(2deg);} }
/* d: スケール脈動 */
@keyframes lp3LD { 0%,100%{transform:scale(1,1);} 50%{transform:scale(1.1,.9);} }
/* e: ピョン（ジャンプ→スクワッシュ） */
@keyframes lp3LE { 0%,100%{transform:translateY(0) scale(1,1);} 15%{transform:translateY(4px) scale(1.12,.88);} 40%{transform:translateY(-26px) scale(.92,1.08);} 60%{transform:translateY(0) scale(1.2,.8);} 75%{transform:translateY(-6px) scale(.96,1.04);} }
/* f: 軽いお辞儀 */
@keyframes lp3LF { 0%,20%,100%{transform:translateY(0) rotate(0) scaleY(1);} 45%{transform:translateY(4px) rotate(-10deg) scaleY(.92);} 65%{transform:translateY(0) rotate(2deg) scaleY(1.02);} }
@media (prefers-reduced-motion: reduce) { .lp3-loader-char, .lp3-loader-dots span { animation: none; } }
CSS;

require __DIR__ . '/../templates/header.php';
?>

<!-- Loader（header直後に出力→flushで先行描画） -->
<div class="lp3-loader" id="lp3-loader" role="status" aria-live="polite" aria-label="読み込み中">
  <div class="lp3-loader-inner">
    <div class="lp3-loader-cast" aria-hidden="true">
      <?php if (isset($loaderChars[0])): ?>
        <img src="img/chara_deformed/<?= h($loaderChars[0]) ?>" alt="" class="lp3-loader-char is-side is-left anim-<?= h($loaderAnims[0]) ?>" style="animation-delay: <?= h($loaderDelays[0]) ?>" width="88" height="88">
      <?php endif; ?>
      <?php if (isset($loaderChars[1])): ?>
        <img src="img/chara_deformed/<?= h($loaderChars[1]) ?>" alt="" class="lp3-loader-char is-main anim-<?= h($loaderAnims[1]) ?>" style="animation-delay: <?= h($loaderDelays[1]) ?>" width="136" height="136">
      <?php endif; ?>
      <?php if (isset($loaderChars[2])): ?>
        <img src="img/chara_deformed/<?= h($loaderChars[2]) ?>" alt="" class="lp3-loader-char is-side is-right anim-<?= h($loaderAnims[2]) ?>" style="animation-delay: <?= h($loaderDelays[2]) ?>" width="88" height="88">
      <?php endif; ?>
    </div>
    <div class="lp3-loader-title"><?= h(SITE_NAME) ?></div>
    <div class="lp3-loader-status" id="lp3-loader-status">ロビー清掃中…</div>
    <div class="lp3-loader-bar" aria-hidden="true"><span></span></div>
  </div>
</div>

<!-- 桜演出コンテナ（JS が .sakura-petal を動的追加） -->
<div class="sakura-container" aria-hidden="true"></div>

<?php
// ローダーをブラウザへ先行送出してから重いクエリを実行
if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) { @ob_end_flush(); }
}
@flush();

// --- 重いデータ取得（ローダー表示中に実行される） ---
['data' => $tournaments] = fetchData(fn() => Tournament::allWithDetails());
['data' => $championCounts] = fetchData(fn() => HallOfFame::championCounts(3));
['data' => $randomPlayers] = fetchData(fn() => HallOfFame::randomPlayers(8));
['data' => $highestScores] = fetchData(fn() => HallOfFame::highestRoundScores(3));
['data' => $mostTops] = fetchData(fn() => HallOfFame::mostTopFinishes(3));
['data' => $latestByEvent] = fetchData(fn() => HallOfFame::latestByEventType());
['data' => $latestInterviews] = fetchData(fn() => HallOfFame::latestInterviews(5));

$tournaments = $tournaments ?? [];
$championCounts = $championCounts ?? [];
$highestScores = $highestScores ?? [];
$mostTops = $mostTops ?? [];
$randomPlayers = $randomPlayers ?? [];
$latestByEvent = $latestByEvent ?? [];

$completedTournaments = array_values(array_filter($tournaments, fn($t) => $t['status'] === TournamentStatus::Completed->value));
$activeTournaments = array_values(array_filter($tournaments, fn($t) => $t['status'] === TournamentStatus::InProgress->value));
$preparingTournaments = array_values(array_filter($tournaments, fn($t) => $t['status'] === TournamentStatus::Preparing->value));

$latestCompleted = $completedTournaments[0] ?? null;
if ($latestCompleted) {
    ['data' => $latestChampion] = fetchData(fn() => Standing::champion((int) $latestCompleted['id']));
} else {
    $latestChampion = null;
}

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
?>

<div class="lp3">

<?php require __DIR__ . '/../templates/partials/index/hero.php'; ?>

<?php require __DIR__ . '/../templates/partials/index/live.php'; ?>

<?php require __DIR__ . '/../templates/partials/index/champion.php'; ?>

<?php require __DIR__ . '/../templates/partials/index/series.php'; ?>

<?php require __DIR__ . '/../templates/partials/index/vault.php'; ?>

<?php require __DIR__ . '/../templates/partials/index/roster.php'; ?>

<?php require __DIR__ . '/../templates/partials/index/archive.php'; ?>

<?php require __DIR__ . '/../templates/partials/index/spotlight.php'; ?>

</div><!-- /.lp3 -->

<?php require __DIR__ . '/../templates/footer.php'; ?>
