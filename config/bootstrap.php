<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

const SITE_NAME = '雀魂部屋主催';

// 1 ラウンドあたりの対局数の上限（卓作成フォームの選択肢と、卓ページの入力欄生成の両方で使う）
const MAX_GAME_COUNT = 4;

// 1 局のスコア（pt）として受け付ける絶対値の上限。記録欄に非現実的な値が載るのを防ぐ
const MAX_ABS_SCORE = 1000;

// .envファイルから環境変数を読み込む（既存の環境変数は上書きしない）
$envFile = dirname(__DIR__);
if (file_exists($envFile . '/.env')) {
    Dotenv\Dotenv::createImmutable($envFile)->safeLoad();
}

define('SITE_URL', $_ENV['SITE_URL'] ?? getenv('SITE_URL') ?: 'https://jantama-records.onrender.com');

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/request.php';
require_once __DIR__ . '/discord.php';
