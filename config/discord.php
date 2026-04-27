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
 * Discord Guild ID を返す。Scheduled Events API は Guild スコープ。
 */
function discordGuildId(): string
{
    return $_ENV['DISCORD_GUILD_ID'] ?? getenv('DISCORD_GUILD_ID') ?: '';
}

/**
 * Guild Scheduled Event を新規作成する。
 *
 * Bot に MANAGE_EVENTS 権限が必要。
 * https://discord.com/developers/docs/resources/guild-scheduled-event#create-guild-scheduled-event
 *
 * @param string $name 100 文字以内
 * @param string $startIso8601 UTC ISO8601（例: "2026-04-27T12:00:00Z"）
 * @param string $endIso8601   UTC ISO8601。EXTERNAL イベントでは必須
 * @param string $location     エンティティの場所（URL or テキスト、100 文字以内）
 * @param string $description  1000 文字以内
 * @return string|null 作成成功時は Discord Event ID。失敗・Token 未設定時は null
 */
function discordCreateScheduledEvent(
    string $name,
    string $startIso8601,
    string $endIso8601,
    string $location,
    string $description = ''
): ?string {
    $guildId = discordGuildId();
    if ($guildId === '' || !discordEnabled()) {
        return null;
    }

    $payload = [
        'name' => mb_substr($name, 0, 100),
        'description' => mb_substr($description, 0, 1000),
        'scheduled_start_time' => $startIso8601,
        'scheduled_end_time' => $endIso8601,
        'privacy_level' => 2,    // GUILD_ONLY
        'entity_type' => 3,      // EXTERNAL
        'entity_metadata' => ['location' => mb_substr($location, 0, 100)],
    ];

    $res = discordRequest('POST', '/guilds/' . $guildId . '/scheduled-events', $payload);
    if (!$res || $res['status'] >= 400 || empty($res['body']['id'])) {
        error_log('[Discord] create scheduled event failed: status=' . ($res['status'] ?? '-'));
        return null;
    }
    return (string) $res['body']['id'];
}

/**
 * 既存の Guild Scheduled Event を更新する。
 *
 * @return bool 成功時 true。失敗・Token 未設定・event_id 不正時 false
 */
function discordPatchScheduledEvent(
    string $eventId,
    string $name,
    string $startIso8601,
    string $endIso8601,
    string $location,
    string $description = ''
): bool {
    $guildId = discordGuildId();
    if ($guildId === '' || $eventId === '' || !discordEnabled()) {
        return false;
    }

    $payload = [
        'name' => mb_substr($name, 0, 100),
        'description' => mb_substr($description, 0, 1000),
        'scheduled_start_time' => $startIso8601,
        'scheduled_end_time' => $endIso8601,
        'entity_metadata' => ['location' => mb_substr($location, 0, 100)],
    ];

    $res = discordRequest('PATCH', '/guilds/' . $guildId . '/scheduled-events/' . $eventId, $payload);
    if (!$res || $res['status'] >= 400) {
        error_log('[Discord] patch scheduled event failed: event=' . $eventId . ' status=' . ($res['status'] ?? '-'));
        return false;
    }
    return true;
}

/**
 * アナウンス用チャンネル ID を返す。未設定なら空文字。
 */
function discordAnnounceChannelId(): string
{
    return $_ENV['DISCORD_ANNOUNCE_CHANNEL_ID'] ?? getenv('DISCORD_ANNOUNCE_CHANNEL_ID') ?: '';
}

/**
 * 指定チャンネルへ Bot がプレーンテキストメッセージを投稿する。
 *
 * Bot に対象チャンネルの View Channel + Send Messages 権限が必要。
 * 失敗・Token 未設定時は false を返すのみで例外は投げない。
 */
function discordPostChannelMessage(string $channelId, string $content): bool
{
    if ($channelId === '' || $content === '' || !discordEnabled()) {
        return false;
    }

    $res = discordRequest('POST', '/channels/' . $channelId . '/messages', ['content' => $content]);
    if (!$res || $res['status'] >= 400) {
        error_log('[Discord] post channel message failed: channel=' . $channelId . ' status=' . ($res['status'] ?? '-'));
        return false;
    }
    return true;
}

