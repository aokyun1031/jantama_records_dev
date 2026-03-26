#!/bin/bash
set -e

# phinx.phpが無ければテンプレートからコピー
if [ ! -f phinx.php ] && [ -f phinx.php.example ]; then
  cp phinx.php.example phinx.php
fi

# デプロイ時にPhinxマイグレーションを自動実行
php vendor/bin/phinx migrate

# Apacheをフォアグラウンドで起動
apache2-foreground
