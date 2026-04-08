# 雀魂部屋主催 - 麻雀トーナメント戦績サイト

雀魂（じゃんたま）で開催する身内向け麻雀トーナメントの戦績サイトです。最強位戦・鳳凰位戦・マスターズ・百段位戦・プチイベントなど、複数の大会種別に対応しています。

## 技術スタック

- HTML / CSS / JavaScript（フレームワーク不使用）
- Google Fonts（Noto Sans JP, Inter）
- PHP 8.2（Apache）
- PostgreSQL（Neon）
- Phinx（DBマイグレーション）
- Docker Compose（ローカル開発）
- Render（ホスティング）
- GitHub Codespaces（開発環境）
- UptimeRobot（死活監視）

## 環境構成

```
本番:  Render ──→ Neon (productionブランチ)
開発:  Codespaces / Docker ──→ Neon (devブランチ)
```

DBはすべてNeon（リモート）を使用します。ローカルにDBコンテナは不要です。
Neon無料枠のスリープからの復帰を考慮し、DB接続はリトライ付き（最大3回、指数バックオフ）で行います。

## ディレクトリ構成

```
├── public/          Webサーバー公開ディレクトリ（DocumentRoot）
│   ├── css/         スタイルシート
│   ├── js/          JavaScript
│   └── img/         画像
├── models/          データアクセス層（SQLはここに集約）
├── enums/           PHP enum（定数定義・値オブジェクト）
├── config/          DB接続・ヘルパー関数
├── templates/       共通ヘッダー・フッター
├── db/migrations/   Phinxマイグレーション
├── db/seeds/        Phinxシーダー
├── tests/e2e/       E2Eテスト（Playwright）
├── docs/            ドキュメント（DB設計書等）
└── .devcontainer/   GitHub Codespaces設定
```

`public/` のみがWebサーバーから公開されます。`config/`、`models/`、`enums/`、`templates/`、`db/`、`vendor/` はWeb経由でアクセスできません。

## ページ遷移

```
index.php（トップ）
  ├→ players.php（選手一覧）
  │    ├→ player_new.php（選手登録）
  │    └→ player.php（選手詳細）
  │         ├→ player_edit.php（選手編集・削除）
  │         ├→ player_tournament.php（大会別戦績）
  │         └→ player_analysis.php（戦績分析）
  ├→ tournaments.php（大会一覧）
  │    ├→ tournament_new.php（大会作成）
  │    └→ tournament.php（大会管理）
  │         ├→ tournament_edit.php（大会情報編集）
  │         ├→ tournament_players.php（選手登録）
  │         ├→ table_new.php（卓作成）
  │         ├→ table.php（卓管理：日程・牌譜URL・結果登録・完了）
  │         └→ interview_edit.php（優勝インタビュー設定・大会完了）
  ├→ tournament_view.php（大会結果閲覧）
  └→ interview.php（優勝インタビュー）
```

## 開発環境セットアップ

### GitHub Codespaces（推奨）

#### 初回セットアップ

1. GitHubリポジトリ → **Code** → **Codespaces** → **Create codespace**
2. 初回起動時に `.devcontainer/setup.sh` が自動実行され、以下が設定される:
   - PHP拡張（pdo_pgsql, pgsql）のインストール
   - `composer install` の実行
   - `phinx.php` の作成
   - `.env` の作成（Codespaces Secretsから `DATABASE_URL` を読み取り）
3. `DATABASE_URL` の設定（以下のいずれか）:
   - **Codespaces Secrets**（推奨）: GitHub → Settings → Codespaces → Secrets → `DATABASE_URL` を追加
   - **手動**: `.env` を編集して Neon devブランチの接続文字列を設定

#### 動作確認

Codespaces起動時にPHPビルトインサーバー（ポート8080）が自動で立ち上がります。

ブラウザで確認するには:

1. VS Code下部パネルの「**ポート**」タブをクリック
2. ポート `8080` の行にある **地球儀アイコン**（Open in Browser）をクリック

`https://<codespace名>-8080.app.github.dev` でページが表示されます。

#### PHPサーバーが停止した場合

```bash
php -S 0.0.0.0:8080 -t public
```

### Docker Compose（ローカル）

```bash
# 初期設定
cp .env.example .env
# .env にNeon devブランチの接続文字列を設定
cp phinx.php.example phinx.php

# 起動
docker compose up -d

# 依存パッケージのインストール
docker compose exec web composer install

# 停止・削除
docker compose down
```

起動後 `http://localhost:8080` でアクセスできます。

## Phinx（DBマイグレーション）

```bash
# Codespaces内
php vendor/bin/phinx status              # ステータス確認
php vendor/bin/phinx migrate             # マイグレーション実行
php vendor/bin/phinx seed:run            # シーダー実行
php vendor/bin/phinx create AddNewColumn # 新しいマイグレーション作成
php vendor/bin/phinx rollback            # ロールバック

# Docker内
docker compose exec web php vendor/bin/phinx status
docker compose exec web php vendor/bin/phinx migrate
docker compose exec web php vendor/bin/phinx seed:run
```

### 初回セットアップ（phinx導入前にinit.sqlでテーブル作成済みの環境）

既存のDBをPhinx管理下に置くには `--fake` を使います:

```bash
php vendor/bin/phinx migrate --fake
```

## Neonブランチ運用

```
Neon production (default) ← Render本番が接続
Neon dev                  ← 開発環境が接続
```

### 開発フロー

1. devブランチで開発・テスト
2. 確認OK → mainブランチにマージ
3. Renderが自動デプロイし、`start.sh` 経由でproductionにマイグレーションが自動実行される
4. Neon devブランチを削除 → 再作成（productionのコピーでクリーンに戻す）

## E2Eテスト

Playwright による自動テスト。Docker 起動中に実行する。

```bash
# 初回セットアップ
cd tests/e2e && npm install && npx playwright install chromium --with-deps

# テスト実行
npx playwright test              # 全テスト
npx playwright test --headed     # ブラウザ表示あり
npx playwright test pages/       # ページテストのみ
npx playwright test features/    # 機能テストのみ
```

`git push` 時に Claude Code の hook で自動実行される（Docker 未起動時はスキップ）。

| ディレクトリ | 内容 |
|---|---|
| `tests/e2e/pages/` | ページ別テスト（表示・CRUD・バリデーション・404） |
| `tests/e2e/features/` | 機能テスト（テーマ切替・クリーンURL・セキュリティ・ナビゲーション） |
| `tests/e2e/helpers/` | 共通ユーティリティ（カスタムfixture・テストプレイヤー管理） |
