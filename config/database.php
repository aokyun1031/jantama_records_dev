<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// .envファイルから環境変数を読み込む（既存の環境変数は上書きしない）
$envFile = dirname(__DIR__);
if (file_exists($envFile . '/.env')) {
    Dotenv\Dotenv::createImmutable($envFile)->safeLoad();
}

/**
 * 本番環境かどうかを返す。
 */
function isProduction(): bool
{
    return ($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'production';
}

/**
 * DB接続を返す。
 * Neon無料枠のスリープからの復帰を考慮し、リトライを行う。
 * 同一リクエスト内では同じPDOインスタンスを再利用する。
 */
function getDbConnection(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        try {
            $pdo->query('SELECT 1');
            return $pdo;
        } catch (PDOException) {
            $pdo = null;
        }
    }

    $databaseUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');

    if ($databaseUrl) {
        $params = parse_url($databaseUrl);
        if ($params === false) {
            throw new RuntimeException('DATABASE_URL の形式が不正です');
        }
        $host = $params['host'] ?? 'localhost';
        $port = $params['port'] ?? 5432;
        $name = ltrim($params['path'] ?? '', '/');
        $user = $params['user'] ?? '';
        $pass = $params['pass'] ?? '';
    } else {
        $host = $_ENV['PGHOST'] ?? getenv('PGHOST') ?: 'localhost';
        $port = $_ENV['PGPORT'] ?? getenv('PGPORT') ?: 5432;
        $name = $_ENV['PGDATABASE'] ?? getenv('PGDATABASE') ?: 'jantama';
        $user = $_ENV['PGUSER'] ?? getenv('PGUSER') ?: 'postgres';
        $pass = $_ENV['PGPASSWORD'] ?? getenv('PGPASSWORD') ?: '';
    }

    $sslmode = $_ENV['PGSSLMODE'] ?? getenv('PGSSLMODE') ?: 'require';
    $dsn = "pgsql:host={$host};port={$port};dbname={$name};sslmode={$sslmode}";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 10,
    ];

    // Neon無料枠はスリープ状態からの復帰に時間がかかるためリトライする
    $maxRetries = 3;
    $retryDelay = 1; // 秒
    $lastException = null;

    for ($i = 0; $i < $maxRetries; $i++) {
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            return $pdo;
        } catch (PDOException $e) {
            $lastException = $e;
            error_log("[DB] Connection attempt " . ($i + 1) . " failed: " . $e->getMessage());
            if ($i < $maxRetries - 1) {
                sleep($retryDelay);
                $retryDelay *= 2; // 1秒 → 2秒 → 4秒
            }
        }
    }

    throw $lastException;
}

/**
 * HTMLエスケープのショートカット。
 */
function h(string|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * 静的ファイルのパスにキャッシュバスティング用のクエリパラメータを付与する。
 */
function asset(string $path): string
{
    $file = __DIR__ . '/../public/' . $path;
    $v = file_exists($file) ? filemtime($file) : 0;
    return htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '?v=' . $v;
}

/**
 * データ取得の共通ラッパー。例外をキャッチしてエラー情報を返す。
 *
 * @return array{data: mixed, error: bool}
 */
function fetchData(callable $callback): array
{
    try {
        return ['data' => $callback(), 'error' => false];
    } catch (PDOException $e) {
        error_log('[DB] ' . $e->getMessage());
        return ['data' => null, 'error' => true];
    }
}

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
 * POST入力値をサニタイズして返す。制御文字を除去し、前後の空白をトリムする。
 */
function sanitizeInput(string $key): string
{
    return preg_replace('/[\x00-\x1F\x7F]/u', '', trim($_POST[$key] ?? ''));
}

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
 * 大会メタ情報からルールタグ配列を生成する。
 */
function buildRuleTags(array $meta): array
{
    $tags = [];
    $eventType = EventType::tryFrom($meta['event_type'] ?? '');
    if ($eventType) {
        $tags[] = $eventType->label();
    }
    $tags[] = (PlayerMode::tryFrom($meta['player_mode'] ?? '4'))?->label() ?? '';
    $tags[] = (RoundType::tryFrom($meta['round_type'] ?? 'hanchan'))?->label() ?? '';
    if (!empty($meta['thinking_time'])) {
        $tags[] = $meta['thinking_time'] . '秒';
    }
    if (!empty($meta['starting_points'])) {
        $tags[] = '原点' . $meta['starting_points'];
    }
    if (!empty($meta['return_points'])) {
        $tags[] = '返し' . $meta['return_points'];
    }
    if (isset($meta['red_dora'])) {
        $tags[] = '赤' . $meta['red_dora'];
    }
    $tags[] = (ToggleRule::tryFrom($meta['open_tanyao'] ?? '1'))?->label('喰いタン') ?? '';
    $hanLabel = (HanRestriction::tryFrom($meta['han_restriction'] ?? ''))?->label();
    if ($hanLabel) {
        $tags[] = $hanLabel;
    }
    $tags[] = (ToggleRule::tryFrom($meta['bust'] ?? '0'))?->label('トビ') ?? '';
    return array_filter($tags, fn($t) => $t !== '');
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

/**
 * 入力文字列からURLを抽出する。
 * 「雀魂牌譜: https://...」のようなプレフィックス付きテキストからURLだけを取り出す。
 */
function extractUrl(string $input): string
{
    if (preg_match('/(https?:\/\/\S+)/', $input, $m)) {
        return $m[1];
    }
    return $input;
}

/**
 * キャラクターアイコンのHTMLを返す。アイコンがない場合は「NO IMG」プレースホルダーを返す。
 *
 * @param int $size ピクセルサイズ（width/height）
 */
function charaIcon(?string $icon, int $size = 28, string $class = ''): string
{
    if ($icon) {
        return '<img src="img/chara_deformed/' . h($icon) . '" alt="" width="' . $size . '" height="' . $size . '"'
            . ' class="chara-icon' . ($class !== '' ? ' ' . h($class) : '') . '"'
            . ' loading="lazy">';
    }
    return '<span class="chara-icon-none' . ($class !== '' ? ' ' . h($class) : '') . '"'
        . ' style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . round($size * 0.16, 1) . 'rem"'
        . '>NO<br>IMG</span>';
}
