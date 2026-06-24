<?php

declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();
ensureCsrfToken();

header('Content-Type: application/json; charset=utf-8');

// --- メソッド + CSRF 検証 ---
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method not allowed'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    exit;
}

if (!validateCsrfToken()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'csrf'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    exit;
}

// --- 入力検証 ---
$tournamentId = filter_input(INPUT_POST, 'tournament_id', FILTER_VALIDATE_INT);
$roundNumber = filter_input(INPUT_POST, 'round_number', FILTER_VALIDATE_INT);
$onlyPlayerId = filter_input(INPUT_POST, 'player_id', FILTER_VALIDATE_INT);

if (!$tournamentId || !$roundNumber) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'tournament_id and round_number required'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    exit;
}

['data' => $tournament] = fetchData(fn() => Tournament::findWithMeta($tournamentId));
if (!$tournament) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'tournament not found'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    exit;
}

if ($tournament['status'] === TournamentStatus::Completed->value) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'tournament completed'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    exit;
}

['data' => $candidates] = fetchData(fn() => ScheduleCandidate::byRound($tournamentId, $roundNumber));
if (empty($candidates)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'no schedule candidates'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    exit;
}

// --- 送信対象決定 ---
if ($onlyPlayerId) {
    ['data' => $player] = fetchData(fn() => Player::find($onlyPlayerId));
    if (!$player) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'player not found'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        exit;
    }
    ['data' => $tournamentPlayerIds] = fetchData(fn() => Tournament::playerIds($tournamentId));
    if (!in_array($onlyPlayerId, $tournamentPlayerIds ?? [], true)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'player not in tournament'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        exit;
    }
    $targets = [$player];
} else {
    ['data' => $targets] = fetchData(fn() => Standing::activePlayersWithDetails($tournamentId));
    $targets = $targets ?? [];
}

// --- DM送信 ---
$counts = dispatchDmToTargets(
    $targets,
    function (array $p) use ($tournamentId, $roundNumber, $tournament) {
        $responseUrl = SITE_URL . '/schedule_response?tournament_id=' . $tournamentId
            . '&round_number=' . $roundNumber . '&player_id=' . (int) $p['id'];
        $embed = [
            'title' => '📅 ' . $roundNumber . '回戦 参加可能日アンケート',
            'description' => $tournament['name'],
            'color' => 0x9b8ce8,
            'url' => $responseUrl,
            'fields' => [
                [
                    'name' => '参加可能日の回答',
                    'value' => '下のURLから参加可能な日程を選択してください。',
                    'inline' => false,
                ],
                [
                    'name' => 'URL',
                    'value' => $responseUrl,
                    'inline' => false,
                ],
            ],
            'footer' => ['text' => SITE_NAME],
        ];
        $content = "📅 **{$tournament['name']}** {$roundNumber}回戦の参加可能日アンケートが届きました。\n"
            . "こちらから参加可能な日程を選択してください:\n"
            . $responseUrl;
        return ['embed' => $embed, 'content' => $content];
    }
);

echo json_encode(['ok' => true] + $counts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
