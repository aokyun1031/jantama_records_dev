---
name: data-model
description: DBモデル層の構成とクエリパターン。データ取得ページやモデル追加時に参照する
---

# データモデル

## テーブル関係

```
tournaments (1) ──→ (N) tables_info (1) ──→ (N) table_players ←── (N) players
tournaments (1) ──→ (N) round_results ←── (N) players
tournaments (1) ──→ (N) standings ←── (N) players
tournaments (1) ──→ (N) tournament_meta
characters (1) ──→ (N) players
```

## モデル一覧（`models/` ディレクトリ）

### Tournament
```php
Tournament::all();              // 全大会を取得（作成日降順）
Tournament::find($id);          // IDで1件取得（見つからなければnull）
Tournament::byPlayer($playerId); // 特定選手が参加した大会一覧（順位・スコア付き）
```

### Character
```php
Character::all();        // 全キャラクターを取得（名前昇順）
Character::find($id);   // IDで1件取得（見つからなければnull）
```

### Player
```php
Player::all();           // 全選手を取得（名前昇順、キャラアイコン付き）
Player::find($id);       // IDで1件取得（キャラアイコン付き、見つからなければnull）
Player::count();         // 選手数を取得
```

### PlayerAnalysis
```php
PlayerAnalysis::summary($playerId);      // 通算成績サマリー（参加回数・平均スコア・最高/最低・通過率）
PlayerAnalysis::avgTableRank($playerId); // 卓内平均着順（全大会合算）
PlayerAnalysis::headToHead($playerId);   // 同卓対戦成績（対戦相手ごとの勝敗・スコア差）
PlayerAnalysis::scoreHistory($playerId); // スコア推移（全大会・時系列順）
```

### Standing
```php
Standing::all($tournamentId);                          // 大会の総合順位を取得（選手名付き）
Standing::finalists($tournamentId);                    // 決勝進出者を取得（ラウンドスコアのトレンド付き）
Standing::findByPlayer($tournamentId, $playerId);      // 大会での特定選手の順位を取得
```

### RoundResult
```php
RoundResult::byRound($tournamentId, $roundNumber); // 大会の特定ラウンド成績（選手名付き、スコア降順）
RoundResult::byPlayer($tournamentId, $playerId);   // 大会での特定選手の全ラウンド成績
```

### TableInfo
```php
TableInfo::byRound($tournamentId, $roundNumber);                // 大会の特定ラウンド卓情報（メンバー付き、卓ごとにグループ化済み）
TableInfo::byPlayerAndTournament($tournamentId, $playerId);     // 大会で特定選手が参加した卓情報（同卓メンバーのスコア付き、ラウンドごと）
```

### TournamentMeta
```php
TournamentMeta::all($tournamentId);                        // 大会の全メタ情報を連想配列で取得
TournamentMeta::get($tournamentId, $key, $default);        // 大会の特定キーの値を取得
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
