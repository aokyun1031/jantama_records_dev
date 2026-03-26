---
name: data-model
description: DBテーブル構成とクエリパターン。データ取得・表示ページを作る際に参照する
---

# データモデル

## テーブル関係

```
players (1) ──→ (N) table_players ←── (N) tables_info (1)
players (1) ──→ (N) round_results
players (1) ──→ (1) standings
tournament_meta（キーバリューストア）
```

## 主要クエリパターン

### 総合順位の取得（standings + players）
```php
$stmt = $pdo->query('
    SELECT s.rank, p.name, s.total, s.pending, s.eliminated_round
    FROM standings s
    JOIN players p ON p.id = s.player_id
    ORDER BY s.rank
');
```

### 特定ラウンドの成績（round_results + players）
```php
$stmt = $pdo->prepare('
    SELECT p.name, r.score, r.is_above_cutoff
    FROM round_results r
    JOIN players p ON p.id = r.player_id
    WHERE r.round_number = ?
    ORDER BY r.score DESC
');
$stmt->execute([$roundNumber]);
```

### 卓情報と卓メンバー（tables_info + table_players + players）
```php
$stmt = $pdo->prepare('
    SELECT t.table_name, t.schedule, t.done, p.name, tp.seat_order
    FROM tables_info t
    JOIN table_players tp ON tp.table_id = t.id
    JOIN players p ON p.id = tp.player_id
    WHERE t.round_number = ?
    ORDER BY t.table_name, tp.seat_order
');
$stmt->execute([$roundNumber]);
```

## DB接続

- `config/database.php` の `getDbConnection()` を使う
- PDOオプション: `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`
- ユーザー入力をSQLに含める場合は必ずプリペアドステートメントを使う