/**
 * 卓 1 件の Discord Scheduled Event を同期する。
 *
 * 呼び出し側は対局日（played_date / played_time）を保存した直後にこれを呼ぶだけでよい。
 * Token / Guild ID 未設定（= ローカル開発）では何もしない。
 *
 * 動作:
 *   - 既存マッピング無し                       → CREATE + 「決定」通知
 *   - 既存マッピング有り + 旧日時と異なる      → PATCH + 「変更」通知
 *   - 既存マッピング有り + 旧日時と同じ        → PATCH のみ（投稿なし）
 *   - 開始時刻が現在以前                       → no-op（Discord は未来時刻必須）
 *   - API 失敗時はマッピングを変更せず error_log のみ（致命的にしない）
 *
 * 呼び出し元（table.php）が played_date / played_time を必須バリデーション済み前提。
 *
 * @param string|null $previousDate 更新前の played_date（変更検知用、未指定なら通知しない）
 * @param string|null $previousTime 更新前の played_time（同上）
 */
function syncDiscordTableEvent(int $tableId, ?string $previousDate = null, ?string $previousTime = null): void
{
    if (!discordEnabled() || discordGuildId() === '') {
        return;
    }

    $table = TableInfo::findWithPlayers($tableId);
    if (!$table) {
        return;
    }

    $playedDate = (string) ($table['played_date'] ?? '');
    $playedTime = (string) ($table['played_time'] ?? '');
    $tournament = Tournament::findWithMeta((int) $table['tournament_id']);
    if (!$tournament) {
        return;
    }

    // JST → UTC に変換。+3h を終了時刻とする
    try {
        $tz = new DateTimeZone('Asia/Tokyo');
        $start = new DateTimeImmutable($playedDate . ' ' . $playedTime . ':00', $tz);
        $end = $start->modify('+3 hours');
        $startIso = $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        $endIso = $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    } catch (Exception $e) {
        error_log('[Discord] sync table event date parse failed: table=' . $tableId);
        return;
    }

    // 過去時刻は Discord 側で 400（must be a future date）になる。
    if ($start <= new DateTimeImmutable('now')) {
        return;
    }

    $existing = DiscordScheduledEvent::find(DiscordScheduledEvent::ENTITY_TABLE, $tableId);

    // 表示要素を組み立てる
    // - $playerNames: 素のテキスト名（イベント description 用、メンションは押下不可なので名前で）
    // - $playerTokens: <@id> or 名前（チャンネル投稿用、連携済みはメンションして通知を飛ばす）
    $playerNames = [];
    $playerTokens = [];
    foreach ($table['players'] ?? [] as $p) {
        $name = (string) ($p['nickname'] ?? $p['name'] ?? '');
        if ($name !== '') {
            $playerNames[] = $name;
        }
        $discordId = (string) ($p['discord_user_id'] ?? '');
        if ($discordId !== '') {
            $playerTokens[] = '<@' . $discordId . '>';
        } elseif ($name !== '') {
            $playerTokens[] = $name;
        }
    }
    $ruleTags = buildRuleTags($tournament['meta'] ?? []);
    $dayLabel = (string) ($table['day_of_week'] ?? '');
    $dayShort = $dayLabel !== '' ? mb_substr($dayLabel, 0, 1) : '';

    $name = $tournament['name'] . ' ' . $table['round_number'] . '回戦 ' . $table['table_name'];
    $location = SITE_URL . '/table?id=' . $tableId;
    $description = buildDiscordTableEventDescription($ruleTags, $playerNames);

    if ($existing) {
        $patched = discordPatchScheduledEvent(
            $existing['discord_event_id'],
            $name,
            $startIso,
            $endIso,
            $location,
            $description
        );

        // 旧日時が分かっており、かつ実際に変更があった場合のみ「変更」通知を投稿
        $hasPrev = $previousDate !== null && $previousTime !== null;
        $isChanged = $hasPrev && ($previousDate !== $playedDate || $previousTime !== $playedTime);
        if ($patched && $isChanged) {
            $announceChannelId = discordAnnounceChannelId();
            if ($announceChannelId !== '') {
                $announceContent = buildDiscordTableUpdateAnnouncement(
                    $tournament,
                    $table,
                    $previousDate ?? '',
                    $previousTime ?? '',
                    $start,
                    $dayShort,
                    $playerTokens,
                    $existing['discord_event_id']
                );
                discordPostChannelMessage($announceChannelId, $announceContent);
            }
        }
        return;
    }

    $eventId = discordCreateScheduledEvent($name, $startIso, $endIso, $location, $description);
    if ($eventId === null) {
        return;
    }
    DiscordScheduledEvent::upsert(
        DiscordScheduledEvent::ENTITY_TABLE,
        $tableId,
        $eventId,
        discordGuildId()
    );

    // 新規作成時のみ「決定」通知を投稿
    $announceChannelId = discordAnnounceChannelId();
    if ($announceChannelId !== '') {
        $announceContent = buildDiscordTableAnnouncement(
            $tournament,
            $table,
            $start,
            $dayShort,
            $playerTokens,
            $eventId
        );
        discordPostChannelMessage($announceChannelId, $announceContent);
    }
}

