<?php

declare(strict_types=1);

/**
 * 現行モデル（models/）の読み取り系メソッドの戻り値を、全パラメータの組み合わせで書き出す。
 *
 * リニューアルで計算ロジックを書き直すときに「現行コードが何を返していたか」の記録として使う。
 * 出力は 1 呼び出し 1 行の JSON Lines（標準出力）。件数などの要約は標準エラーに出す。
 *
 *   php tests/characterization/legacy-export.php > out.jsonl
 *
 * 実行は E2E 専用スタック（docker-compose.test.yml）の web コンテナ内で行う（run.mjs が呼ぶ）。
 * 読み取りしかしない。パラメータ（大会 ID・選手 ID など）は DB から直接集める。
 *
 * 書き出す対象は「models/ の全クラスの public static メソッド」から、下の EXCLUDED に
 * 理由つきで挙げたものを除いた全部。どちらにも無いメソッドが見つかったら失敗する
 * （モデルにメソッドが増えたのに記録から漏れる、という事故を防ぐ）。
 */

require dirname(__DIR__, 2) . '/config/bootstrap.php';

/**
 * 書き出さないメソッド。値はその理由。
 * 書き込み系はここでは呼ばず、書き込みシナリオの記録（別途）で扱う。
 */
const EXCLUDED = [
    'DiscordScheduledEvent::upsert' => 'write',
    'Interview::save' => 'write',
    'Player::create' => 'write',
    'Player::delete' => 'write',
    'Player::update' => 'write',
    'Player::updateDiscord' => 'write',
    'Player::updateDiscordUserId' => 'write',
    'RoundResult::saveScores' => 'write',
    'ScheduleCandidate::createBatch' => 'write',
    'ScheduleResponse::replaceForPlayer' => 'write',
    'Standing::processRoundAdvancement' => 'write',
    'Standing::updateTotals' => 'write',
    'TableInfo::completeWithResults' => 'write',
    'TableInfo::create' => 'write',
    'TableInfo::createBatch' => 'write',
    'TableInfo::delete' => 'write',
    'TableInfo::markDone' => 'write',
    'TableInfo::updateSchedule' => 'write',
    'TablePaifuUrl::saveAll' => 'write',
    'Tournament::addPlayer' => 'write',
    'Tournament::complete' => 'write',
    'Tournament::createWithDetails' => 'write',
    'Tournament::delete' => 'write',
    'Tournament::processRoundCompletion' => 'write',
    'Tournament::recordDmDispatch' => 'write',
    'Tournament::removePlayer' => 'write',
    'Tournament::start' => 'write',
    'Tournament::updateDetails' => 'write',
    'Tournament::updatePlayers' => 'write',
    'TournamentMeta::set' => 'write',
];

/** 一覧系メソッドを上限なしで呼ぶときの件数（シードの行数より十分大きい値） */
const NO_LIMIT = 100000;

// --- パラメータの収集 -------------------------------------------------------

$pdo = getDbConnection();

/** @return list<int> */
$intColumn = static fn(string $sql): array => array_map(
    'intval',
    $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN)
);

