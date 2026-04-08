<?php

declare(strict_types=1);

/**
 * セキュアなセッションを開始する。
 */
function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

/**
 * CSRFトークンを生成（未生成の場合のみ）し、返す。
 */
function ensureCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * POSTリクエストのCSRFトークンを検証する。
 */
function validateCsrfToken(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * CSRFトークンを再生成する。POST成功後に呼び出す。
 * セッションIDも再生成してセッション固定攻撃を防止する。
 */
function regenerateCsrfToken(): void
{
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * CSP用のnonceを生成・返却する。同一リクエスト内では同じ値を返す。
 */
function cspNonce(): string
{
    static $nonce = null;
    if ($nonce === null) {
        $nonce = bin2hex(random_bytes(16));
    }
    return $nonce;
}

/**
 * POST入力値をサニタイズして返す。制御文字を除去し、前後の空白をトリムする。
 */
function sanitizeInput(string $key): string
{
    return preg_replace('/[\x00-\x1F\x7F]/u', '', trim($_POST[$key] ?? ''));
}
