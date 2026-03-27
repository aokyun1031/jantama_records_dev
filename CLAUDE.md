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
- `models/` - データアクセス層（SQLはここに集約）
- `config/` - DB接続・ヘルパー関数
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

- SQLは `models/` のクラスに集約する。ビュー（`public/*.php`）にSQLを書かない
- データ取得は `fetchData(fn() => ModelName::method())` を使う
- HTMLエスケープは `h()` ヘルパーを使う
- テンプレートは `templates/` の header.php / footer.php を使う
- DB接続は `getDbConnection()` を使用。直接PDOを生成しない
- SQLにユーザー入力を使う場合はプリペアドステートメント必須
- GETパラメータは `filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)` で検証する
- 大会スコープのデータ取得は `$tournamentId` を必ず指定する（`Player` を除く全モデル）
- モデル追加後は `composer dump-autoload` を実行する
- `.env` の読み込みは phpdotenv (`Dotenv\Dotenv::createImmutable()`) を使う
- ダークテーマ対応: ページ固有CSSはCSS変数を使い、必要に応じて `theme-dark.css` に `body` プレフィックス付きで上書きを追加する

## 作業ルール

- ファイルやディレクトリ構成を変更したら `README.md` のファイル構成セクションを更新する
- 新しいモデルやスキルを追加したら対応する `.claude/skills/` の SKILL.md を更新する
- コーディング規約やコマンドが変わったら `CLAUDE.md` を更新する
