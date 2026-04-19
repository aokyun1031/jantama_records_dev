<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

const SITE_NAME = '雀魂部屋主催';
const SITE_URL  = 'https://jantama-records.onrender.com';

// .envファイルから環境変数を読み込む（既存の環境変数は上書きしない）
$envFile = dirname(__DIR__);
if (file_exists($envFile . '/.env')) {
    Dotenv\Dotenv::createImmutable($envFile)->safeLoad();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/request.php';