/**
 * Discord イベント description を組み立てる（イベントカード上に表示される）。
 *
 * イベントカード自体が「タイトル / 日時 / 場所URL」を表示するので、
 * description にはそれらと重複しない「参加選手」「ルール」のみ載せる。
 * 1000 文字以内（discordCreateScheduledEvent 側で切り詰める）。
 *
 * @param string[] $ruleTags
 * @param string[] $playerNames
 */
function buildDiscordTableEventDescription(array $ruleTags, array $playerNames): string
{
    $sections = [];
    if (!empty($playerNames)) {
        $sections[] = "**参加選手**\n" . implode(' / ', $playerNames);
    }
    if (!empty($ruleTags)) {
        $sections[] = "**ルール**\n" . implode(' / ', $ruleTags);
    }
    return implode("\n\n", $sections);
}

/**
 * 旧 played_date / played_time から「m/d(曜) HH:MM」形式の表示文字列を作る。
 * パース失敗・空入力時は素のテキスト連結にフォールバック。
 */
function formatDiscordPreviousSchedule(string $previousDate, string $previousTime): string
{
    if ($previousDate === '' && $previousTime === '') {
        return '未設定';
    }
    try {
        $tz = new DateTimeZone('Asia/Tokyo');
        if ($previousDate !== '' && preg_match('/^\d{2}:\d{2}$/', $previousTime)) {
            $dt = new DateTimeImmutable($previousDate . ' ' . $previousTime . ':00', $tz);
            $day = (int) $dt->format('w');
            $names = ['日', '月', '火', '水', '木', '金', '土'];
            return $dt->format('n/j') . '(' . $names[$day] . ') ' . $dt->format('H:i');
        }
    } catch (Exception $e) {
        // フォールバックへ
    }
    return trim($previousDate . ' ' . $previousTime);
}

/**
 * 卓完了時の対局結果をアナウンスチャンネルに投稿する。
 *
 * 含む情報:
 *   - 大会名 / ラウンド / 卓名
 *   - メンバーのメンション（連携済）または名前
 *   - 局ごとの牌譜URL + 順位付きスコア
 *   - 複数局の場合は合計順位
 *
 * Token / channel_id 未設定なら no-op。table.php の game_data / bulk アクションから呼ぶ。
 */
function announceDiscordTableResult(int $tableId): void
{
    $channelId = discordAnnounceChannelId();
    if ($channelId === '' || !discordEnabled()) {
        return;
    }

    $table = TableInfo::findWithPlayers($tableId);
    if (!$table) {
        return;
    }

    $tournament = Tournament::findWithMeta((int) $table['tournament_id']);
    if (!$tournament) {
        return;
    }

    $paifuMap = [];
    foreach (TablePaifuUrl::byTable($tableId) as $pu) {
        $paifuMap[(int) $pu['game_number']] = (string) $pu['url'];
    }

    $content = buildDiscordTableResultAnnouncement($tournament, $table, $paifuMap);
    discordPostChannelMessage($channelId, $content);
}

/**
 * スコアを「+25.0」形式に整形する（負数は -25.0）。
 */
function formatDiscordScore(float $score): string
{
    $formatted = number_format($score, 1, '.', '');
    return $score >= 0 ? '+' . $formatted : $formatted;
}

/**
 * 対局結果メッセージを組み立てる。
 *
 * @param array<string, mixed> $tournament
 * @param array<string, mixed> $table  TableInfo::findWithPlayers の戻り値（players + game_scores 含む）
 * @param array<int, string>   $paifuMap  game_number => url
 */
