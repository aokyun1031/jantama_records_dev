<?php

declare(strict_types=1);

/**
 * 404レスポンスを返して処理を終了する。
 */
function abort404(): never
{
    http_response_code(404);
    require __DIR__ . '/../public/404.php';
    exit;
}

/**
 * GETパラメータからプレイヤーIDを検証・取得する。無効な場合は404。
 */
function requirePlayerId(): int
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id === false || $id === null || $id <= 0) {
        abort404();
    }
    return $id;
}

/**
 * プレイヤーをIDで取得する。見つからない場合は404。
 *
 * @return array{id: int, name: string, nickname: ?string, character_id: ?int, character_icon: ?string}
 */
function requirePlayer(int $id): array
{
    ['data' => $player] = fetchData(fn() => Player::find($id));
    if (!$player) {
        abort404();
    }
    return $player;
}

/**
 * GETパラメータから大会IDを検証・取得する。無効な場合は404。
 */
function requireTournamentId(): int
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id === false || $id === null || $id <= 0) {
        abort404();
    }
    return $id;
}

/**
 * 大会をメタ情報付きで取得する。見つからない場合は404。
 */
function requireTournamentWithMeta(int $id): array
{
    ['data' => $tournament] = fetchData(fn() => Tournament::findWithMeta($id));
    if (!$tournament) {
        abort404();
    }
    return $tournament;
}

/**
 * フラッシュメッセージを取得して消費する。
 */
function consumeFlash(): ?string
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
