# 雀魂部屋主催 - 麻雀トーナメント戦績サイト

PHP 8.2 / Apache / PostgreSQL（Neon）/ Phinx / Docker Compose / Render。フレームワーク不使用。

## ディレクトリ

- `public/` — DocumentRoot（新ページはここに）
- `models/` — データアクセス層（SQL 集約）
- `enums/` — PHP enum（値オブジェクト）
- `config/` — DB 接続・ヘルパー・セキュリティ
- `templates/` — 共通 header.php / footer.php
- `templates/partials/{page}/{section}.php` — ページ固有 partial（500 行超ページの分割用、include で親スコープ共有）
- `db/migrations/`, `db/seeds/` — Phinx
- `cloudflare-worker/` — CDN リバースプロキシ
- `tests/e2e/` — Playwright E2E

`public/` 外は Web から到達不能。

## よく使うコマンド

```bash
# Docker（web + db(postgres) の2サービス）
docker compose up -d                      # 起動（db 初回起動時に current_schema.sql 自動適用）
docker compose down                       # 停止（volume pgdata は保持）
docker compose down -v                    # 停止 + ローカルDBリセット（再 up でスキーマ再構築）
docker compose exec web composer install
docker compose exec db psql -U postgres jantama   # ローカルDBに psql 接続

# Phinx（Docker は `docker compose exec web` を前置）
php vendor/bin/phinx status / migrate / seed:run / create MigrationName
# ローカルで seed:run すると DevDataSeeder が TRUNCATE → db/seed_data.sql を流し込む
# → テストデータを壊しても seed:run で毎回クリーンに復元できる

# E2E（ホスト実行、Docker 起動前提）
cd tests/e2e && npm install && npx playwright install chromium --with-deps
npx playwright test [pages/xxx.spec.ts] [--headed|--ui]
```

E2E は手動実行。push 前に `cd tests/e2e && npx playwright test` を各自で走らせる。詳細は `tests/e2e/` と `testing` skill。

## 環境

- 本番: Render → Neon production（push で `start.sh` が migrate 自動実行）
- 開発（使い分け）:
  - **ローカル Docker Compose** → ローカル Postgres コンテナ（`docker-compose.yml` の `db` サービス、volume `pgdata` で永続化）
  - **GitHub Codespaces** → Neon dev ブランチ（Neon 無料枠の compute 節約目的でローカル優先）
- 切替は `.env` の `DATABASE_URL` / `PGSSLMODE` のみ。コード変更不要

## ローカル Postgres 初期化方針（重要）

ローカルは「マイグレーションで初期化しない」。理由: 古いマイグが本番 `init.sql` 前提で書かれており、空 DB から `migrate` を通すと失敗するため。

- **スキーマ** → `db/current_schema.sql`（Neon から pg_dump したもの）を `docker-entrypoint-initdb.d` に自動適用（volume 初回のみ）
- **データ** → `db/seed_data.sql`（Neon dev から `pg_dump --data-only --column-inserts` したもの）を `DevDataSeeder` が TRUNCATE CASCADE → 流し込む
- **`phinx seed:run` でいつでもテストデータをリセット**できる（何度でも流せる）
- **マイグは本番運用用**。ローカルでは `docker-compose.yml` の `command: apache2-foreground` で `start.sh` を回避し migrate は走らない
- dump 更新手順 → `docs/local-dev-seed.md`

## コーディング規約（要点）

### PHP

- 先頭に `declare(strict_types=1)`
- PSR-12: 型キャスト後にスペース `(int) $var`
- SQL は `models/` に集約。`public/*.php` に直書き禁止
- データ取得は `fetchData(fn() => ModelName::method())`
- DB 接続は `getDbConnection()`（直接 PDO 生成禁止）
- プリペアドステートメント必須（ユーザー入力を含む SQL）
- GET: `requirePlayerId()` / `requireTournamentId()` / `filter_input()`
- POST: `sanitizeInput($key)`（配列は `preg_replace('/[\x00-\x1F\x7F]/u', '', trim(...))`）
- POST ページ: `startSecureSession()` + `ensureCsrfToken()` + `validatePost()`
- POST 成功: `$_SESSION['flash'] = ...` → `regenerateCsrfToken()` → PRG リダイレクト
- Turnstile: `$pageTurnstile = true` のみで footer.php が一括埋込
- 出力: `h($value)` でエスケープ
- JS 埋込: `json_encode($x, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)`
- `<script>` に `nonce="<?= cspNonce() ?>"`、インラインは `$pageInlineScript`
- イベントハンドラ属性（`onclick` 等）禁止。`addEventListener` / `data-confirm` を使う
- 大会スコープのクエリは `$tournamentId` 必須（`Player` モデル以外）
- モデル/enum 追加後は `composer dump-autoload`
- ハードコード定数は enum 化。表示は `label()` メソッド

詳細 → `.claude/skills/security/SKILL.md`, `.claude/skills/data-model/SKILL.md`, `.claude/skills/enum/SKILL.md`, `docs/database.md`（DB 設計書）

### CSS

- ライト固定。CSS 変数のみ使用（ハードコードカラー禁止）
- フォーム: `css/forms.css` を `$pageCss` で読込。input には `--input-*` 変数を使う（`--glass-border` / `--card` 不可）
- 戻るボタン: `btn-cancel`（components.css）
- **ページ固有 CSS は必ず `public/css/{page}.css` として外部化し `$pageCss` 配列で読込む**
- `$pageStyle`（インライン CSS）は原則禁止。例外は Loader 等の Critical CSS（外部 CSS 到着前に描画が必要なもの）に限る
- ルールタグ表示: `buildRuleTags($meta)`

詳細 → `.claude/skills/design/SKILL.md`

### HTML

- 内部リンクに `.php` 付けない（.htaccess の 301 で POST が消える）
- 画像に `width` / `height` / `alt` 必須。一覧は `loading="lazy"` も

## 作業ルール

- 新ディレクトリ／新ページ追加 → `README.md` 更新
- 新モデル／新 enum／新スキル追加 → `.claude/skills/` 該当 SKILL.md 更新
- 新ページ追加 → `tests/e2e/pages/` に E2E 追加
- 新機能追加 → `tests/e2e/features/` に E2E 追加
- 規約・コマンド変更 → `CLAUDE.md` 更新

## スキル・コマンド・agent（`.claude/`）

- skills/ — `add-page`, `data-model`, `design`, `enum`, `migration`, `refactor`, `security`, `testing`
- commands/ — `/refactor`, `/security-check`, `/migration-new`
- agents/ — `php-reviewer`

開発者向けフロー → `.claude/README.md`
