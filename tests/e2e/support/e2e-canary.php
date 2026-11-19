<?php

declare(strict_types=1);

/**
 * E2E テスト用スタックが隔離されているかを報告する診断エンドポイント。
 *
 * docker-compose.test.yml が public/__e2e_canary.php としてマウントする。tests/ は .dockerignore で
 * 除外されているため、本番イメージにも開発用スタックにも存在しない。
 *
 * テストと同じ経路（Apache + mod_php）でアプリの設定を読み、global-setup.ts が期待値と照合する。
 * CLI で確認すると Apache 側と環境変数の見え方が違う場合に見逃すので、HTTP 経由にしている。
 */

require dirname(__DIR__) . '/config/bootstrap.php';

$databaseUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: '';
if ($databaseUrl !== '') {
    $dbHost = (string) (parse_url($databaseUrl, PHP_URL_HOST) ?: '');
} else {
    $dbHost = (string) ($_ENV['PGHOST'] ?? getenv('PGHOST') ?: 'localhost');
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    // Bot トークンが設定されていると、大会作成などのたびに実際の Discord へ送信される
    'discord_enabled' => discordEnabled(),
    // OAuth のクライアントシークレットが入っていると、連携フローが実際の Discord と通信し得る
    'discord_oauth_configured' => discordClientSecret() !== '',
    // 設定が紛れ込んでも外へ届かないよう、名前解決をループバックに向けてあること
    'discord_host' => gethostbyname('discord.com'),
    // 接続先が compose の db サービスであること（Neon など外部の DB でないこと）
    'db_host' => $dbHost,
    // 本番扱いだと Turnstile 検証が有効になり、テストの POST がすべて失敗する
    'is_production' => isProduction(),
    // 開発者の .env がコンテナ内に存在しないこと
    'env_file_present' => file_exists(dirname(__DIR__) . '/.env'),
], JSON_UNESCAPED_SLASHES);