function buildDiscordTableResultAnnouncement(array $tournament, array $table, array $paifuMap): string
{
    $players = $table['players'] ?? [];
    $gameScores = $table['game_scores'] ?? [];
    $tableUrl = SITE_URL . '/table?id=' . (int) $table['id'];

    $tokens = [];
    $nameById = [];
    foreach ($players as $p) {
        $name = (string) ($p['nickname'] ?? $p['name'] ?? '');
        $nameById[(int) $p['player_id']] = $name;
        $discordId = (string) ($p['discord_user_id'] ?? '');
        if ($discordId !== '') {
            $tokens[] = '<@' . $discordId . '>';
        } elseif ($name !== '') {
            $tokens[] = $name;
        }
    }

    $title = $tournament['name'] . ' ' . $table['round_number'] . '回戦 ' . $table['table_name'];

    $lines = [];
    $lines[] = '## 🏁 対局結果';
    $lines[] = '';
    $lines[] = '**' . $title . '**';
    if (!empty($tokens)) {
        $lines[] = implode(' ', $tokens);
    }

    $gameCount = count($gameScores);
    ksort($gameScores);
    foreach ($gameScores as $gameNumber => $scores) {
        $heading = $gameCount > 1 ? $gameNumber . '局目' : '結果';
        if (!empty($paifuMap[$gameNumber])) {
            $heading .= ' — [牌譜](' . $paifuMap[$gameNumber] . ')';
        }
        $lines[] = '';
        $lines[] = '### ' . $heading;

        arsort($scores);
        $rank = 1;
        foreach ($scores as $playerId => $score) {
            $name = $nameById[(int) $playerId] ?? '?';
            $lines[] = $rank . '位 ' . $name . ' ' . formatDiscordScore((float) $score);
            $rank++;
        }
    }

    if ($gameCount > 1) {
        $lines[] = '';
        $lines[] = '### 合計';
        $sortedPlayers = $players;
        usort($sortedPlayers, fn($a, $b) => (float) ($b['score'] ?? 0) <=> (float) ($a['score'] ?? 0));
        foreach ($sortedPlayers as $i => $p) {
            $name = (string) ($p['nickname'] ?? $p['name'] ?? '?');
            $lines[] = ($i + 1) . '位 ' . $name . ' ' . formatDiscordScore((float) ($p['score'] ?? 0));
        }
    }

    $lines[] = '';
    $lines[] = $tableUrl;

    return implode("\n", $lines);
}

/**
 * 指定ラウンドの卓割りをアナウンスチャンネルに投稿する。
 *
 * 各卓ごとに 1 通投稿し、メンバーをメンションする。Discord 未連携選手は名前のみ表示。
 * メッセージはメンバーに「対局日時を決めて卓ページから登録する」よう案内する。
 *
 * Token / channel_id 未設定なら no-op。
 */
function announceDiscordRoundTablesAssigned(int $tournamentId, int $roundNumber): void
{
    $channelId = discordAnnounceChannelId();
    if ($channelId === '' || !discordEnabled()) {
        return;
    }

    $tournament = Tournament::findWithMeta($tournamentId);
    if (!$tournament) {
        return;
    }

    $tables = TableInfo::byRoundForAnnounce($tournamentId, $roundNumber);
    if (empty($tables)) {
        return;
    }

    foreach ($tables as $table) {
        $content = buildDiscordTableAssignment($tournament, $roundNumber, $table);
        discordPostChannelMessage($channelId, $content);
        // チャンネル投稿のレート制限（5 msg / 5sec 程度）に余裕を持たせる
        usleep(200000);
    }
}

/**
 * 卓割り 1 卓分の告知メッセージを組み立てる。
 *
 * メンバーは Discord 連携済みなら <@id> でメンション、未連携なら名前を表示。
 *
 * @param array<string, mixed> $tournament
 * @param array{table_id:int, table_name:string, players:array<int, array{player_id:int, name:string, nickname:?string, discord_user_id:?string}>} $table
 */
