<?php

declare(strict_types=1);

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
    static $connectFailure = null;
    if ($pdo !== null) {
        try {
            $pdo->query('SELECT 1');
            return $pdo;
        } catch (PDOException) {
            $pdo = null;
        }
    }

    // 同一リクエスト内で一度接続に失敗したら再試行しない。1 ページで fetchData() を何度も呼ぶため、
    // 毎回リトライすると DB 障害時に待ち時間が呼び出し回数分だけ積み上がり、ワーカーを占有してしまう。
    if ($connectFailure !== null) {
        throw $connectFailure;
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

    $connectFailure = $lastException;
    // DB に接続できないページは 503 で返す（200 のままだと Worker の KV フォールバックも働かない）
    if (!headers_sent()) {
        http_response_code(503);
    }
    throw $lastException;
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
