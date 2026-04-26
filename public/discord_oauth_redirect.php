<?php

declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

/**
 * Discord OAuth2 認可開始エンドポイント。
 * player_edit ページからリンクで呼ばれ、state署名付き認可URLへリダイレクトする。
 */

$playerId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$playerId || $playerId <= 0) {
    abort404();
}

['data' => $player] = fetchData(fn() => Player::find($playerId));
if (!$player) {
    abort404();
}

if (discordAppSecret() === '' || discordOauthRedirectUri() === '' || discordClientSecret() === '') {
    startSecureSession();
    $_SESSION['flash'] = 'Discord 連携設定が未完了です。運営にご連絡ください。';
    header('Location: player_edit?id=' . $playerId);
    exit;
}

header('Location: ' . discordOauthAuthorizeUrl($playerId));
exit;
