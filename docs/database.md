# データベース設計書

最終更新: 2026-03-27

## 概要

麻雀トーナメントの戦績管理システム。
大会・選手・対局卓・各回戦の成績・総合順位・大会メタ情報を管理する。
複数大会を `tournaments` テーブルで管理し、各データは `tournament_id` で大会ごとに分離される。

| 項目 | 値 |
|---|---|
| DBMS | PostgreSQL（Neon） |
| 接続方式 | SSL必須（`sslmode=require`） |
| マイグレーション | Phinx |
| 本番 | Render -> Neon production |
| 開発 | Codespaces / Docker -> Neon dev |

## ER図

```mermaid
erDiagram
    tournaments ||--o{ tables_info : "大会の卓情報"
    tournaments ||--o{ round_results : "大会の回戦成績"
    tournaments ||--o{ standings : "大会の総合順位"
    tournaments ||--o{ tournament_meta : "大会のメタ情報"
    players ||--o{ round_results : "各回戦の成績"
    players ||--o{ standings : "総合順位"
    players ||--o{ table_players : "卓への配置"
    tables_info ||--o{ table_players : "卓のメンバー"

    tournaments {
        int id PK
        varchar name "大会名"
        varchar status "状態"
        timestamp created_at "作成日時"
    }

    characters {
        int id PK
        varchar name UK "キャラクター名"
        varchar icon_filename "アイコンファイル名"
    }

    players {
        int id PK
        varchar name UK "選手名（正式名称）"
        varchar nickname "呼称"
        int character_id FK "characters.id"
    }

    characters ||--o{ players : "使用キャラ"

    tables_info {
        int id PK
        int tournament_id FK "tournaments.id"
        int round_number "回戦番号"
        varchar table_name "卓名"
        varchar schedule "対局日程"
        boolean done "完了フラグ"
    }

    table_players {
        int id PK
        int table_id FK "tables_info.id"
        int player_id FK "players.id"
        int seat_order "席順 (1-4)"
    }

    round_results {
        int id PK
        int tournament_id FK "tournaments.id"
        int player_id FK "players.id"
        int round_number "回戦番号"
        numeric score "スコア"
        boolean is_above_cutoff "通過フラグ"
    }

    standings {
        int tournament_id PK,FK "tournaments.id"
        int player_id PK,FK "players.id"
        int rank "順位"
        numeric total "累計スコア"
        boolean pending "確定待ち"
        int eliminated_round "敗退回戦"
    }

    tournament_meta {
        int tournament_id PK,FK "tournaments.id"
        varchar key PK "メタキー"
        varchar value "メタ値"
    }
```

### リレーション一覧

| 親テーブル | 子テーブル | 外部キー | カーディナリティ | 削除時 |
|---|---|---|---|---|
| tournaments | tables_info | tournament_id | 1:N | CASCADE |
| tournaments | round_results | tournament_id | 1:N | CASCADE |
| tournaments | standings | tournament_id | 1:N | CASCADE |
| tournaments | tournament_meta | tournament_id | 1:N | CASCADE |
| players | round_results | player_id | 1:N | CASCADE |
| players | standings | player_id | 1:N | CASCADE |
| players | table_players | player_id | 1:N | CASCADE |
| tables_info | table_players | table_id | 1:N | CASCADE |
| characters | players | character_id | 1:N | SET NULL |

## ビジネスルール

### 大会管理

大会は `tournaments` テーブルで管理され、`status` カラムで進行状態を制御する。

- `in_progress` -- 開催中の大会
- その他の値（例: `completed`, `cancelled`）は運営が設定する

全てのデータ（卓情報、回戦成績、総合順位、メタ情報）は `tournament_id` で大会に紐づく。
大会を削除すると、関連する全データがCASCADE削除される。

### 大会進行ルール

大会は複数回戦の敗退制トーナメントで構成される。各回戦ごとにボーダー（カットオフ）が設定され、下回った選手は敗退する。

```
1回戦: 20名 -> 5卓 (4名 x 5)  -> 16名通過 / 4名敗退
2回戦: 16名 -> 4卓 (4名 x 4)  -> 12名通過 / 4名敗退
3回戦: 12名 -> 3卓 (4名 x 3)  ->  4名通過 / 8名敗退
決 勝:  4名 -> 1卓 (4名 x 1)  ->  優勝決定
```

- 各卓は4名で構成される（麻雀のルール上の制約）
- 通過/敗退の判定は `round_results.is_above_cutoff` に記録される
- カットオフの閾値自体はDBに保存されない（運営が判断し、結果のみ記録）

### スコアと順位

