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

## ファイル構成

```
├── index.html                  メインページ（HTML構造）
├── index.php                   同内容（Docker/Apache用）
├── css/
│   ├── base.css                変数・リセット・レイアウト・Hero・プログレス
│   ├── components.css          順位表・タブ・卓カード・結果・レコード
│   ├── finals.css              決勝卓セクションの演出・アニメーション
│   ├── finals-countdown.css    決勝カウントダウン表示
│   ├── champion.css            優勝おめでとうセクション
│   ├── mahjong-deco.css        麻雀牌の装飾
│   ├── theme-dark.css          ダークテーマ（champion含む全セクション対応）
│   └── theme-toggle.css        テーマ切替トグル
├── js/
│   ├── data.js                 大会データ（順位・卓・各回戦結果）
│   ├── render.js               DOM描画・タブ切替
│   ├── effects.js              パーティクル・スクロールアニメ・決勝エフェクト・優勝エフェクト
│   ├── theme-toggle.js         テーマ切替（localStorage永続化）
│   └── countdown.js            決勝カウントダウン表示
├── img/
│   └── nino.png                優勝者アバター画像
├── includes/
│   └── db.php                  PostgreSQL接続（DATABASE_URL対応）
├── db/
│   ├── init.sql                初期スキーマ（手動投入用）
│   ├── seed.sql                初期データ（手動投入用）
│   ├── migrations/             Phinxマイグレーション
│   └── seeds/                  Phinxシーダー
├── .devcontainer/
│   ├── devcontainer.json       GitHub Codespaces設定
│   └── setup.sh                Codespaces初回セットアップ
├── Dockerfile                  Render デプロイ / ローカルDocker用
├── docker-compose.yml          ローカル開発用
├── render.yaml                 Renderデプロイ設定
├── composer.json               PHP依存（Phinx）
├── phinx.php.example           Phinx設定テンプレート
├── .env.example                環境変数テンプレート
├── .gitignore
└── .dockerignore
```

## 環境構成

```
本番:  Render ──→ Neon (productionブランチ)
開発:  Codespaces / Docker ──→ Neon (devブランチ)
監視:  UptimeRobot ──→ Render本番
```

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

## 監視

UptimeRobotによりRender本番環境の死活監視を行っています。ダウン検知時はアラート通知されます。

## 開発環境セットアップ

### GitHub Codespaces（推奨）

1. GitHubリポジトリ → **Code** → **Codespaces** → **Create codespace**
2. 初回起動時に `.devcontainer/setup.sh` が自動実行される
3. `DATABASE_URL` の設定（以下のいずれか）:
   - **Codespaces Secrets**（推奨）: GitHub → Settings → Codespaces → Secrets → `DATABASE_URL` を追加
   - **手動**: `.env` を編集して Neon devブランチの接続文字列を設定

### Docker Compose（ローカル）

```bash
cp .env.example .env
# .env にNeon devブランチの接続文字列を設定
cp phinx.php.example phinx.php
docker compose up -d
docker compose exec web composer install

# Phinxコマンド
docker compose exec web php vendor/bin/phinx status
docker compose exec web php vendor/bin/phinx migrate
docker compose exec web php vendor/bin/phinx seed:run
```

起動後 `http://localhost:8080` でアクセスできます。

### ブラウザのみ

`index.html` をブラウザで直接開くだけでフロントエンドは動作します（DB不要）。

## Phinx（DBマイグレーション）

### コマンド

```bash
# Codespaces内
php vendor/bin/phinx status              # ステータス確認
php vendor/bin/phinx migrate             # マイグレーション実行
php vendor/bin/phinx seed:run            # シーダー実行
php vendor/bin/phinx create AddNewColumn # 新しいマイグレーション作成
php vendor/bin/phinx rollback            # ロールバック
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
2. 確認OK → productionに対してマイグレーション実行
3. devブランチを削除 → 再作成（productionのコピーでクリーンに戻す）

### productionへのマイグレーション反映

```bash
# .envのDATABASE_URLをproductionの接続文字列に一時変更して実行
php vendor/bin/phinx migrate
```

## データ更新方法

現在、大会データは `js/data.js` にハードコードされています。

- `standings` : 総合順位・累計ポイント・各回戦スコア・敗退ラウンド
- `r1Tables` / `r2Tables` / `r3Tables` : 各回戦の卓割り
- `r1Above` / `r1Below` 等 : 各回戦の個別結果（通過 / 敗退）

DB連携後はPHP経由でNeonからデータを取得する形に移行予定です。
