<?php

declare(strict_types=1);

/**
 * Discord 連携ヘルパー。
 *
 * 公式 Bot API を叩いて選手の Discord ユーザーへ DM を送る等の薄いラッパー。
 * Token 未設定（= ローカル開発）ではすべての送信処理を no-op とし、エラーを出さない。
 *
 * 公式ドキュメント: https://discord.com/developers/docs/resources/user#create-dm
 */

const DISCORD_API_BASE = 'https://discord.com/api/v10';

/**
 * Discord Bot Token を返す。未設定なら空文字。
 */
function discordBotToken(): string
{
    return $_ENV['DISCORD_BOT_TOKEN'] ?? getenv('DISCORD_BOT_TOKEN') ?: '';
}

/**
 * Discord Public Key（Interactions Endpoint 署名検証用）を返す。
 */
function discordPublicKey(): string
{
    return $_ENV['DISCORD_PUBLIC_KEY'] ?? getenv('DISCORD_PUBLIC_KEY') ?: '';
}

/**
 * Discord 連携が有効かどうか。Token 未設定なら false。
 */
function discordEnabled(): bool
{
    return discordBotToken() !== '';
}

/**
 * Discord API へ認証付きリクエストを送る。
 *
 * @param string $method HTTP メソッド
 * @param string $path  /users/@me/channels 等の API パス
 * @param array<string, mixed>|null $body JSON ボディ
 * @return array{status:int, body:?array}|null Token 未設定時は null
 */
function discordRequest(string $method, string $path, ?array $body = null): ?array
{
    $token = discordBotToken();
    if ($token === '') {
        return null;
    }

    $headers = [
        'Authorization: Bot ' . $token,
        'User-Agent: JantamaRecordsBot (https://jantama-records.onrender.com, 1.0)',
    ];
    $opts = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ];
    if ($body !== null) {
        $opts['http']['header'] .= "\r\nContent-Type: application/json";
        $opts['http']['content'] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    $url = DISCORD_API_BASE . $path;
    $response = @file_get_contents($url, false, stream_context_create($opts));
    if ($response === false) {
        error_log('[Discord] request failed: ' . $method . ' ' . $path);
        return ['status' => 0, 'body' => null];
    }

    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    $decoded = json_decode($response, true);
    return ['status' => $status, 'body' => is_array($decoded) ? $decoded : null];
}

/**
 * 指定 Discord ユーザーへ DM を送る。
 *
 * 失敗時 / Token 未設定時 / DM 拒否設定時 は false を返すのみで例外は投げない。
 * 呼び出し側（大会作成時通知等）は送信失敗を致命的にしない設計とする。
 */
function discordSendDm(string $userId, string $content): bool
{
    if ($userId === '' || $content === '' || !discordEnabled()) {
        return false;
    }

    $dm = discordRequest('POST', '/users/@me/channels', ['recipient_id' => $userId]);
    if (!$dm || $dm['status'] >= 400 || empty($dm['body']['id'])) {
        error_log('[Discord] open DM failed: user=' . $userId . ' status=' . ($dm['status'] ?? '-'));
        return false;
    }

    $channelId = (string) $dm['body']['id'];
    $msg = discordRequest('POST', '/channels/' . $channelId . '/messages', ['content' => $content]);
    if (!$msg || $msg['status'] >= 400) {
        error_log('[Discord] send message failed: user=' . $userId . ' status=' . ($msg['status'] ?? '-'));
        return false;
    }

    return true;
}

/**
 * OAuth2 Client Secret を返す。
 */
function discordClientSecret(): string
{
    return $_ENV['DISCORD_CLIENT_SECRET'] ?? getenv('DISCORD_CLIENT_SECRET') ?: '';
}

/**
 * OAuth2 Redirect URI を返す。
 */
function discordOauthRedirectUri(): string
{
    return $_ENV['DISCORD_OAUTH_REDIRECT_URI'] ?? getenv('DISCORD_OAUTH_REDIRECT_URI') ?: '';
}

/**
 * APP_SECRET（state HMAC 署名用）を返す。
 */
function discordAppSecret(): string
{
    return $_ENV['APP_SECRET'] ?? getenv('APP_SECRET') ?: '';
}

/**
 * OAuth state を生成する。形式: base64url(player_id.expires.hmac)
 * 有効期限 10 分。
 */
