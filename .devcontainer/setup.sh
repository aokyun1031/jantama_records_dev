#!/bin/bash
set -e

# PostgreSQL用PHP拡張をインストール（PHPバージョンを自動検出）
PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
sudo apt-get update && sudo apt-get install -y "php${PHP_VER}-pgsql"

# Composer依存をインストール
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
