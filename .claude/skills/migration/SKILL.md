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
- PK変更や制約変更など API で対応できない場合のみ `$this->execute()` を使う
- 外部キーには `ON DELETE CASCADE` を設定する
- 文字列カラムには `limit` を指定する
- 大会スコープのテーブルには `tournament_id` カラム（FK → tournaments）を追加する

## 既存テーブル構成

### 初期スキーマ（20260317000000_create_initial_schema）

- `players` - 選手マスタ（id, name, nickname）
- `tables_info` - 卓情報（round_number, table_name, schedule, done）
- `table_players` - 卓メンバー（table_id → tables_info, player_id → players, seat_order）
- `round_results` - ラウンド成績（player_id → players, round_number, score, is_above_cutoff）
- `standings` - 総合順位（player_id → players, rank, total, pending, eliminated_round）
- `tournament_meta` - 大会メタ情報（key, value）

### 大会対応（20260327000000_add_tournaments_support）

- `tournaments` テーブル新規作成（id, name, status, created_at）
- `tables_info`, `round_results` に `tournament_id` カラム + FK追加
- `standings` のPKを `(tournament_id, player_id)` の複合PKに変更
- `tournament_meta` のPKを `(tournament_id, key)` の複合PKに変更
- `round_results` のユニーク制約を `(tournament_id, player_id, round_number)` に変更

### 決勝データ投入（20260327100000_add_finals_data）

- 決勝（4回戦）の卓情報・成績データを投入（冪等: 既存データがあればスキップ）
- スタンディングを決勝結果反映済みの正しい順位・スコアに更新
- tournament_meta の current_round, remaining_players を更新

### 選手名を正式名称に変更（20260329000000_rename_players_to_official_names）

- players.name をアプリ内の正式名称に一括更新
- tournament_meta の record_player も合わせて更新

### 選手に呼称を追加（20260329100000_add_nickname_to_players）

- players テーブルに nickname カラム（VARCHAR(50), NULL可）を追加
- 全20名の呼称（サイト表示用の通称）を一括セット

## デプロイ

- 本番: Renderデプロイ時に `start.sh` 経由で自動実行される
- 開発: 手動で `php vendor/bin/phinx migrate` を実行
