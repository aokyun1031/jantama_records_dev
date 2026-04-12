#!/bin/bash
set -e

# phinx.phpが無ければテンプレートからコピー
if [ ! -f phinx.php ] && [ -f phinx.php.example ]; then
  cp phinx.php.example phinx.php
fi

# vendorが無ければcomposer installを実行（ボリュームマウントで上書きされた場合の対処）
if [ ! -f vendor/bin/phinx ]; then
  composer install --no-dev --no-interaction --optimize-autoloader
fi

# デプロイ時にPhinxマイグレーションを自動実行
php vendor/bin/phinx migrate

# Apacheをフォアグラウンドで起動
apache2-foreground
