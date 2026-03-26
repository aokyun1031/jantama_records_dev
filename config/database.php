<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

// .envファイルから環境変数を読み込む（既存の環境変数は上書きしない）
$envFile = dirname(__DIR__);
if (file_exists($envFile . '/.env')) {
    Dotenv\Dotenv::createImmutable($envFile)->safeLoad();
}

/**
 * DB接続を返す。
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

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