- スコアは小数第1位まで記録（例: 82.5, -72.8）
- 正の値・負の値どちらもあり得る（麻雀の得点計算に準拠）
- `standings.total` = 全参加回戦の `round_results.score` の合計
- `standings.rank` は `total` の降順で決定（1位 = 最高スコア）
- 敗退した選手は参加した回戦分のスコアのみが累計に反映される

### 敗退と残留

- `standings.eliminated_round = 0` -- 残留中（決勝進出者・優勝者を含む）
- `standings.eliminated_round = N` -- N回戦で敗退
- 敗退した選手はそれ以降の回戦に出場しない（`table_players` に追加されない）
- 敗退しても `players` テーブルからは削除されない（履歴保持）

### 対局卓の管理

- `tables_info.done = false` -- 未対局、`true` -- 対局完了
- `table_players.seat_order` (1-4) は着席位置を表す
- 卓割り・席順は運営が決定し、結果のみDBに記録する

## データライフサイクル

大会の作成から終了までのデータの流れを示す。

```
[大会作成]
  |
  +-- tournaments に大会を登録 (status='in_progress')
  |
[大会開始]
  |
  +-- players に全選手を登録
  +-- tournament_meta に大会情報を設定 (tournament_id を指定)
  |
[各回戦の実施]
  |
  +-- 1. tables_info に卓情報を作成 (tournament_id を指定, done=false)
  +-- 2. table_players に各卓の選手と席順を登録
  +-- 3. 対局実施
  +-- 4. round_results にスコアと通過判定を記録 (tournament_id を指定)
  +-- 5. tables_info.done を true に更新
  +-- 6. standings を再計算 (tournament_id を指定, total, rank, eliminated_round)
  +-- 7. tournament_meta の current_round, remaining_players を更新
  |
  +-- 次回戦へ（通過者のみ）
  |
[大会終了]
  |
  +-- tournaments.status を更新
```

**不変条件:**
- 同一大会の同一選手の同一回戦にスコアは1件のみ（複合ユニーク制約で保証）
- standings は大会ごとに選手1人につき1レコード（複合PK = tournament_id, player_id）
- tournament_meta は大会ごとにキー1件のみ（複合PK = tournament_id, key）
- 回戦を遡って結果を変更する仕組みはない（追記型）

## テーブル定義

### tournaments - 大会マスタ

大会を管理するマスタテーブル。全ての戦績データの起点となる。大会が削除されると関連する tables_info, round_results, standings, tournament_meta がCASCADE削除される。

| カラム | 型 | NULL | デフォルト | 説明 |
|---|---|---|---|---|
| id | SERIAL (INTEGER) | NO | auto_increment | PK |
| name | VARCHAR(100) | NO | - | 大会名 |
| status | VARCHAR(20) | NO | 'in_progress' | 大会の状態 |
| created_at | TIMESTAMP | YES | CURRENT_TIMESTAMP | 作成日時 |

| インデックス | 種別 | カラム | 設計意図 |
|---|---|---|---|
| tournaments_pkey | PRIMARY KEY | id | 行の一意識別 |

---

### players - 選手マスタ

全テーブルの起点となるマスタテーブル。選手が削除されると関連する全データがCASCADEで削除される。

| カラム | 型 | NULL | デフォルト | 説明 |
|---|---|---|---|---|
| id | INTEGER | NO | auto_increment | PK |
| name | VARCHAR(50) | NO | - | 選手名（アプリ内正式名称） |
| nickname | VARCHAR(50) | YES | NULL | 呼称（サイト表示用の通称） |
| character_id | INTEGER | YES | NULL | FK -> characters.id |

| インデックス | 種別 | カラム | 設計意図 |
|---|---|---|---|
| players_pkey | PRIMARY KEY | id | 行の一意識別 |
| players_name_key | UNIQUE | name | 同名選手の登録を防止 |
| players_character_id_fkey | FOREIGN KEY | character_id → characters(id) | SET NULL |

---

### characters - キャラクターマスタ

雀魂のキャラクター情報を管理するマスタテーブル。選手が使用しているキャラクターのアイコン表示に使用。

| カラム | 型 | NULL | デフォルト | 説明 |
|---|---|---|---|---|
| id | INTEGER | NO | auto_increment | PK |
| name | VARCHAR(50) | NO | - | キャラクター名 |
| icon_filename | VARCHAR(100) | YES | NULL | アイコンファイル名（public/img/chara_deformed/配下） |

| インデックス | 種別 | カラム | 設計意図 |
|---|---|---|---|
| characters_pkey | PRIMARY KEY | id | 行の一意識別 |
| characters_name_key | UNIQUE | name | 同名キャラクターの登録を防止 |