$tournamentIds = $intColumn('SELECT id FROM tournaments ORDER BY id');
$playerIds = $intColumn('SELECT id FROM players ORDER BY id');
$characterIds = $intColumn('SELECT id FROM characters ORDER BY id');
$tableIds = $intColumn('SELECT id FROM tables_info ORDER BY id');
$candidateIds = $intColumn('SELECT id FROM schedule_candidates ORDER BY id');
$playerNames = $pdo->query('SELECT name FROM players ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);

/** 大会ごとの、卓または成績が存在する回戦番号 */
$roundsByTournament = array_fill_keys($tournamentIds, []);
$rows = $pdo->query(
    'SELECT tournament_id, round_number FROM tables_info
     UNION
     SELECT tournament_id, round_number FROM round_results
     ORDER BY 1, 2'
)->fetchAll();
foreach ($rows as $row) {
    $roundsByTournament[(int) $row['tournament_id']][] = (int) $row['round_number'];
}

/** 大会ごとの登録選手（standings に行がある選手） */
$playersByTournament = array_fill_keys($tournamentIds, []);
$rows = $pdo->query('SELECT tournament_id, player_id FROM standings ORDER BY 1, 2')->fetchAll();
foreach ($rows as $row) {
    $playersByTournament[(int) $row['tournament_id']][] = (int) $row['player_id'];
}

/** 大会種別フィルタ: 指定なし / 1 種別ずつ / 全種別 */
$eventTypeValues = array_map(static fn(EventType $e): string => $e->value, EventType::cases());
$eventTypeFilters = [[]];
foreach ($eventTypeValues as $value) {
    $eventTypeFilters[] = [$value];
}
$eventTypeFilters[] = $eventTypeValues;

// --- 呼び出し一覧の組み立て -------------------------------------------------

/** @var list<array{0:string, 1:string, 2:list<mixed>, 3?:string}> $calls [クラス, メソッド, 引数, 並べ替えキー] */
$calls = [];
$add = static function (string $class, string $method, array $args = [], ?string $sortBy = null) use (&$calls): void {
    $calls[] = $sortBy === null ? [$class, $method, $args] : [$class, $method, $args, $sortBy];
};

// マスタ・選手
$add('Character', 'all');
foreach ($characterIds as $id) {
    $add('Character', 'find', [$id]);
}
$add('Character', 'find', [NO_LIMIT]);

$add('Player', 'all');
$add('Player', 'count');
foreach ($playerIds as $id) {
    $add('Player', 'find', [$id]);
    $add('Player', 'hasTournaments', [$id]);
}
$add('Player', 'find', [NO_LIMIT]);
foreach ($playerNames as $name) {
    $add('Player', 'existsByName', [(string) $name]);
}
$add('Player', 'existsByName', ['__存在しない選手名__']);
$add('Player', 'findByDiscordUserId', ['000000000000000000']);

// 大会
$add('Tournament', 'all');
$add('Tournament', 'allWithDetails');
$add('TournamentMeta', 'allByTournamentIds', [$tournamentIds]);
$add('TournamentMeta', 'allByTournamentIds', [[]]);
foreach ($tournamentIds as $t) {
    $add('Tournament', 'find', [$t]);
    $add('Tournament', 'findWithMeta', [$t]);
    $add('Tournament', 'playerIds', [$t]);
    $add('Tournament', 'playedPlayerIds', [$t]);
    $add('Tournament', 'isFinalRoundDone', [$t]);
    $add('Tournament', 'dmTargets', [$t]);
    $add('Tournament', 'dmDispatchesByPlayer', [$t]);
    $add('TournamentMeta', 'all', [$t]);
    $add('TournamentMeta', 'get', [$t, 'player_mode', '']);
    $add('TournamentMeta', 'get', [$t, '__存在しないキー__', 'fallback']);
    $add('Interview', 'byTournament', [$t]);
    $add('TournamentRecords', 'all', [$t]);
    $add('TournamentRecords', 'highestRoundScore', [$t]);
    $add('TournamentRecords', 'mostTopFinishes', [$t]);
    $add('HallOfFame', 'currentRound', [$t]);

    // 順位・勝ち抜け
    $add('Standing', 'all', [$t]);
    $add('Standing', 'finalists', [$t]);
    $add('Standing', 'champion', [$t]);
    $add('Standing', 'activePlayerIds', [$t]);
    $add('Standing', 'activePlayersWithDetails', [$t]);
    $add('Standing', 'totalMap', [$t]);
    foreach ($playersByTournament[$t] as $p) {
        $add('Standing', 'findByPlayer', [$t, $p]);
        $add('TableInfo', 'byPlayerAndTournament', [$t, $p]);
    }

    // 卓・回戦
    $add('TableInfo', 'nextRoundNumber', [$t]);
    $add('TableInfo', 'byTournament', [$t]);
    foreach ($roundsByTournament[$t] as $r) {
        $add('RoundResult', 'byRound', [$t, $r]);
        $add('TableInfo', 'byRound', [$t, $r]);
        $add('TableInfo', 'byRoundForAnnounce', [$t, $r]);
        $add('TableInfo', 'playerGroupsByRound', [$t, $r]);
    }

    // 日程アンケート（存在する回戦と、その次の回戦）
    $scheduleRounds = $roundsByTournament[$t];
    $scheduleRounds[] = ($scheduleRounds === [] ? 0 : max($scheduleRounds)) + 1;
    foreach ($scheduleRounds as $r) {
        $add('ScheduleCandidate', 'byRound', [$t, $r]);
        $add('ScheduleCandidate', 'responseCountsByRound', [$t, $r]);
        $add('ScheduleResponse', 'respondedPlayerIds', [$t, $r]);
    }
    $nextRound = end($scheduleRounds);
    foreach ($playersByTournament[$t] as $p) {
        $add('ScheduleResponse', 'byPlayer', [$t, $nextRound, $p]);
    }
}
$add('Tournament', 'find', [NO_LIMIT]);
$add('Tournament', 'findWithMeta', [NO_LIMIT]);
$add('Standing', 'champion', [NO_LIMIT]);
if ($candidateIds !== []) {
    $add('ScheduleResponse', 'byCandidateIds', [$candidateIds]);
}
$add('ScheduleResponse', 'byCandidateIds', [[]]);

foreach ($tableIds as $id) {
    $add('TableInfo', 'find', [$id]);
    $add('TableInfo', 'findWithPlayers', [$id]);
    $add('TablePaifuUrl', 'byTable', [$id]);
    $add('DiscordScheduledEvent', 'find', [DiscordScheduledEvent::ENTITY_TABLE, $id]);
}
$add('TableInfo', 'find', [NO_LIMIT]);
$add('TableInfo', 'findWithPlayers', [NO_LIMIT]);

// 卓一覧の検索（フィルタの組み合わせとページング）
$searchFilters = [
    [],
    ['status' => 'done'],
    ['status' => 'pending'],
    ['event_types' => $eventTypeValues],
    ['keyword' => '第1回'],
    ['keyword' => 'テスト'],
    ['keyword' => '%'],
    ['keyword' => '_'],
    ['keyword' => '__該当なし__'],
];
foreach ($eventTypeValues as $value) {
    $searchFilters[] = ['event_types' => [$value]];
    $searchFilters[] = ['event_types' => [$value], 'status' => 'done'];
}
foreach ($tournamentIds as $t) {
    $searchFilters[] = ['tournament_id' => $t];
}
foreach ($searchFilters as $filters) {
    $add('TableInfo', 'searchAllCount', [$filters]);
    $add('TableInfo', 'searchAll', [$filters, NO_LIMIT, 0]);
}
$add('TableInfo', 'searchAll', [[], 10, 0]);
$add('TableInfo', 'searchAll', [[], 10, 10]);

// トップページの横断集計。既定の件数と、上限なしの両方を記録する
foreach (['championCounts', 'totalPointLeaders', 'highestRoundScores', 'mostTopFinishes', 'latestInterviews'] as $method) {
    $add('HallOfFame', $method);
    $add('HallOfFame', $method, [NO_LIMIT]);
}
$add('HallOfFame', 'siteStats');
$add('HallOfFame', 'latestByEventType');
// ORDER BY RANDOM() で抽出順が毎回変わるため、全件を取って選手 ID 順に並べ替えて記録する
$add('HallOfFame', 'randomPlayers', [NO_LIMIT], 'player_id');

// 個人成績の分析（選手 × 種別フィルタ）
$analysisMethods = [
    'summary', 'avgTableRank', 'headToHead', 'scoreHistory', 'rankDistribution',
    'scoreTimeline', 'roundPerformance', 'eventTypeStats', 'bestFinalRank',
];
foreach ($playerIds as $p) {
    $add('Tournament', 'byPlayer', [$p]);
    foreach ($eventTypeFilters as $filter) {
        foreach ($analysisMethods as $method) {
            $add('PlayerAnalysis', $method, [$p, $filter]);
        }
    }
}

// --- 対象の漏れ確認 ---------------------------------------------------------

$declared = [];
foreach (glob(dirname(__DIR__, 2) . '/models/*.php') ?: [] as $file) {
    $class = basename($file, '.php');
    foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->isStatic() && $method->getDeclaringClass()->getName() === $class) {
            $declared["{$class}::{$method->getName()}"] = true;
        }
    }
}
$listed = [];
foreach ($calls as [$class, $method]) {
    $listed["{$class}::{$method}"] = true;
}
$uncovered = array_diff(array_keys($declared), array_keys($listed), array_keys(EXCLUDED));
$bothListed = array_intersect(array_keys($listed), array_keys(EXCLUDED));
if ($uncovered !== [] || $bothListed !== []) {
    if ($uncovered !== []) {
        fwrite(STDERR, "書き出し対象にも除外一覧にも無いメソッド:\n  " . implode("\n  ", $uncovered) . "\n");
    }
    if ($bothListed !== []) {
        fwrite(STDERR, "書き出し対象と除外一覧の両方にあるメソッド:\n  " . implode("\n  ", $bothListed) . "\n");
    }
    exit(2);
}

