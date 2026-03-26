# 最強位戦 - 麻雀トーナメント戦績サイト

## 技術スタック

- PHP 8.2 / Apache / PostgreSQL（Neon）
- HTML / CSS / JavaScript（フレームワーク不使用）
- Phinx（DBマイグレーション）
- phpdotenv（環境変数管理）
- Docker Compose（ローカル開発）
- Render（本番ホスティング）
- GitHub Codespaces（クラウド開発環境）

## ディレクトリ構成

- `public/` - Webサーバー公開ディレクトリ（DocumentRoot）
- `config/` - DB接続等の設定ファイル
- `templates/` - 共通ヘッダー・フッター
- `db/migrations/` - Phinxマイグレーション
- `db/seeds/` - Phinxシーダー

`public/` 外のファイルはWebからアクセスできない。新しいページは `public/` に作成する。

## コマンド

```bash
# ローカルDocker
docker compose up -d
docker compose down
docker compose exec web composer install

# Phinx（Docker内）
docker compose exec web php vendor/bin/phinx status
docker compose exec web php vendor/bin/phinx migrate
docker compose exec web php vendor/bin/phinx seed:run
docker compose exec web php vendor/bin/phinx create MigrationName

# Phinx（Codespaces内）
php vendor/bin/phinx status
php vendor/bin/phinx migrate
php vendor/bin/phinx seed:run
```

## 環境構成

- 本番: Render → Neon production
- 開発: Codespaces / Docker → Neon dev
- DBはすべてNeon（リモート）。ローカルDBコンテナは使わない
- 本番デプロイ時に `start.sh` 経由で自動マイグレーション実行

## コーディング規約

- PHPの新規ページは `public/` 配下に作成し、`config/database.php` を `require __DIR__ . '/../config/database.php'` で読み込む
- テンプレートは `templates/` の header.php / footer.php を使う
- DB接続は `getDbConnection()` を使用。直接PDOを生成しない
- 出力時は必ず `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')` でエスケープ
- SQLにユーザー入力を使う場合はプリペアドステートメント必須
- `.env` の読み込みは phpdotenv (`Dotenv\Dotenv::createImmutable()`) を使う
