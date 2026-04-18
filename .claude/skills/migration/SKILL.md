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

- パスカルケース、操作 + 対象
- 例: `AddScoreToPlayers`, `CreateMatchesTable`, `DropScheduleFromTablesInfo`

## ファイル配置

- `db/migrations/` 配下に配置される
- ファイル名は自動生成: `YYYYMMDDHHMMSS_migration_name.php`
- 既存マイグレーションの内容は `db/migrations/` を直接参照（ここには記載しない）

## 書き方の規約

- 先頭に `declare(strict_types=1)`
- `up()` と `down()` 両方を必ず実装（ロールバック可能）
- Neon（サーバーレスPG）相性問題により Phinx テーブルビルダーが失敗する場合あり→`$this->execute()` で直接SQL
- カラム追加・テーブル作成も `$this->execute()` で問題ない（`ALTER TABLE`, `CREATE TABLE`）
- 外部キーには `ON DELETE CASCADE`
- 文字列カラムには `limit` を指定
- 大会スコープのテーブルには `tournament_id` カラム + FK（→ tournaments）

## 冪等性（重要）

本番の Render は push ごとに `start.sh` 経由で `phinx migrate` を実行する。
データ投入系マイグレーションは既存データがあればスキップするロジックを入れる。

```php
// 例: 既存データを検出してスキップ
$count = $this->fetchRow('SELECT COUNT(*) AS c FROM standings WHERE tournament_id = 1')['c'];
if ((int) $count > 0) {
    return;
}
```

## 実行コマンド

```bash
php vendor/bin/phinx status        # ステータス確認
php vendor/bin/phinx migrate       # マイグレーション実行
php vendor/bin/phinx seed:run      # シーダー実行
php vendor/bin/phinx rollback      # ロールバック

# 既存DBをPhinx管理下に置く（init.sql適用済み環境）
php vendor/bin/phinx migrate --fake
```

## デプロイ

- 本番: Render デプロイ時に `start.sh` 経由で自動実行
- 開発: 手動で `php vendor/bin/phinx migrate`
- マイグレーション失敗時は Apache が起動しない（`set -e`）→ 本番投入前に必ず dev で検証

## 関連スキル

- `data-model` — テーブル構成・モデルとの対応