function buildDiscordTableAssignment(array $tournament, int $roundNumber, array $table): string
{
    $tableUrl = SITE_URL . '/table?id=' . $table['table_id'];
    $memberTokens = [];
    foreach ($table['players'] as $p) {
        $discordId = (string) ($p['discord_user_id'] ?? '');
        if ($discordId !== '') {
            $memberTokens[] = '<@' . $discordId . '>';
        } else {
            $memberTokens[] = (string) ($p['nickname'] ?? $p['name'] ?? '');
        }
    }

    $lines = [];
    $lines[] = '## 🪑 ' . $tournament['name'] . ' ' . $roundNumber . '回戦 — 卓割り発表';
    $lines[] = '';
    $lines[] = '**' . $table['table_name'] . '**';
    $lines[] = implode(' ', $memberTokens);
    $lines[] = '';
    $lines[] = 'メンバー間で **対局日時** を相談のうえ、卓ページから登録してください。';
    $lines[] = '';
    $lines[] = $tableUrl;

    return implode("\n", $lines);
}

/**
 * 大会完了（優勝者発表）をアナウンスチャンネルに投稿する。
 *
 * 上位 3 名（金銀銅）を表示し、優勝者は連携済みなら <@id> でメンション。
 * Token / channel_id 未設定なら no-op。interview_edit.php の complete アクションから呼ぶ。
 */
function announceDiscordTournamentCompleted(int $tournamentId): void
{
    $channelId = discordAnnounceChannelId();
    if ($channelId === '' || !discordEnabled()) {
        return;
    }

    $tournament = Tournament::findWithMeta($tournamentId);
    if (!$tournament) {
        return;
    }

    $standings = Standing::all($tournamentId);
    if (empty($standings)) {
        return;
    }

    $content = buildDiscordTournamentCompletedAnnouncement($tournament, $standings);
    discordPostChannelMessage($channelId, $content);
}

/**
 * 大会完了告知メッセージを組み立てる。
 *
 * @param array<string, mixed> $tournament Tournament::findWithMeta 結果
 * @param array<int, array<string, mixed>> $standings Standing::all 結果（total 降順想定）
 */
function buildDiscordTournamentCompletedAnnouncement(array $tournament, array $standings): string
{
    $url = SITE_URL . '/tournament_view?id=' . (int) $tournament['id'];
    $top3 = array_slice($standings, 0, 3);
    $medals = ['🥇', '🥈', '🥉'];

    $lines = [];
    $lines[] = '# 🏆 大会終了 — 優勝者発表！';
    $lines[] = '';
    $lines[] = '## **' . $tournament['name'] . '**';
    $lines[] = '';
    $lines[] = '> 接戦の末、頂点を掴んだのは──';
    $lines[] = '';

    foreach ($top3 as $i => $s) {
        $medal = $medals[$i] ?? (($i + 1) . '位');
        $name = (string) ($s['nickname'] ?? $s['name'] ?? '');
        $total = formatDiscordScore((float) ($s['total'] ?? 0));
        $discordId = (string) ($s['discord_user_id'] ?? '');

        if ($i === 0) {
            // 優勝者は太字 + 連携済ならメンション
            if ($discordId !== '') {
                $lines[] = $medal . ' **<@' . $discordId . '>** (' . $name . ') ' . $total . 'pt';
            } else {
                $lines[] = $medal . ' **' . $name . '** ' . $total . 'pt';
            }
        } else {
            $lines[] = $medal . ' ' . $name . ' ' . $total . 'pt';
        }
    }

    $lines[] = '';
    $lines[] = '参加された皆さん、お疲れ様でした！';
    $lines[] = '';
    $lines[] = $url;

    return implode("\n", $lines);
}

/**
 * 大会作成「告知」をアナウンスチャンネルに投稿する。
 *
 * Token / channel_id 未設定なら no-op。tournament_new.php から呼ぶ。
 */
function announceDiscordTournamentCreated(int $tournamentId): void
{
    $channelId = discordAnnounceChannelId();
    if ($channelId === '' || !discordEnabled()) {
        return;
    }

    $tournament = Tournament::findWithMeta($tournamentId);
    if (!$tournament) {
        return;
    }

    $content = buildDiscordTournamentAnnouncement($tournament);
    discordPostChannelMessage($channelId, $content);
}

/**
 * 大会告知メッセージを組み立てる。
 *
 * @param array<string, mixed> $tournament Tournament::findWithMeta 結果
 */
