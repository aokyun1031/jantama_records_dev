<?php

declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();

/**
 * Discord OAuth2 認可コードフローのコールバック。
 *
 * 想定フロー:
 *   1. player_edit ページの「Discord と連携」リンクが /oauth2/authorize へ遷移
 *   2. ユーザーが Discord 上で「認証」 → このページに code, state 付きで戻る
 *   3. state を検証して player_id を復元
 *   4. code → access_token 交換 → /users/@me で Discord ユーザー情報取得
 *   5. players.discord_user_id / discord_username 保存
 *   6. flash + player_edit へリダイレクト
 */

$code = filter_input(INPUT_GET, 'code');
$state = filter_input(INPUT_GET, 'state');
$error = filter_input(INPUT_GET, 'error');

// ユーザーが認証画面でキャンセルした場合
if ($error) {
    $playerId = $state ? discordOauthVerifyState((string) $state) : null;
    $_SESSION['flash'] = 'Discord 連携をキャンセルしました。';
    header('Location: ' . ($playerId ? "player_edit?id={$playerId}" : 'players'));
    exit;
}

if (!$code || !$state) {
    abort404();
}

$playerId = discordOauthVerifyState((string) $state);
if ($playerId === null) {
    abort404();
}

['data' => $player] = fetchData(fn() => Player::find($playerId));
if (!$player) {
    abort404();
}

// --- code を access_token に交換 ---
$tokenData = discordOauthExchangeCode((string) $code);
if (!$tokenData) {
    $_SESSION['flash'] = 'Discord との連携に失敗しました（トークン取得エラー）。';
    header('Location: player_edit?id=' . $playerId);
    exit;
}

// --- Discord ユーザー情報取得 ---
$user = discordOauthFetchUser((string) $tokenData['access_token']);
if (!$user || empty($user['id'])) {
    $_SESSION['flash'] = 'Discord との連携に失敗しました（ユーザー情報取得エラー）。';
    header('Location: player_edit?id=' . $playerId);
    exit;
}

$discordUserId = (string) $user['id'];
$discordUsername = (string) ($user['global_name'] ?? $user['username'] ?? '');
if (mb_strlen($discordUsername) > 64) {
    $discordUsername = mb_substr($discordUsername, 0, 64);
}

// --- 既に他選手で登録済みかチェック ---
['data' => $existing] = fetchData(fn() => Player::findByDiscordUserId($discordUserId));
if ($existing && (int) $existing['id'] !== $playerId) {
    $_SESSION['flash'] = '既に別の選手「' . ($existing['nickname'] ?? $existing['name']) . '」に紐付け済みのDiscordアカウントです。';
    header('Location: player_edit?id=' . $playerId);
    exit;
}

// --- DB 保存 ---
try {
    Player::updateDiscord($playerId, $discordUserId, $discordUsername !== '' ? $discordUsername : null);
    $_SESSION['flash'] = 'Discord と連携しました: @' . $discordUsername;
} catch (PDOException $e) {
    error_log('[Discord OAuth] save failed: ' . $e->getMessage());
    $_SESSION['flash'] = 'Discord 連携の保存に失敗しました。';
}

header('Location: player_edit?id=' . $playerId);
exit;
