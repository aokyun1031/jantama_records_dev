<?php

declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();
ensureCsrfToken();

header('Content-Type: application/json; charset=utf-8');

// --- メソッド + CSRF + Turnstile 検証 ---
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method not allowed'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    exit;
}

// validatePost() が 403 を設定済み
$postError = validatePost();
if ($postError !== null) {
    echo json_encode(['ok' => false, 'error' => $postError], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
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
// 大会名は誰でも編集できるため、Markdown として解釈される箇所へは無害化して埋め込む
$safeName = discordEscape($tournament['name']);

$counts = dispatchDmToTargets(
    $targets,
    function (array $p) use ($tournamentId, $roundNumber, $safeName) {
        $responseUrl = SITE_URL . '/schedule_response?tournament_id=' . $tournamentId
            . '&round_number=' . $roundNumber . '&player_id=' . (int) $p['id'];
        $embed = [
            'title' => '📅 ' . $roundNumber . '回戦 参加可能日アンケート',
            'description' => $safeName,
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
        $content = "📅 **{$safeName}** {$roundNumber}回戦の参加可能日アンケートが届きました。\n"
            . "こちらから参加可能な日程を選択してください:\n"
            . $responseUrl;
        return ['embed' => $embed, 'content' => $content];
    }
);

// ここでは regenerateCsrfToken() を呼ばない。このエンドポイントはページ表示直後に裏で自動実行されるため、
// セッション ID とトークンを差し替えると、同じセッションで開いている他ページのフォームが 403 になる。
// 連続実行は送信ごとに必要な Turnstile トークン（1 回使い切り）で抑止する。
echo json_encode(['ok' => true] + $counts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