---

### tables_info - 対局卓情報

各回戦における卓の構成情報。1回戦に複数卓が存在する。`tournament_id` で大会に紐づく。

| カラム | 型 | NULL | デフォルト | 説明 |
|---|---|---|---|---|
| id | INTEGER | NO | auto_increment | PK |
| tournament_id | INTEGER | NO | - | FK -> tournaments.id |
| round_number | INTEGER | NO | - | 回戦番号（1, 2, 3, ...） |
| table_name | VARCHAR(20) | NO | - | 卓名（例: 1卓, 2卓） |
| schedule | VARCHAR(50) | YES | '' | 対局日程 |
| done | BOOLEAN | YES | false | 対局完了フラグ |

| インデックス | 種別 | カラム | 設計意図 |
|---|---|---|---|
| tables_info_pkey | PRIMARY KEY | id | 行の一意識別 |

| 外部キー | 参照先 | 削除時 |
|---|---|---|
| tables_info_tournament_id_fkey | tournaments(id) | CASCADE |

---

### table_players - 卓別選手配置

tables_info と players の中間テーブル。各卓にどの選手がどの席に座るかを管理する。

| カラム | 型 | NULL | デフォルト | 説明 |
|---|---|---|---|---|
| id | INTEGER | NO | auto_increment | PK |
| table_id | INTEGER | NO | - | FK -> tables_info.id |
| player_id | INTEGER | NO | - | FK -> players.id |
| seat_order | INTEGER | NO | 0 | 席順（1-4） |

| インデックス | 種別 | カラム | 設計意図 |
|---|---|---|---|
| table_players_pkey | PRIMARY KEY | id | 行の一意識別 |

| 外部キー | 参照先 | 削除時 |
|---|---|---|
| table_players_table_id_fkey | tables_info(id) | CASCADE |
| table_players_player_id_fkey | players(id) | CASCADE |

---

### round_results - 回戦別成績

各回戦における選手ごとのスコアと通過判定を記録する。大会の中核データ。`tournament_id` で大会に紐づく。

| カラム | 型 | NULL | デフォルト | 説明 |
|---|---|---|---|---|
| id | INTEGER | NO | auto_increment | PK |
| tournament_id | INTEGER | NO | - | FK -> tournaments.id |
| player_id | INTEGER | NO | - | FK -> players.id |
| round_number | INTEGER | NO | - | 回戦番号 |
| score | NUMERIC | NO | - | スコア（小数第1位まで） |
| is_above_cutoff | BOOLEAN | NO | true | ボーダー通過フラグ |

| インデックス | 種別 | カラム | 設計意図 |
|---|---|---|---|
| round_results_pkey | PRIMARY KEY | id | 行の一意識別 |
| round_results_tournament_id_player_id_round_number_key | UNIQUE | (tournament_id, player_id, round_number) | 同一大会の同一選手の同一回戦に重複登録を防止 |

| 外部キー | 参照先 | 削除時 |
|---|---|---|
| round_results_tournament_id_fkey | tournaments(id) | CASCADE |
| round_results_player_id_fkey | players(id) | CASCADE |

---

### standings - 総合順位

大会全体を通した選手の最終順位と累計スコア。大会ごとに1選手につき1レコード。

| カラム | 型 | NULL | デフォルト | 説明 |
|---|---|---|---|---|
| tournament_id | INTEGER | NO | - | PK / FK -> tournaments.id |
| player_id | INTEGER | NO | - | PK / FK -> players.id |
| rank | INTEGER | NO | - | 順位（1 = 最上位） |
| total | NUMERIC | NO | 0 | 累計スコア |
| pending | BOOLEAN | YES | false | 成績確定待ちフラグ |
| eliminated_round | INTEGER | YES | 0 | 敗退回戦（0 = 残留中） |

| インデックス | 種別 | カラム | 設計意図 |
|---|---|---|---|
| standings_pkey | PRIMARY KEY | (tournament_id, player_id) | 大会ごとに1選手1レコードを保証 |

| 外部キー | 参照先 | 削除時 |
|---|---|---|
| standings_tournament_id_fkey | tournaments(id) | CASCADE |
| standings_player_id_fkey | players(id) | CASCADE |

---

### tournament_meta - 大会メタ情報

大会に関する設定値・統計値をKey-Value形式で保持する。`tournament_id` で大会に紐づく。

| カラム | 型 | NULL | デフォルト | 説明 |
|---|---|---|---|---|
| tournament_id | INTEGER | NO | - | PK / FK -> tournaments.id |
| key | VARCHAR(50) | NO | - | PK / メタキー |
| value | VARCHAR(200) | NO | - | メタ値 |