function discordOauthSignState(int $playerId): string
{
    $expires = time() + 600;
    $payload = $playerId . '.' . $expires;
    $hmac = hash_hmac('sha256', $payload, discordAppSecret());
    $shortHmac = substr($hmac, 0, 32);
    return rtrim(strtr(base64_encode($payload . '.' . $shortHmac), '+/', '-_'), '=');
}

/**
 * OAuth state を検証して player_id を返す。失敗時 null。
 */
function discordOauthVerifyState(string $state): ?int
{
    $decoded = base64_decode(strtr($state, '-_', '+/'), true);
    if ($decoded === false) {
        return null;
    }
    $parts = explode('.', $decoded);
    if (count($parts) !== 3) {
        return null;
    }
    [$playerIdStr, $expiresStr, $providedHmac] = $parts;
    $playerId = (int) $playerIdStr;
    $expires = (int) $expiresStr;
    if ($playerId <= 0 || $expires < time()) {
        return null;
    }
    $expectedHmac = substr(hash_hmac('sha256', $playerId . '.' . $expires, discordAppSecret()), 0, 32);
    if (!hash_equals($expectedHmac, $providedHmac)) {
        return null;
    }
    return $playerId;
}

/**
 * OAuth2 認可URL を生成する。
 */
function discordOauthAuthorizeUrl(int $playerId): string
{
    $params = http_build_query([
        'client_id' => $_ENV['DISCORD_APPLICATION_ID'] ?? getenv('DISCORD_APPLICATION_ID') ?: '',
        'redirect_uri' => discordOauthRedirectUri(),
        'response_type' => 'code',
        'scope' => 'identify',
        'state' => discordOauthSignState($playerId),
        'prompt' => 'none',
    ]);
    return 'https://discord.com/oauth2/authorize?' . $params;
}

/**
 * 認可コードをアクセストークンに交換する。
 *
 * @return array{access_token:string, token_type:string, expires_in:int, refresh_token?:string, scope:string}|null
 */
function discordOauthExchangeCode(string $code): ?array
{
    $appId = $_ENV['DISCORD_APPLICATION_ID'] ?? getenv('DISCORD_APPLICATION_ID') ?: '';
    $secret = discordClientSecret();
    $redirect = discordOauthRedirectUri();
    if ($appId === '' || $secret === '' || $redirect === '') {
        error_log('[Discord] OAuth config missing');
        return null;
    }

    $body = http_build_query([
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirect,
        'client_id' => $appId,
        'client_secret' => $secret,
    ]);

    $response = @file_get_contents(DISCORD_API_BASE . '/oauth2/token', false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json",
            'content' => $body,
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]));

    if ($response === false) {
        error_log('[Discord] OAuth token exchange failed (network)');
        return null;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded) || empty($decoded['access_token'])) {
        error_log('[Discord] OAuth token exchange failed: ' . substr($response, 0, 200));
        return null;
    }
    return $decoded;
}

/**
 * アクセストークンで Discord User 情報を取得する。
 *
 * @return array{id:string, username:string, global_name?:string, ...}|null
 */
function discordOauthFetchUser(string $accessToken): ?array
{
    $response = @file_get_contents(DISCORD_API_BASE . '/users/@me', false, stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'Authorization: Bearer ' . $accessToken,
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]));
    if ($response === false) {
        return null;
    }
    $decoded = json_decode($response, true);
    return is_array($decoded) && !empty($decoded['id']) ? $decoded : null;
}

/**
 * 指定 Discord ユーザーへ Embed 形式で DM を送る。
 *
 * @param array<string, mixed> $embed Discord Embed 構造体
 *   title, description, color, url, fields, footer など
 *   https://discord.com/developers/docs/resources/channel#embed-object
 */
function discordSendDmEmbed(string $userId, array $embed): bool
{
    if ($userId === '' || !discordEnabled()) {
        return false;
    }

    $dm = discordRequest('POST', '/users/@me/channels', ['recipient_id' => $userId]);
    if (!$dm || $dm['status'] >= 400 || empty($dm['body']['id'])) {
        error_log('[Discord] open DM failed: user=' . $userId . ' status=' . ($dm['status'] ?? '-'));
        return false;
    }

    $channelId = (string) $dm['body']['id'];
    $msg = discordRequest('POST', '/channels/' . $channelId . '/messages', ['embeds' => [$embed]]);
    if (!$msg || $msg['status'] >= 400) {
        error_log('[Discord] send embed failed: user=' . $userId . ' status=' . ($msg['status'] ?? '-'));
        return false;
    }

    return true;
}
