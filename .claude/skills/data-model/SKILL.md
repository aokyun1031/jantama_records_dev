---
name: data-model
description: DBモデル層の構成とクエリパターン。データ取得ページやモデル追加時に参照する
---

# データモデル

## テーブル関係

```
players (1) ──→ (N) table_players ←── (N) tables_info (1)
players (1) ──→ (N) round_results
players (1) ──→ (1) standings
tournament_meta（キーバリューストア）
```

## モデル一覧（`models/` ディレクトリ）

### Player
```php
Player::all();           // 全選手を取得
Player::find($id);       // IDで1件取得（見つからなければnull）
Player::count();         // 選手数を取得
```

### Standing
```php
Standing::all();                  // 総合順位を取得（選手名付き）
Standing::findByPlayer($playerId); // 特定選手の順位を取得
```

### RoundResult
```php
RoundResult::byRound($roundNumber); // 特定ラウンドの成績（選手名付き、スコア降順）
RoundResult::byPlayer($playerId);   // 特定選手の全ラウンド成績
```

### TableInfo
```php
TableInfo::byRound($roundNumber); // 特定ラウンドの卓情報（メンバー付き、卓ごとにグループ化済み）
```

### TournamentMeta
```php
TournamentMeta::all();                // 全メタ情報を連想配列で取得
TournamentMeta::get($key, $default);  // 特定キーの値を取得
```

## 新しいモデルの追加手順

1. `models/` にクラスファイルを作成
2. `declare(strict_types=1)` を付ける
3. `getDbConnection()` でPDOを取得
4. ユーザー入力がある場合は必ずプリペアドステートメントを使う
5. `composer dump-autoload` を実行

## ページからの呼び出しパターン

```php
// fetchData() でエラーハンドリングを共通化
['data' => $players, 'error' => $error] = fetchData(fn() => Player::all());
```

## DB接続

- `getDbConnection()` はNeonスリープ対応（リトライ3回、指数バックオフ）
- 同一リクエスト内でPDOインスタンスを再利用（staticキャッシュ）
