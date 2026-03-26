---
name: migration
description: Phinxマイグレーションの作成手順と規約
---

# Phinxマイグレーション

## 作成コマンド

```bash
# Codespaces
php vendor/bin/phinx create MigrationName

# Docker
docker compose exec web php vendor/bin/phinx create MigrationName
```

## 命名規約

- パスカルケースで、操作 + 対象を表す名前にする
- 例: `AddScoreToPlayers`, `CreateMatchesTable`, `DropScheduleFromTablesInfo`

## ファイル

- 配置先: `db/migrations/`
- ファイル名は自動生成（`YYYYMMDDHHMMSS_migration_name.php`）

## 書き方の規約

- `declare(strict_types=1)` を先頭に付ける
- `up()` と `down()` の両方を必ず実装する（ロールバック可能にする）
- Phinx のテーブルビルダーAPI を使う（生SQLは避ける）
- 外部キーには `ON DELETE CASCADE` を設定する
- 文字列カラムには `limit` を指定する

## 既存テーブル構成

参照: `db/migrations/20260317000000_create_initial_schema.php`

- `players` - 選手マスタ（id, name）
- `tables_info` - 卓情報（round_number, table_name, schedule, done）
- `table_players` - 卓メンバー（table_id → tables_info, player_id → players, seat_order）
- `round_results` - ラウンド成績（player_id → players, round_number, score, is_above_cutoff）
- `standings` - 総合順位（player_id → players, rank, total, pending, eliminated_round）
- `tournament_meta` - 大会メタ情報（key, value）

## デプロイ

- 本番: Renderデプロイ時に `start.sh` 経由で自動実行される
- 開発: 手動で `php vendor/bin/phinx migrate` を実行
