# 最強位戦 - 麻雀トーナメント

雀魂（じゃんたま）で開催する身内向け麻雀トーナメント「最強位戦」の戦績ページです。

## 技術スタック

- HTML / CSS / JavaScript（フレームワーク不使用）
- Google Fonts（Noto Sans JP, Inter）
- PHP 8.2（Apache）
- PostgreSQL（Neon）
- Phinx（DBマイグレーション）
- Docker Compose（ローカル開発）
- Render（ホスティング）
- GitHub Codespaces（開発環境）
- UptimeRobot（監視）

## 環境構成

```
本番:  Render ──→ Neon (productionブランチ)
開発:  Codespaces / Docker ──→ Neon (devブランチ)
監視:  UptimeRobot ──→ Render本番
```

DBはすべてNeon（リモート）を使用します。ローカルにDBコンテナは不要です。

## ファイル構成

```
├── public/                     Webサーバー公開ディレクトリ
│   ├── index.php               メインページ
│   ├── tournament_view.php     大会閲覧ページ（index.phpベースの動的版）
│   ├── interview.php           優勝インタビュー
│   ├── player.php              選手詳細ページ（大会一覧）
│   ├── player_analysis.php     選手の戦績分析ページ
│   ├── player_edit.php         選手情報編集ページ
│   ├── player_new.php          選手新規登録ページ
│   ├── player_tournament.php   選手の大会別戦績ページ
│   ├── players.php             選手一覧ページ
│   ├── tournaments.php         大会一覧ページ
│   ├── tournament_new.php      大会新規作成ページ
│   ├── tournament_edit.php     大会情報編集ページ
│   ├── tournament_players.php  大会選手登録ページ
│   ├── tournament.php          大会詳細ページ（ラウンド・卓一覧）
│   ├── table_new.php           卓作成ページ
│   ├── table.php               卓管理ページ（日程・牌譜URL・結果登録）
│   ├── interview_edit.php      優勝インタビュー設定ページ
│   ├── 404.php                 404エラーページ
│   ├── 500.html                500エラーページ（静的HTML）
│   ├── maintenance.html        メンテナンスページ（静的HTML）
│   ├── .htaccess               セキュリティヘッダー・URL書き換え・エラーページ設定
│   ├── css/
│   │   ├── base.css            変数・リセット・レイアウト・Hero・プログレス
│   │   ├── components.css      順位表・タブ・卓カード・結果・レコード
│   │   ├── forms.css           フォーム共通スタイル（edit-*・btn-save等）
│   │   ├── finals.css          決勝卓セクションの演出・アニメーション
│   │   ├── champion.css        優勝おめでとうセクション
│   │   ├── mahjong-deco.css    麻雀牌の装飾
│   │   ├── theme-dark.css      ダークテーマ（champion含む全セクション対応）
│   │   └── theme-toggle.css    テーマ切替トグル
│   ├── js/
│   │   ├── render.js           DOM描画・タブ切替
│   │   ├── effects.js          パーティクル・スクロールアニメ・決勝エフェクト
│   │   └── theme-toggle.js     テーマ切替（localStorage永続化）
│   └── img/
│       └── chara_deformed/     キャラクターデフォルメアイコン
├── models/
│   ├── Character.php           キャラクターマスタの取得
│   ├── Interview.php           優勝インタビューの取得・保存
│   ├── Player.php              選手データの取得（キャラアイコン付き）
│   ├── PlayerAnalysis.php      選手の戦績分析（対戦成績・統計）
│   ├── RoundResult.php         ラウンド成績の取得
│   ├── Standing.php            総合順位の取得
│   ├── TableInfo.php           卓情報・メンバーの取得
│   ├── Tournament.php          大会データの取得
│   └── TournamentMeta.php      大会メタ情報の取得
├── enums/
│   ├── DayOfWeek.php           曜日（日付から曜日ラベルを取得）
│   ├── EventType.php           イベント種別（最強位戦・鳳凰位戦等）
│   ├── HanRestriction.php      翻縛り（一翻・二翻・四翻）
│   ├── PlayerMode.php          対局人数（三麻・四麻）
│   ├── RoundType.php           局数（半荘・東風・一局）
│   ├── ToggleRule.php          ON/OFF設定（喰いタン・トビ等）
│   └── TournamentStatus.php    大会ステータス（準備中・開催中・終了）
├── config/
│   └── database.php            DB接続・ヘルパー関数・環境変数読み込み
├── templates/
│   ├── header.php              共通ヘッダー
│   └── footer.php              共通フッター
├── db/
│   ├── migrations/             Phinxマイグレーション
│   └── seeds/                  Phinxシーダー
├── .devcontainer/
│   ├── devcontainer.json       GitHub Codespaces設定
│   └── setup.sh                Codespaces初回セットアップ
├── start.sh                    起動スクリプト（マイグレーション→Apache）
├── Dockerfile                  Render デプロイ / ローカルDocker用
├── docker-compose.yml          ローカル開発用
├── render.yaml                 Renderデプロイ設定
├── composer.json               PHP依存・autoload設定
├── phinx.php.example           Phinx設定テンプレート
├── .env.example                環境変数テンプレート
├── tests/
│   └── e2e/                     E2Eテスト（Playwright）
├── docs/
│   └── database.md              データベース設計書
├── .gitignore
└── .dockerignore
```

