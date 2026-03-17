<?php
/**
 * PostgreSQL 接続
 *
 * 接続優先順位:
 *   1. DATABASE_URL（接続文字列）
 *   2. 個別のPG*環境変数
 */

function getDbConnection(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $databaseUrl = getenv('DATABASE_URL');

    if ($databaseUrl) {
        $params = parse_url($databaseUrl);
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
            $params['host'],
            $params['port'] ?? 5432,
            ltrim($params['path'] ?? '', '/'),
            getenv('PGSSLMODE') ?: 'require'
        );
        $user = $params['user'] ?? '';
        $pass = $params['pass'] ?? '';
    } else {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
            getenv('PGHOST') ?: 'localhost',
            getenv('PGPORT') ?: '5432',
            getenv('PGDATABASE') ?: 'jantama',
            getenv('PGSSLMODE') ?: 'disable'
        );
        $user = getenv('PGUSER') ?: 'postgres';
        $pass = getenv('PGPASSWORD') ?: '';
    }

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
