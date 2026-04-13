# 雀魂部屋主催 - 麻雀トーナメント戦績サイト

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
- `enums/` - PHP enum（定数定義・値オブジェクト）
- `config/` - アプリケーション設定（DB接続・ヘルパー・セキュリティ・リクエスト処理）
- `templates/` - 共通ヘッダー・フッター
- `db/migrations/` - Phinxマイグレーション
- `db/seeds/` - Phinxシーダー
- `cloudflare-worker/` - Cloudflare Workerリバースプロキシ（CDN）

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

# E2Eテスト（ホストマシンで実行、Docker起動が前提）
cd tests/e2e && npm install && npx playwright install chromium --with-deps
npx playwright test                              # 全テスト
npx playwright test pages/players.spec.ts        # 特定テスト
npx playwright test --headed                     # ブラウザ表示あり
npx playwright test --ui                         # UIモード

# git push 時に E2E テストが自動実行される（.claude/settings.json の hook で設定）
# テスト失敗で push がブロックされる

# Cloudflare Worker（CDNリバースプロキシ）
cd cloudflare-worker && npm install
npx wrangler login                       # 初回ログイン
npx wrangler deploy                      # デプロイ
```

## 環境構成

- 本番: Render → Neon production
- 開発: Codespaces / Docker → Neon dev
- DBはすべてNeon（リモート）。ローカルDBコンテナは使わない
- 本番デプロイ時に `start.sh` 経由で自動マイグレーション実行

## コーディング規約

### PHP
- 全PHPファイルの先頭に `declare(strict_types=1)` を付ける
- PSR-12 準拠: 型キャストの後にスペース `(int) $var`（`(int)$var` は不可）
- SQLは `models/` のクラスに集約する。ビュー（`public/*.php`）にSQLを書かない
- データ取得は `fetchData(fn() => ModelName::method())` を使う
- HTMLエスケープは `h()` ヘルパーを使う
- テンプレートは `templates/` の header.php / footer.php を使う
- DB接続は `getDbConnection()` を使用。直接PDOを生成しない
- SQLにユーザー入力を使う場合はプリペアドステートメント必須
- GETパラメータは `requirePlayerId()` / `requireTournamentId()` / `filter_input()` で検証する
- POST入力は `sanitizeInput($key)` で取得する（制御文字除去 + trim）
- 配列形式のPOST入力は `preg_replace('/[\x00-\x1F\x7F]/u', '', trim(...))` で同等処理する
- フォームページは `startSecureSession()` + `ensureCsrfToken()` を使う
- POST検証は `validatePost()` を使う（CSRF + Turnstile を一括検証）
- Turnstileは `footer.php` で一括管理。フォームページは `$pageTurnstile = true;` をヘッダーincludeの前に設定するだけでよい（個別フォームへのTurnstile div追加は不要）
- Turnstile検証成功まで送信ボタンは無効化される（`ts-pending` クラスによるCSS制御）
- POST成功後は `$_SESSION['flash']` にメッセージを設定 → `regenerateCsrfToken()` → PRGリダイレクト
- フラッシュメッセージの読み取りは `consumeFlash()` を使う
- `json_encode` には `JSON_UNESCAPED_UNICODE | JSON_HEX_TAG` を付ける
- `<script>` タグには `nonce="<?= cspNonce() ?>"` を必ず付ける
- インラインスクリプトは `$pageInlineScript` 変数を使い、直接 `<script>` タグを書かない
- イベントハンドラ属性（`onclick`, `onsubmit` 等）は使わない。`addEventListener` または `data-confirm` パターンを使う
- 大会スコープのデータ取得は `$tournamentId` を必ず指定する（`Player` を除く全モデル）
- モデルやenum追加後は `composer dump-autoload` を実行する
- `.env` の読み込みは phpdotenv (`Dotenv\Dotenv::createImmutable()`) を使う
- ハードコード文字列は enum に定義する。ラベル表示用に `label()` メソッドを持たせる

### Enum（`enums/` ディレクトリ）
- `EventType`: 大会イベント種別（最強位戦/鳳凰位戦/マスターズ/百段位戦/プチイベント）
- `TournamentStatus`: 大会ステータス（preparing/in_progress/completed）。`label()` + `cssClass()`
- `PlayerMode`: 対局人数（3/4）。`label()`（三麻/四麻） + `fullLabel()`（三人麻雀/四人麻雀）
- `RoundType`: 局数（hanchan/tonpu/ikkyoku）
- `HanRestriction`: 翻縛り（1/2/4）
- `ToggleRule`: ON/OFF設定（1/0）。`label('食い断')` → 食い断有/無
- `DayOfWeek`: 曜日（0-6）。`fromDate('2026-04-07')` で日付から曜日ラベルを取得

### CSS
- ダークテーマ対応: ページ固有CSSはCSS変数を使う。ハードコードカラー禁止
- フォーム共通CSS: `css/forms.css` を `$pageCss` で読み込む（edit-*, btn-save, player-select-* 等）
- フォーム入力欄: `--input-bg`, `--input-border`, `--input-border-hover`, `--input-border-focus`, `--input-focus-ring`, `--input-placeholder`, `--input-disabled-bg`, `--input-disabled-border` を使う。`--glass-border` や `--card` を input に使わない
- 戻るボタン: `btn-cancel` クラスを使用（`components.css` で定義済み）
- ページ固有CSSの `$pageStyle` には、forms.css / components.css に無いスタイルのみ書く
- 大会ルール表示は `buildRuleTags($meta)` ヘルパーで生成してループ出力する

### HTML
- 内部リンクに `.php` を付けない（.htaccessの301リダイレクトでPOSTデータが消えるため）
- 画像に `width`, `height`, `alt` 属性を必ず付ける。一覧表示では `loading="lazy"` も付ける

## 作業ルール

- 新しいディレクトリを追加したら `README.md` のディレクトリ構成セクションを更新する
- 新しいページを追加したら `README.md` のページ遷移セクションを更新する
- 新しいモデルやスキルを追加したら対応する `.claude/skills/` の SKILL.md を更新する
- コーディング規約やコマンドが変わったら `CLAUDE.md` を更新する
- 新しいページを追加したら `tests/e2e/pages/` にE2Eテストを追加する
- 新しい機能を追加したら `tests/e2e/features/` にテストを追加する