`public/` のみがWebサーバーから公開されます。`config/`、`models/`、`enums/`、`templates/`、`db/`、`vendor/` はWeb経由でアクセスできません。

Neon無料枠のスリープからの復帰を考慮し、DB接続はリトライ付き（最大3回、指数バックオフ）で行います。

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

#### 開発の流れ

1. VS Codeでファイルを編集・保存
2. ポートタブからブラウザを開いてリロードで確認
3. ターミナルでPhinxコマンドやgit操作を実行

#### PHPサーバーが停止した場合

ターミナルで手動起動できます:

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

### ブラウザのみ

`public/index.php` をPHPサーバー経由で開くとフロントエンドが動作します。

## Phinx（DBマイグレーション）

### コマンド

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

## 機能

### テーマ切替

ライトテーマ（デフォルト）とダークテーマを右上のトグルで切り替え可能。設定はlocalStorageに保存されます。

### モバイル最適化

768px以下の画面幅では backdrop-filter 無効化やアニメーション簡略化が適用されます。`prefers-reduced-motion: reduce` でアニメーション一括停止にも対応。

### OGP対応

SNSやアプリでURLを共有した際にサムネイル・説明文が表示されます。

### エラーページ

- 存在しないURLにアクセスすると404ページを表示
- サーバーエラー時は500ページを表示（PHPが動かなくても表示できる静的HTML）

### メンテナンスモード

`public/.htaccess` の該当行のコメントを外すと、全ページがメンテナンスページにリダイレクトされます。

## 監視

UptimeRobotによりRender本番環境の死活監視を行っています。ダウン検知時はアラート通知されます。

## データ管理

すべてのページはNeonデータベースからリアルタイムにデータを取得します。

### ページ遷移

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
  │    └→ tournament.php（大会詳細）
  │         ├→ tournament_edit.php（大会情報編集）
  │         ├→ tournament_players.php（選手登録）
  │         ├→ table_new.php（卓作成）
  │         ├→ table.php（卓管理：日程・牌譜URL・結果登録・完了）
  │         └→ interview_edit.php（優勝インタビュー設定・大会完了）
  └→ interview.php（優勝インタビュー）
```

### 選手管理（CRUD）

- **登録**: players.php → 「選手を追加」ボタン → player_new.php
- **編集**: player.php → 鉛筆アイコン → player_edit.php（呼称・キャラクター変更）
- **削除**: player_edit.php 下部（大会参加済みの選手は削除不可）

### 大会管理

`tournaments` テーブルで複数大会を管理します。詳細は `docs/database.md` を参照。

## E2Eテスト

Playwright による自動テスト。Docker 起動中に実行する。

```bash
# 初回セットアップ
cd tests/e2e && npm install && npx playwright install chromium --with-deps

# テスト実行
npx playwright test              # 全テスト（約2分）
npx playwright test --headed     # ブラウザ表示あり
npx playwright test pages/       # ページテストのみ
npx playwright test features/    # 機能テストのみ
```

`git push` 時に Claude Code の hook で自動実行される（Docker 未起動時はスキップ）。

### テスト構成

| ディレクトリ | 内容 |
|---|---|
| `tests/e2e/pages/` | ページ別テスト（表示・CRUD・バリデーション・404） |
| `tests/e2e/features/` | 機能テスト（テーマ切替・クリーンURL・セキュリティ・ナビゲーション） |
| `tests/e2e/helpers/` | 共通ユーティリティ（テストプレイヤー作成・削除） |
