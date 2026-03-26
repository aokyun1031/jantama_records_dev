#!/bin/bash
set -e

# デプロイ時にPhinxマイグレーションを自動実行
php vendor/bin/phinx migrate

# Apacheをフォアグラウンドで起動
apache2-foreground
