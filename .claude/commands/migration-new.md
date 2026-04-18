---
description: Phinx マイグレーションを規約に従って新規作成する
argument-hint: "<MigrationName>"
allowed-tools: Bash, Read, Edit, Write, Glob
---

`migration` スキル（`.claude/skills/migration/SKILL.md`）の規約に従ってマイグレーションを作成する。

## 引数

ユーザーが渡したマイグレーション名: `$ARGUMENTS`

**必須**。パスカルケース、操作 + 対象の形式（例: `AddScoreToPlayers`, `CreateMatchesTable`, `DropScheduleFromTablesInfo`）。引数が無い場合はユーザーに名前を尋ねる。

## 実行手順

1. 命名規約チェック: `$ARGUMENTS` がパスカルケースで操作 + 対象の形式か確認。違反なら修正案を提示して確認を取る

2. 既存マイグレーションのスタイル確認: `db/migrations/` の最新 2〜3 ファイルを Read して実装パターンを把握

3. Phinx で雛形生成:

   ```bash
   # Codespaces
   php vendor/bin/phinx create $ARGUMENTS
   # Docker
   docker compose exec web php vendor/bin/phinx create $ARGUMENTS
   ```

   環境判定: `command -v php` が通れば Codespaces、通らなければ Docker

4. 生成されたファイル（`db/migrations/YYYYMMDDHHMMSS_*.php`）に以下を反映:
   - 先頭に `declare(strict_types=1)`
   - `up()` と `down()` 両方実装（ロールバック可能に）
   - Neon 相性のため、原則 `$this->execute('...')` で直接 SQL を書く
   - カラム追加・テーブル作成も `ALTER TABLE` / `CREATE TABLE` を `$this->execute()`
   - 外部キーは `ON DELETE CASCADE`
   - 文字列カラムは `limit` 指定（例: `VARCHAR(100)`）
   - 大会スコープのテーブルには `tournament_id` + FK → `tournaments`
   - データ投入系は**冪等性**を確保（既存データチェック → スキップ）

5. ユーザーにマイグレーション内容を提示して合意を得る

6. 合意後、dev で実行:

   ```bash
   php vendor/bin/phinx status
   php vendor/bin/phinx migrate
   ```

7. 失敗時はロールバックと修正を繰り返す。本番投入は dev で検証後

## 冪等性パターン

データ投入を含む場合は既存データチェックを必ず入れる（本番 Render は push ごとに `start.sh` で migrate を自動実行するため）:

```php
$count = $this->fetchRow('SELECT COUNT(*) AS c FROM ... WHERE ...')['c'];
if ((int) $count > 0) {
    return;
}
```

## 注意

- `--no-verify` 等で hook をスキップしない
- 本番投入前に dev で必ず `migrate` + `rollback` + `migrate` の往復確認
- Phinx テーブルビルダーが Neon で失敗することがある → `$this->execute()` で直接 SQL が安全
