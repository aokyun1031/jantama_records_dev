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
// 大会名・ルール値は誰でも編集できるため、Markdown として解釈される箇所へは無害化して埋め込む
$ruleTags = array_map('discordEscape', buildRuleTags($tournament['meta']));
$ruleSummary = implode(' / ', $ruleTags);
$safeName = discordEscape($tournament['name']);

$counts = dispatchDmToTargets(
    $targets,
    function (array $p) use ($tournamentId, $tournament, $ruleSummary, $safeName) {
        $playerId = (int) $p['id'];
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
        $content = "🀄 **{$safeName}** の参加表明URLが届きました。\n"
            . "こちらから参加 / 不参加 を選択してください:\n"
            . $joinUrl;
        return ['embed' => $embed, 'content' => $content];
    },
    fn(int $playerId, string $status) => Tournament::recordDmDispatch($tournamentId, $playerId, $status)
);

// ここでは regenerateCsrfToken() を呼ばない。このエンドポイントはページ表示直後に裏で自動実行されるため、
// セッション ID とトークンを差し替えると、同じセッションで開いている他ページのフォームが 403 になる。
// 連続実行は送信ごとに必要な Turnstile トークン（1 回使い切り）で抑止する。
echo json_encode(['ok' => true] + $counts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