function buildDiscordTournamentAnnouncement(array $tournament): string
{
    $ruleTags = buildRuleTags($tournament['meta'] ?? []);
    $url = SITE_URL . '/tournament_view?id=' . (int) $tournament['id'];

    $lines = [];
    $lines[] = '# 🏆 新大会、開催決定！';
    $lines[] = '';
    $lines[] = '## **' . $tournament['name'] . '**';
    $lines[] = '';
    $lines[] = '> 雀士たちよ、卓へ集え！';
    $lines[] = '> 新たな闘いの幕が上がります。';
    if (!empty($ruleTags)) {
        $lines[] = '';
        $lines[] = '**ルール**';
        $lines[] = implode(' / ', $ruleTags);
    }
    $lines[] = '';
    $lines[] = '参加表明は Bot からの DM をお待ちください。';
    $lines[] = '';
    $lines[] = $url;

    return implode("\n", $lines);
}

/**
 * 「m/d(曜) HH:MM」形式に整形する。
 */
function formatDiscordSchedule(DateTimeImmutable $start, string $dayShort): string
{
    return $start->format('n/j')
        . ($dayShort !== '' ? "({$dayShort})" : '')
        . ' ' . $start->format('H:i');
}

/**
 * 対局日「変更」通知メッセージを組み立てる。
 *
 * @param array<string, mixed> $tournament
 * @param array<string, mixed> $table
 * @param string[] $playerTokens 各要素は <@discord_id> もしくは素のテキスト名
 */
function buildDiscordTableUpdateAnnouncement(
    array $tournament,
    array $table,
    string $previousDate,
    string $previousTime,
    DateTimeImmutable $start,
    string $dayShort,
    array $playerTokens,
    string $eventId
): string {
    $title = $tournament['name'] . ' ' . $table['round_number'] . '回戦 ' . $table['table_name'];
    $oldPretty = formatDiscordPreviousSchedule($previousDate, $previousTime);
    $newPretty = formatDiscordSchedule($start, $dayShort);
    $eventUrl = 'https://discord.com/events/' . discordGuildId() . '/' . $eventId;

    $lines = [];
    $lines[] = '## 🔄 対局日変更';
    $lines[] = '';
    $lines[] = '**' . $title . '**';
    if (!empty($playerTokens)) {
        $lines[] = implode(' ', $playerTokens);
    }
    $lines[] = '';
    $lines[] = '- 旧日時: ' . $oldPretty;
    $lines[] = '- 新日時: ' . $newPretty;
    $lines[] = '';
    $lines[] = $eventUrl;

    return implode("\n", $lines);
}

/**
 * 対局日「決定」通知メッセージを組み立てる。
 *
 * @param array<string, mixed> $tournament
 * @param array<string, mixed> $table
 * @param string[] $playerTokens 各要素は <@discord_id> もしくは素のテキスト名
 */
function buildDiscordTableAnnouncement(
    array $tournament,
    array $table,
    DateTimeImmutable $start,
    string $dayShort,
    array $playerTokens,
    string $eventId
): string {
    $title = $tournament['name'] . ' ' . $table['round_number'] . '回戦 ' . $table['table_name'];
    $datePretty = formatDiscordSchedule($start, $dayShort);
    $eventUrl = 'https://discord.com/events/' . discordGuildId() . '/' . $eventId;

    $lines = [];
    $lines[] = '## 🀄 対局日決定';
    $lines[] = '';
    $lines[] = '**' . $title . '**';
    if (!empty($playerTokens)) {
        $lines[] = implode(' ', $playerTokens);
    }
    $lines[] = '';
    $lines[] = '- 日時: ' . $datePretty;
    $lines[] = '';
    $lines[] = $eventUrl;

    return implode("\n", $lines);
}

/**
 * 指定 Discord ユーザーへ Embed 形式で DM を送る。
 *
 * @param array<string, mixed> $embed Discord Embed 構造体
 *   title, description, color, url, fields, footer など
 *   https://discord.com/developers/docs/resources/channel#embed-object
 */
function discordSendDmEmbed(string $userId, array $embed, string $content = ''): bool
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
    // content も併送する。クライアント設定で Embed 非表示の選手にも本文が届くようにする。
    $payload = ['embeds' => [$embed]];
    if ($content !== '') {
        $payload['content'] = $content;
    }
    $msg = discordRequest('POST', '/channels/' . $channelId . '/messages', $payload);
    if (!$msg || $msg['status'] >= 400) {
        error_log('[Discord] send embed failed: user=' . $userId . ' status=' . ($msg['status'] ?? '-'));
        return false;
    }

    return true;
}
