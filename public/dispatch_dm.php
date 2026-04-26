<?php

declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();

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
$onlyPlayerId = filter_input(INPUT_POST, 'player_id', FILTER_VALIDATE_INT);

if (!$tournamentId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'tournament_id required'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
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

// --- 送信対象決定 ---
if ($onlyPlayerId) {
    ['data' => $player] = fetchData(fn() => Player::find($onlyPlayerId));
    if (!$player) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'player not found'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        exit;
    }
    $targets = [$player];
} else {
    ['data' => $targets] = fetchData(fn() => Tournament::dmTargets($tournamentId));
    $targets = $targets ?? [];
}

// --- DM送信 ---
$counts = ['sent' => 0, 'failed' => 0, 'no_discord_id' => 0];
$ruleTags = buildRuleTags($tournament['meta']);
$ruleSummary = implode(' / ', $ruleTags);

foreach ($targets as $p) {
    $playerId = (int) $p['id'];
    $discordUserId = (string) ($p['discord_user_id'] ?? '');

    if ($discordUserId === '') {
        Tournament::recordDmDispatch($tournamentId, $playerId, 'no_discord_id');
        $counts['no_discord_id']++;
        continue;
    }

    $joinUrl = SITE_URL . '/tournament_join?tournament_id=' . $tournamentId . '&player_id=' . $playerId;
    $embed = [
        'title' => '🀄 ' . $tournament['name'],
        'description' => $ruleSummary !== '' ? 'ルール: ' . $ruleSummary : '',
        'color' => 0x9b8ce8,
        'url' => $joinUrl,
        'fields' => [
            [
                'name' => '参加表明',
                'value' => '下のボタンから参加 / 不参加 を選択してください。',
                'inline' => false,
            ],
            [
                'name' => 'URL',
                'value' => $joinUrl,
                'inline' => false,
            ],
        ],
        'footer' => ['text' => SITE_NAME],
    ];

    // クライアントで Embed 非表示の選手にも本文が届くように content も併送する
    $content = "🀄 **{$tournament['name']}** の参加表明URLが届きました。\n"
        . "こちらから参加 / 不参加 を選択してください:\n"
        . $joinUrl;

    $ok = discordSendDmEmbed($discordUserId, $embed, $content);
    Tournament::recordDmDispatch($tournamentId, $playerId, $ok ? 'sent' : 'failed');
    $counts[$ok ? 'sent' : 'failed']++;

    // Discord レート制限 (50 req/sec) 余裕を持って 100ms 間隔
    usleep(100000);
}

echo json_encode(['ok' => true] + $counts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
