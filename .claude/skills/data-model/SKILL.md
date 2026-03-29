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

### Character
```php
Character::all();        // 全キャラクターを取得（名前昇順）
Character::find($id);   // IDで1件取得（見つからなければnull）
```

### Player
```php
Player::all();                                     // 全選手（キャラアイコン付き、名前昇順）
Player::find($id);                                 // 1件取得（character_id, character_icon付き）
Player::create($name, $nickname, $characterId);    // 新規登録（IDを返す）
Player::existsByName($name);                       // 名前の重複チェック（bool）
Player::update($id, $nickname, $characterId);      // 呼称・キャラクター更新
Player::hasTournaments($id);                       // 大会参加の有無（bool）
Player::delete($id);                               // 削除（大会未参加の場合のみ使用）
Player::count();                                   // 選手数
```

### Tournament
```php
Tournament::all();              // 全大会（作成日降順）
Tournament::find($id);          // IDで1件取得
Tournament::byPlayer($playerId); // 特定選手が参加した大会一覧（順位・スコア付き）
```

### Standing
```php
Standing::all($tournamentId);                          // 総合順位（選手名付き）
Standing::finalists($tournamentId);                    // 決勝進出者（トレンド・キャラアイコン付き）
Standing::findByPlayer($tournamentId, $playerId);      // 特定選手の順位
```

### RoundResult
```php
RoundResult::byRound($tournamentId, $roundNumber); // 特定ラウンド成績（スコア降順）
RoundResult::byPlayer($tournamentId, $playerId);   // 特定選手の全ラウンド成績
```

### TableInfo
```php
TableInfo::byRound($tournamentId, $roundNumber);                // ラウンドの卓情報（メンバー付き）
TableInfo::byPlayerAndTournament($tournamentId, $playerId);     // 選手の参加卓情報（スコア付き）
```

### TournamentMeta
```php
TournamentMeta::all($tournamentId);                        // 全メタ情報を連想配列で取得
TournamentMeta::get($tournamentId, $key, $default);        // 特定キーの値を取得
```

### PlayerAnalysis
```php
PlayerAnalysis::summary($playerId);      // 通算成績サマリー
PlayerAnalysis::avgTableRank($playerId); // 卓内平均着順
PlayerAnalysis::headToHead($playerId);   // 同卓対戦成績
PlayerAnalysis::scoreHistory($playerId); // スコア推移
```

## 新しいモデルの追加手順

1. `models/` にクラスファイルを作成
2. `declare(strict_types=1)` を付ける
3. `getDbConnection()` でPDOを取得
4. ユーザー入力は必ずプリペアドステートメント
5. `composer dump-autoload` を実行
6. `data-model/SKILL.md` のモデル一覧を更新

## ページからの呼び出しパターン

```php
// 読み取り
['data' => $players] = fetchData(fn() => Player::all());

// プレイヤー取得（見つからなければ404）
$player = requirePlayer($playerId);

// 書き込み（try/catch で囲む）
try {
    Player::update($id, $nickname, $characterId);
    regenerateCsrfToken();
    header('Location: ...');
    exit;
} catch (PDOException $e) {
    error_log('[DB] ' . $e->getMessage());
    $validationError = '保存に失敗しました。';
}
```

## DB接続

- `getDbConnection()` はNeonスリープ対応（リトライ3回、指数バックオフ）
- 同一リクエスト内でPDOインスタンスを再利用（staticキャッシュ）
- キャッシュ済みPDOは接続切れを自動検出して再接続
