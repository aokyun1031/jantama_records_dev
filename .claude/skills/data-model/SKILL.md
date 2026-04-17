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
tournaments (1) ──→ (N) interviews
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
Tournament::all();                                              // 全大会（作成日降順）
Tournament::find($id);                                          // IDで1件取得
Tournament::findWithMeta($id);                                  // メタ情報付きで1件取得
Tournament::allWithDetails();                                   // 一覧（参加人数・イベント種別・優勝者付き）
Tournament::createWithDetails($name, $meta, $playerIds);        // 大会作成（メタ+選手登録）
Tournament::updateDetails($id, $name, $meta);                   // 大会名・メタ更新
Tournament::playerIds($id);                                     // 参加選手ID一覧
Tournament::playedPlayerIds($id);                               // 卓に割り当て済み選手ID
Tournament::updatePlayers($id, $playerIds);                     // 選手の追加・削除（対局済み保護あり）
Tournament::start($id);                                         // ステータスを開催中に
Tournament::complete($id);                                      // ステータスを完了に
Tournament::processRoundCompletion($tournamentId, $roundNum);   // ラウンド完了処理（勝ち抜き判定含む）
Tournament::delete($id);                                        // 削除（CASCADE）
Tournament::byPlayer($playerId);                                // 特定選手が参加した大会一覧
```

### Standing
```php
Standing::all($tournamentId);                                   // 総合順位（ニックネーム・アイコン付き、勝ち抜き→敗退順）
Standing::finalists($tournamentId);                             // 決勝進出者（トレンド・キャラアイコン付き）
Standing::champion($tournamentId);                              // 優勝者（eliminated_round=0で最高ポイント）
Standing::findByPlayer($tournamentId, $playerId);               // 特定選手の順位
Standing::activePlayerIds($tournamentId);                       // 勝ち抜き中の選手ID一覧
Standing::totalMap($tournamentId);                              // 選手ID→合計ポイントのマップ
Standing::updateTotals($tournamentId);                          // round_resultsから全順位を再計算
Standing::processRoundAdvancement($tournamentId, $roundNum, $advanceCount); // 勝ち抜き判定
```

### RoundResult
```php
RoundResult::byRound($tournamentId, $roundNumber);  // 特定ラウンド成績（ニックネーム・アイコン付き）
RoundResult::byPlayer($tournamentId, $playerId);    // 特定選手の全ラウンド成績
RoundResult::saveScores($tournamentId, $roundNumber, $scores); // スコア一括保存（UPSERT）
```

### TableInfo
```php
TableInfo::find($id);                                           // 卓を1件取得
TableInfo::findWithPlayers($id);                                // 選手一覧+既存スコア付きで取得
TableInfo::byTournament($tournamentId);                         // ラウンド別卓一覧（アイコン・スコア付き）
TableInfo::byRound($tournamentId, $roundNumber);                // ラウンドの卓情報（アイコン付き）
TableInfo::byPlayerAndTournament($tournamentId, $playerId);     // 選手の参加卓情報（スコア付き）
TableInfo::playerGroupsByRound($tournamentId, $roundNumber);    // 卓ごとの選手IDグループ（同卓回避用）
TableInfo::create($tournamentId, $roundNumber, $tableName, $playerIds); // 卓作成
TableInfo::createBatch($tournamentId, $roundNumber, $tables);   // 複数卓一括作成
TableInfo::updateSchedule($id, $playedDate, $dayOfWeek, $playedTime); // 対局日更新
TableInfo::markDone($id);                                       // 卓を完了に
TableInfo::delete($id);                                         // 卓削除（CASCADE）

// 牌譜URL（ゲーム別）
TablePaifuUrl::byTable($tableId);                               // 卓の牌譜URL一覧
TablePaifuUrl::saveAll($tableId, [1 => $url1, 2 => $url2]);    // 一括保存（UPSERT）
```

### TournamentMeta
```php
TournamentMeta::all($tournamentId);                        // 全メタ情報を連想配列で取得
TournamentMeta::get($tournamentId, $key, $default);        // 特定キーの値を取得
TournamentMeta::set($tournamentId, $key, $value);          // 値を設定（UPSERT）
```

### TournamentRecords
round_results / table_players から算出するトーナメントレコード群。
tournament_view の「トーナメントレコード」セクション向け。

```php
TournamentRecords::all($tournamentId);                     // 下記3つをまとめて取得
TournamentRecords::highestRoundScore($tournamentId);       // 1回戦単体の最高得点
TournamentRecords::mostTopFinishes($tournamentId);         // 卓内1位を最も多く取った選手
TournamentRecords::largestTableSpread($tournamentId);      // 単卓内の最大得点差
```

### Interview
```php
Interview::byTournament($tournamentId);             // インタビューQ&A一覧
Interview::save($tournamentId, $items);              // Q&A一括保存（全削除→再作成）
```

### PlayerAnalysis
全メソッドは第2引数で `array $selectedEventTypes = []`（EventType::value の配列）を受け取り、
指定された大会種別のみに集計対象を絞り込む。空配列は無絞り込み。

```php
PlayerAnalysis::summary($playerId, $types);               // サマリー + 卓内着順統計（1クエリ）
PlayerAnalysis::avgTableRank($playerId, $types);          // 卓内平均着順
PlayerAnalysis::headToHead($playerId, $types);            // 同卓対戦成績
PlayerAnalysis::scoreHistory($playerId, $types);          // スコア推移
PlayerAnalysis::rankDistribution($playerId, $types);      // 卓内着順分布
PlayerAnalysis::scoreTimeline($playerId, $types);         // スコア時系列
PlayerAnalysis::roundPerformance($playerId, $types);      // 回戦別パフォーマンス
PlayerAnalysis::eventTypeStats($playerId, $types);        // イベント種別別成績
PlayerAnalysis::bestFinalRank($playerId, $types);         // 最高最終順位
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