| インデックス | 種別 | カラム | 設計意図 |
|---|---|---|---|
| tournament_meta_pkey | PRIMARY KEY | (tournament_id, key) | 大会ごとにキーの一意性を保証 |

| 外部キー | 参照先 | 削除時 |
|---|---|---|
| tournament_meta_tournament_id_fkey | tournaments(id) | CASCADE |

**現在登録されているキー（大会ごと）:**

| key | value | 説明 |
|---|---|---|
| total_players | 20 | 総参加者数 |
| current_round | 3 | 現在の回戦 |
| remaining_players | 4 | 残留選手数 |

※ `record_score` / `record_player` は廃止。`TournamentRecords::all()` で round_results から自動算出する。

## 命名規約

| 対象 | ルール | 例 |
|---|---|---|
| テーブル名 | snake_case / 複数形 | `players`, `round_results`, `tournaments` |
| カラム名 | snake_case | `player_id`, `round_number`, `tournament_id` |
| 外部キー | `{テーブル名}_{カラム名}_fkey` | `round_results_player_id_fkey`, `tables_info_tournament_id_fkey` |
| ユニーク制約 | `{テーブル名}_{カラム名}_key` | `players_name_key` |
| 複合ユニーク | `{テーブル名}_{カラム1}_{カラム2}_key` | `round_results_tournament_id_player_id_round_number_key` |
| PK | `{テーブル名}_pkey` | `standings_pkey`, `tournaments_pkey` |
| ブール型 | `is_` / `done` / `pending` 等の状態語 | `is_above_cutoff`, `done` |
| FK参照カラム | `{参照先テーブル単数形}_id` | `player_id`, `table_id`, `tournament_id` |

## データ量（現時点）

| テーブル | 件数 | 増加パターン |
|---|---|---|
| tournaments | 1 | 大会ごとに1件 |
| characters | 19 | 雀魂キャラクターマスタ |
| players | 20 | 大会ごとに固定 |
| tables_info | 12 | 回戦数 x 卓数（最大 ~15/大会） |
| table_players | 48 | tables_info x 4（1卓4名） |
| round_results | 48 | 回戦ごとの参加者数の合計 |
| standings | 20 | players と同数（大会ごと） |
| tournament_meta | 5 | 少数のKVペア（大会ごと） |

## モデル層マッピング

各テーブルへのアクセスは `models/` 配下のクラスに集約されている。
ビュー（`public/*.php`）からは直接SQLを発行せず、必ずモデル経由でデータを取得する。

| モデル | テーブル | 主なメソッド |
|---|---|---|
| Tournament | tournaments | `all()`, `find($id)`, `byPlayer($playerId)` |
| Character | characters | `all()`, `find($id)` |
| Player | players + characters | `all()`, `find($id)`, `count()` |
| Standing | standings + players | `all($tournamentId)`, `finalists($tournamentId)`, `findByPlayer($tournamentId, $playerId)` |
| RoundResult | round_results + players | `byRound($tournamentId, $roundNumber)`, `byPlayer($tournamentId, $playerId)` |
| TableInfo | tables_info + table_players + players | `byRound($tournamentId, $roundNumber)`, `byPlayerAndTournament($tournamentId, $playerId)` |
| TournamentMeta | tournament_meta | `all($tournamentId)`, `get($tournamentId, $key, $default)` |
| PlayerAnalysis | round_results + table_players + players (集計) | `summary($playerId)`, `avgTableRank($playerId)`, `headToHead($playerId)`, `scoreHistory($playerId)` |

## 接続設定

```
# .env（DATABASE_URL形式）
DATABASE_URL=postgresql://user:password@host/dbname?sslmode=require

# または個別指定
PGHOST=...
PGPORT=5432
PGDATABASE=jantama
PGUSER=...
PGPASSWORD=...
PGSSLMODE=require
```

- アプリケーション: `config/database.php` の `getDbConnection()` で接続
- マイグレーション: `phinx.php` で同じ環境変数を参照
- Neonスリープ対策としてリトライ機構あり（最大3回、指数バックオフ: 1s -> 2s -> 4s）

## マイグレーション履歴

| バージョン | 名称 | 内容 |
|---|---|---|
| 20260317000000 | CreateInitialSchema | 全6テーブルの初期スキーマ作成 |
| 20260327000000 | AddTournamentsSupport | tournaments テーブル新規作成、既存テーブルに tournament_id カラム追加、外部キー・複合PK・複合ユニーク制約の変更 |
| 20260327100000 | AddFinalsData | 決勝（4回戦）の卓情報・成績データ投入、スタンディングを決勝結果反映済みに更新 |
