# ローカル開発環境の DB 初期化

ローカル Docker Compose の Postgres コンテナは、マイグレーションではなく以下 2 ファイルで初期化する。

| ファイル | 内容 | 適用タイミング |
|---|---|---|
| `db/current_schema.sql` | Neon から pg_dump したスキーマ定義 | db コンテナ初回起動時に `/docker-entrypoint-initdb.d/01-schema.sql` として自動適用 |
| `db/seed_data.sql` | Neon dev から pg_dump したデータ（`--data-only --column-inserts`） | `DevDataSeeder` が TRUNCATE CASCADE → 流し込み |

## なぜマイグを使わない？

旧マイグ（`20260327000000_add_tournaments_support.php` 以降）は本番の `init.sql` で作成された制約名を前提に `DROP CONSTRAINT` している。まっさらな空 DB に `phinx migrate` を通すと途中で失敗する。

本番 Neon は `init.sql` 起点なので成立しているが、ローカルで同じ流れを再現するのは手間（本番 init.sql は消失済み）。

そこで **ローカルは pg_dump の生 SQL でスキーマを再現** し、マイグは本番運用のみに使う構成にしている。

## 基本運用

```bash
docker compose up -d                                # 初回: スキーマ自動適用
docker compose exec web php vendor/bin/phinx seed:run   # データ投入
```

データを壊しても:

```bash
docker compose exec web php vendor/bin/phinx seed:run   # TRUNCATE CASCADE → 再投入
```

スキーマも含めて完全リセット:

```bash
docker compose down -v          # volume pgdata 削除
docker compose up -d            # initdb.d でスキーマ再構築
docker compose exec web php vendor/bin/phinx seed:run
```

## dump 更新手順

Neon dev のスキーマ/データが更新されて、ローカル開発でも最新を使いたい場合。

### スキーマ更新

```bash
docker run --rm postgres:17-alpine pg_dump \
  "<Neon dev 接続文字列>" \
  --schema-only --no-owner --no-privileges --no-comments \
  --schema=public --exclude-table=phinxlog \
  | sed -E '/^\\(restrict|unrestrict) /d; /^CREATE SCHEMA public;$/d' \
  > db/current_schema.sql
```

`\restrict` / `\unrestrict` は pg_dump 17 が埋め込む psql 専用コマンドで PDO 非対応のため削除。`CREATE SCHEMA public;` は既存スキーマと衝突するため削除。

適用:

```bash
docker compose down -v && docker compose up -d
```

### データ更新

```bash
docker run --rm postgres:17-alpine pg_dump \
  "<Neon dev 接続文字列>" \
  --data-only --no-owner --no-privileges \
  --schema=public --exclude-table=phinxlog \
  --column-inserts \
  | sed -E '/^\\(restrict|unrestrict) /d' \
  > db/seed_data.sql
```

適用:

```bash
docker compose exec web php vendor/bin/phinx seed:run
```

### Neon 接続文字列の取得

- **Neon dev**: `.env` のコメント欄 or Neon ダッシュボード → Branches → dev → Connection Details
- 本番（production）は触らない（ダウンタイム回避のため）

## 新テーブル追加時の反映

1. 本番 Neon に適用される新マイグを作成（通常どおり `phinx create`）
2. Neon production に反映されるのを待つ（main マージ → Render デプロイ）
3. Neon dev ブランチを production から再作成（任意）
4. 上記「dump 更新手順」で `current_schema.sql` と `seed_data.sql` を再取得
5. `DevDataSeeder` の TRUNCATE 対象テーブル一覧に新テーブルを追加

## CI / hook 化のアイデア（未実装）

- main への push 後に GitHub Actions で Neon から pg_dump → PR で `db/current_schema.sql` / `db/seed_data.sql` を更新
- ローカルで `docker compose down -v && up -d && phinx seed:run` をワンコマンド化するスクリプト（`scripts/reset-db.sh` 等）
