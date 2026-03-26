<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

// .envファイルから環境変数を読み込む（既存の環境変数は上書きしない）
$envFile = dirname(__DIR__);
if (file_exists($envFile . '/.env')) {
    Dotenv\Dotenv::createImmutable($envFile)->safeLoad();
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
        return $pdo;
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
function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * データ取得の共通ラッパー。例外をキャッチしてエラー情報を返す。
 * モデルのメソッドをそのまま渡せる。
 *
 * 使用例: ['data' => $players, 'error' => $error] = fetchData(fn() => Player::all());
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
