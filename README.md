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
│   ├── index.html              メインページ（HTML構造）
│   ├── index.php               同内容（Docker/Apache用）
│   ├── interview.html          優勝インタビュー（静的）
│   ├── interview.php           優勝インタビュー（PHP版）
│   ├── players.php             選手一覧ページ
│   ├── 404.php                 404エラーページ
│   ├── 500.html                500エラーページ（静的HTML）
│   ├── maintenance.html        メンテナンスページ（静的HTML）
│   ├── .htaccess               セキュリティヘッダー・URL書き換え・エラーページ設定
│   ├── css/
│   │   ├── base.css            変数・リセット・レイアウト・Hero・プログレス
│   │   ├── components.css      順位表・タブ・卓カード・結果・レコード
│   │   ├── finals.css          決勝卓セクションの演出・アニメーション
│   │   ├── finals-countdown.css 決勝カウントダウン表示
│   │   ├── champion.css        優勝おめでとうセクション
│   │   ├── mahjong-deco.css    麻雀牌の装飾
│   │   ├── theme-dark.css      ダークテーマ（champion含む全セクション対応）
│   │   └── theme-toggle.css    テーマ切替トグル
│   ├── js/
│   │   ├── data.js             大会データ（順位・卓・各回戦結果）
│   │   ├── render.js           DOM描画・タブ切替
│   │   ├── effects.js          パーティクル・スクロールアニメ・決勝エフェクト・優勝エフェクト
│   │   ├── theme-toggle.js     テーマ切替（localStorage永続化）
│   │   └── countdown.js        決勝カウントダウン表示
│   └── img/
│       └── nino.png            優勝者アバター画像
├── models/
│   ├── Player.php              選手データの取得
│   ├── Standing.php            総合順位の取得
│   ├── RoundResult.php         ラウンド成績の取得
│   ├── TableInfo.php           卓情報・メンバーの取得
│   └── TournamentMeta.php      大会メタ情報の取得
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
├── .gitignore
└── .dockerignore
```

`public/` のみがWebサーバーから公開されます。`config/`、`models/`、`templates/`、`db/`、`vendor/` はWeb経由でアクセスできません。

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

`public/index.html` をブラウザで直接開くだけでフロントエンドは動作します（DB不要）。

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

768px以下の画面幅では以下のパフォーマンス最適化が適用されます:

- backdrop-filter（blur）の無効化
- 決勝セクションのエフェクト（ember・energy-line・confetti）の非表示
- 背景アニメーション・装飾アニメーションの簡略化
- パーティクル数の削減
- フォントサイズの`clamp()`によるレスポンシブ対応

### OGP対応

SNSやアプリでURLを共有した際にサムネイル・説明文が表示されます。

### エラーページ

- 存在しないURLにアクセスすると404ページを表示
- サーバーエラー時は500ページを表示（PHPが動かなくても表示できる静的HTML）

### メンテナンスモード

`public/.htaccess` の該当行のコメントを外すと、全ページがメンテナンスページにリダイレクトされます。

## 監視

UptimeRobotによりRender本番環境の死活監視を行っています。ダウン検知時はアラート通知されます。

## データ更新方法

現在、大会データは `public/js/data.js` にハードコードされています。

- `standings` : 総合順位・累計ポイント・各回戦スコア・敗退ラウンド
- `r1Tables` / `r2Tables` / `r3Tables` : 各回戦の卓割り
- `r1Above` / `r1Below` 等 : 各回戦の個別結果（通過 / 敗退）

DB連携後はPHP経由でNeonからデータを取得する形に移行予定です。