// --- 書き出し ---------------------------------------------------------------

$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;
$counts = ['ok' => 0, 'error' => 0, 'missing' => 0];

foreach ($calls as $call) {
    [$class, $method, $args] = $call;
    $sortBy = $call[3] ?? null;
    $line = ['call' => "{$class}::{$method}", 'args' => $args];

    if (!isset($declared["{$class}::{$method}"])) {
        // 古いコミットのコードに対して実行したときなど。差分として見えるように記録して続ける
        $line['missing'] = true;
        $counts['missing']++;
    } else {
        try {
            $result = $class::$method(...$args);
            if ($sortBy !== null) {
                usort($result, static fn(array $a, array $b): int => $a[$sortBy] <=> $b[$sortBy]);
                $line['sorted_by'] = $sortBy;
            }
            $line['result'] = $result;
            $counts['ok']++;
        } catch (Throwable $e) {
            // 例外も現行の振る舞いとして記録する
            $line['error'] = ['class' => $e::class, 'message' => $e->getMessage()];
            $counts['error']++;
        }
    }

    echo json_encode($line, $flags), "\n";
}

fwrite(STDERR, sprintf(
    "legacy-export: %d 呼び出し（成功 %d / 例外 %d / メソッドなし %d）、対象 %d メソッド、除外 %d メソッド、PHP %s\n",
    count($calls),
    $counts['ok'],
    $counts['error'],
    $counts['missing'],
    count($listed),
    count(EXCLUDED),
    PHP_VERSION
));
