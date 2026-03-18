#!/bin/bash
set -e

# PostgreSQL用PHP拡張をインストール
sudo apt-get update && sudo apt-get install -y libpq-dev
sudo docker-php-ext-install pdo_pgsql pgsql
# docker-php-ext-enableのパス問題を回避して手動でini登録
echo "extension=pdo_pgsql.so" | sudo tee /usr/local/etc/php/conf.d/docker-php-ext-pdo_pgsql.ini > /dev/null
echo "extension=pgsql.so" | sudo tee /usr/local/etc/php/conf.d/docker-php-ext-pgsql.ini > /dev/null

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
