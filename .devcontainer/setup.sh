#!/bin/bash
set -e

# PostgreSQL用PHP拡張をインストール
echo "PostgreSQL関連ライブラリをインストール中..."
sudo apt-get update && sudo apt-get install -y libpq-dev

# PostgreSQL拡張をインストール試行
echo "PostgreSQL拡張をインストール中..."
if command -v docker-php-ext-install &> /dev/null; then
  # docker-php-ext-installが利用可能な場合
  docker-php-ext-install pdo pdo_pgsql pgsql 2>/dev/null || {
    echo "警告: docker-php-ext-installでのインストール失敗。PECLで試行します..."
    pecl install pdo_pgsql 2>/dev/null || echo "警告: PECLでもインストール失敗。libpq-devのみ利用可能です。"
  }
else
  # PECLを使用（libpq-devインストール後）
  echo "PECLでPostgreSQL拡張をインストール中..."
  pecl install pdo_pgsql 2>/dev/null || echo "警告: PostgreSQL拡張がインストールできませんでしたが、libpq-devはインストール済みです。PDOが\$_ENV['DATABASE_URL']などで利用可能です。"
fi

# Composer依存をインストール
echo "Composer依存をインストール中..."
composer install --no-interaction

# phinx.phpが無ければテンプレートからコピー
if [ ! -f phinx.php ]; then
  if [ -f phinx.php.example ]; then
    cp phinx.php.example phinx.php
    echo "phinx.php を作成しました"
  else
    echo "警告: phinx.php.example が見つかりません" >&2
  fi
fi

# .envが無ければ作成（Codespaces Secretsから読み取り）
if [ ! -f .env ]; then
  echo "# Codespaces開発環境" > .env
  if [ -n "$DATABASE_URL" ]; then
    echo "DATABASE_URL=${DATABASE_URL}" >> .env
    echo ".env にDATABASE_URLを設定しました"
  else
    echo "警告: DATABASE_URL が未設定です。Codespaces Secretsまたは.envに手動で設定してください" >&2
  fi
  echo "PGSSLMODE=require" >> .env
fi

echo "セットアップ完了"
