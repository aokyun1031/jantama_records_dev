# `.claude/` 開発者ガイド

Claude Code 用のプロジェクト設定。skills / commands / agents / hooks を使って開発を高速化する。

## 目次

- [これは何？](#これは何)
- [ディレクトリ構成](#ディレクトリ構成)
- [使い方：3 つの入口](#使い方3-つの入口)
- [skill チートシート](#skill-チートシート)
- [slash command チートシート](#slash-command-チートシート)
- [agent チートシート](#agent-チートシート)
- [hook の挙動](#hook-の挙動)
- [settings の種類](#settings-の種類)
- [ワークフロー例](#ワークフロー例)
- [コードレビュー・監査のタイミング](#コードレビュー監査のタイミング)
- [トラブルシューティング](#トラブルシューティング)

## これは何？

Claude Code は `.claude/` 配下のファイルを自動で読み込んで以下を提供する。

- **skill** — 「〇〇する時はこの規約」を AI が状況に応じて参照する知識ベース
- **slash command** — ユーザーが明示的に `/xxx` と入力して起動する手順書
- **agent** — 限定ツール付きの専門サブ AI。主セッションから委譲して使う
- **hook** — 特定イベント（Write/Edit/git push など）で自動実行されるシェルスクリプト
- **settings** — permissions / hooks / env などの設定

規約（CLAUDE.md）と skill で AI の振る舞いを揃え、hook で自動検証を挟み、command で定型タスクをショートカットする。

## ディレクトリ構成

```
.claude/
├── README.md              # このファイル（開発者向けガイド）
├── settings.json          # チーム共有設定（git 管理）
├── settings.local.json    # 個人設定（gitignore）
├── skills/                # 状況依存で AI が自動参照
│   ├── add-page/SKILL.md
│   ├── data-model/SKILL.md
│   ├── design/SKILL.md
│   ├── enum/SKILL.md
│   ├── migration/SKILL.md
│   ├── refactor/SKILL.md
│   ├── security/SKILL.md
│   └── testing/SKILL.md
├── commands/              # /slash コマンド
│   ├── refactor.md
│   ├── security-check.md
│   └── migration-new.md
├── agents/                # サブエージェント
│   └── php-reviewer.md
└── hooks/                 # 自動実行スクリプト
    └── php-lint.sh        # Write/Edit 後に php -l
```

## 使い方：3 つの入口

| 入口 | トリガ | 用途 |
|---|---|---|
| skill | AI が状況に応じて自動参照 | 「新しいページ作る」「enum 追加したい」など、文脈で呼ばれる |
| slash command | ユーザーが `/xxx` 入力 | 定型タスクをワンショット起動 |
| agent | 主 AI が委譲（または `/security-check` 経由） | 独立した文脈で専門レビュー |

迷ったら: **skill = 知識**、**command = 手順**、**agent = 専門家**。

## skill チートシート

| skill | いつ参照される |
|---|---|
| `add-page` | 新しい PHP ページ作成時。読み取り専用／フォーム付きのテンプレ |
| `data-model` | モデル追加・クエリ書く時。SQL 集約先 |
| `design` | UI コンポーネント・CSS 変数・テーマ対応 |
| `enum` | ハードコード文字列を enum 化する時 |
| `migration` | Phinx マイグレ作成・冪等性パターン |
| `refactor` | 規約ドリフト検出・修正（scan.sh ベース） |
| `security` | CSRF / Turnstile / エスケープ / ヘルパー関数一覧 |
| `testing` | Playwright E2E の構成・追加手順 |

skill は AI が自動で読む。手動で中身を確認したい時は `.claude/skills/<name>/SKILL.md` を Read。

## slash command チートシート

| command | 引数 | 用途 |
|---|---|---|
| `/refactor [path]` | パス（任意） | 引数なし: scan.sh でヘルスチェック。引数あり: 指定対象を修正 |
| `/security-check [path]` | パス（任意） | `php-reviewer` agent に委譲。違反検出のみ、修正は対話的に |
| `/migration-new <Name>` | パスカルケース名 | マイグレ雛形生成 + 規約適用 |

## agent チートシート

| agent | tools | 用途 |
|---|---|---|
| `php-reviewer` | Read, Grep, Glob, Bash | CLAUDE.md 規約＋セキュリティ違反を検出。`/security-check` から委譲される |

## hook の挙動

`.claude/settings.json` に登録。全員に適用される。

### PostToolUse: `Write|Edit` → `php-lint.sh`

`.php` ファイルを編集／作成したら `php -l` で構文チェック。エラーがあれば `decision: block` で差し戻す。ローカルに PHP が無ければ Docker の web コンテナを使う。どちらも無ければ黙ってスキップ。

E2E テスト（Playwright）は hook 化していない。push 前に各自で `cd tests/e2e && npx playwright test` を手動実行する運用。

hook を一時無効化したい場合は `.claude/settings.json` の該当 hook を消す（`--no-verify` は使わない）。

## settings の種類

| ファイル | スコープ | git | 用途 |
|---|---|---|---|
| `.claude/settings.json` | プロジェクト | コミット | チーム共有の hook・permission |
| `.claude/settings.local.json` | プロジェクト | gitignore | 個人の permission override |
| `~/.claude/settings.json` | ユーザー | — | 全プロジェクト共通（theme / statusLine 等） |

ロード順: user → project → local（後が優先）。配列（permissions.allow 等）はマージされる。

設定変更は `update-config` skill を呼ぶのが安全（マージ処理や検証が入る）。

## ワークフロー例

### 新規ページを追加する

1. `add-page` skill の読み取り専用／フォーム構造に従って `public/xxx.php` を作成
2. 必要なら `models/Xxx.php` にクエリ追加（`data-model` skill）
3. CSS は `design` skill の変数・コンポーネントパターンに従う
4. `/security-check public/xxx.php` で違反チェック
5. `tests/e2e/pages/xxx.spec.ts` に E2E 追加（`testing` skill）
6. `README.md` のページ遷移セクション更新
7. `cd tests/e2e && npx playwright test` を手動実行 → 通ったら `git push`

### マイグレーションを追加する

1. `/migration-new AddFooToBar`
2. `db/migrations/...AddFooToBar.php` を編集
3. `php vendor/bin/phinx migrate` で dev 適用
4. ロールバックも確認（`phinx rollback` → `migrate`）
5. コミット・push → 本番 Render が自動 migrate

### 既存コードの規約違反を掃除する

1. `/refactor`（引数なし）で scan 実行 → レポート確認
2. `/refactor public/xxx.php` で対象絞って修正
3. 修正後に再 scan で件数減少を確認
4. 1 PR = 1 テーマ原則（混ぜない）

## コードレビュー・監査のタイミング

目的別に 3 層で運用する。どれか 1 つだけでも回せば抜けは減るが、**層ごとに拾えるバグの種類が違う**ので意識して使い分ける。

### 早見表

| タイミング | 主なコマンド | 対象 | 何を拾う |
|---|---|---|---|
| コミット前（任意） | `/security-check <path>` | 直前の変更ファイル | 自分が書いたばかりの規約違反 |
| push 前（必須） | `/security-review` + E2E | ブランチ全体の差分 | コミット間の不整合・リグレッション |
| 定期監査（四半期） | `/security-check` + `/refactor`（引数なし） | `public/` 全体 | 長期間潜んでいたバグ・規約ドリフト |

### 1. コミット前（個人差分の最終確認）

- **対象**: 自分が直前に書いた変更
- **コマンド**: `/security-check public/xxx.php`（変更ファイルのみ）
- **備考**: `Write|Edit` hook の `php-lint.sh` で構文エラーは自動検出される。コマンド実行は規約違反・セキュリティ違反の検出用途
- **狙い**: コミット粒度と検査粒度を揃える。違反があればそのコミットを作り直せる（push 済みだと履歴を汚す）

### 2. push 前（PR 直前の俯瞰レビュー）

- **対象**: ブランチ全体の累積差分
- **コマンド**:
  - `/security-review` — 保留変更のセキュリティ観点
  - `cd tests/e2e && npx playwright test` — E2E 全件（push 前の必須）
  - `/ultrareview` — マルチエージェント cloud レビュー（**課金あり**。クリティカルな PR のみ）
- **狙い**: コミット間の整合性（A で入れて B で壊す等）。個別コミットの検査では見えない

### 3. 定期監査（四半期・リリース前）

- **対象**: `public/` 全体
- **手順**:
  1. 自動チェック — `php -l`（hook 同等） / `composer audit` / `cd tests/e2e && npm audit`
  2. `/security-check`（引数なし）で `public/` 全 PHP を php-reviewer にレビューさせる
  3. `/refactor`（引数なし）で規約ドリフトをスキャン
  4. 検出事項を **1 PR = 1 テーマ**原則でブランチ分割して修正
     - P0 実害バグ → P1 規約違反 → P2 大型リファクタ → P3 軽微
  5. 残りは GitHub Issue 化して着手を後回し
- **狙い**: 1 / 2 では絶対に拾えない「長期間コードに潜んでいたバグ」の棚卸し
- **実例**: `tournament.php` で CSRF エラーが緑の成功スタイルで表示されていた UX バグは、全体監査で初めて発見された。差分レビューでは既存コード扱いで見逃されるため、定期監査が無いと永久に潜伏する類のもの

## トラブルシューティング

### hook が動かない

1. `jq -e '.hooks' .claude/settings.json` で JSON 妥当性確認
2. `bash -x .claude/hooks/xxx.sh` で手動実行してエラー確認
3. hook は session 開始時に読まれる。設定変更後は `/hooks` を開くか restart
4. PostToolUse は**成功した** Write/Edit のみ発火（失敗時は PostToolUseFailure）

### permission で止まる

1. よく使うコマンドは `.claude/settings.local.json` の allow に追加（個人設定）
2. チーム共通なら `.claude/settings.json` に追加
3. 構文: `"Bash(cmd:*)"` でプレフィックス一致

### skill が呼ばれない

skill は AI が **frontmatter の description** を見て自動判断する。説明が曖昧だと呼ばれない。具体的なトリガ語句を入れる（例: 「新しいページ作成時」「enum 追加時」）。

### E2E hook が遅い・タイムアウト

`.claude/settings.json` の timeout は 300 秒。全テスト 5 分超えるなら並列度（`playwright.config.ts` の `workers`）か test 分割を見直す。緊急時は該当 hook をコメントアウト。

### DB 接続先を切り替えたい

`.env` の `DATABASE_URL` / `PGSSLMODE` のみで切替。

- **ローカル Postgres**（Docker Compose のデフォルト）: `DATABASE_URL=postgresql://postgres:postgres@db:5432/jantama` / `PGSSLMODE=disable`
- **Neon dev**（Codespaces 推奨）: `DATABASE_URL=postgresql://...neon.tech/...?sslmode=require` / `PGSSLMODE=require`

ローカル Postgres は `docker compose down -v` で volume `pgdata` ごと削除しリセット可。再構築は `docker compose up -d`（initdb.d でスキーマ自動適用）→ `phinx seed:run`（テストデータ投入）。マイグは走らない（`docker-compose.yml` で `command: apache2-foreground` を指定し `start.sh` をバイパス）。詳細 → `docs/local-dev-seed.md`。

## 参考

- CLAUDE.md — プロジェクトのコーディング規約
- README.md — セットアップ・デプロイ手順
- [Claude Code 公式ドキュメント](https://docs.claude.com/en/docs/claude-code)
