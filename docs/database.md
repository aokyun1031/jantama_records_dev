# データベース設計書

最終更新: 2026-03-27

## 概要

麻雀トーナメント「最強位戦」の戦績管理システム。
選手・対局卓・各回戦の成績・総合順位・大会メタ情報を管理する。

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
    players ||--o{ round_results : "各回戦の成績"
    players ||--o| standings : "総合順位"
    players ||--o{ table_players : "卓への配置"
    tables_info ||--o{ table_players : "卓のメンバー"

    players {
        int id PK
        varchar name UK "選手名"
    }

    tables_info {
        int id PK
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
        int player_id FK "players.id"
        int round_number "回戦番号"
        numeric score "スコア"
        boolean is_above_cutoff "通過フラグ"
    }

    standings {
        int player_id PK,FK "players.id"
        int rank "順位"
        numeric total "累計スコア"
        boolean pending "確定待ち"
        int eliminated_round "敗退回戦"
    }

    tournament_meta {
        varchar key PK "メタキー"
        varchar value "メタ値"
    }
```

### リレーション一覧

| 親テーブル | 子テーブル | 外部キー | カーディナリティ | 削除時 |
|---|---|---|---|---|
| players | round_results | player_id | 1:N | CASCADE |
| players | standings | player_id | 1:1 | CASCADE |
| players | table_players | player_id | 1:N | CASCADE |
| tables_info | table_players | table_id | 1:N | CASCADE |

`tournament_meta` は他テーブルとのリレーションを持たない独立したKey-Valueストア。

## ビジネスルール

### 大会進行ルール

大会は複数回戦の敗退制トーナメントで構成される。各回戦ごとにボーダー（カットオフ）が設定され、下回った選手は敗退する。

```
1回戦: 20名 → 5卓 (4名 x 5)  → 16名通過 / 4名敗退
2回戦: 16名 → 4卓 (4名 x 4)  → 12名通過 / 4名敗退
3回戦: 12名 → 3卓 (4名 x 3)  →  4名通過 / 8名敗退
決 勝:  4名 → 1卓 (4名 x 1)  →  優勝決定
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

- `standings.eliminated_round = 0` → 残留中（決勝進出者・優勝者を含む）
- `standings.eliminated_round = N` → N回戦で敗退
- 敗退した選手はそれ以降の回戦に出場しない（`table_players` に追加されない）
- 敗退しても `players` テーブルからは削除されない（履歴保持）

### 対局卓の管理

- `tables_info.done = false` → 未対局、`true` → 対局完了
- `table_players.seat_order` (1-4) は着席位置を表す
- 卓割り・席順は運営が決定し、結果のみDBに記録する

## データライフサイクル

1回戦の開始から終了までのデータの流れを示す。

```
[大会開始]
  │
  ├─ players に全選手を登録
  ├─ tournament_meta に大会情報を設定
  │
[各回戦の実施]
  │
  ├─ 1. tables_info に卓情報を作成 (done=false)
  ├─ 2. table_players に各卓の選手と席順を登録
  ├─ 3. 対局実施
  ├─ 4. round_results にスコアと通過判定を記録
  ├─ 5. tables_info.done を true に更新
  ├─ 6. standings を再計算（total, rank, eliminated_round）
  ├─ 7. tournament_meta の current_round, remaining_players を更新
  │
  └─ 次回戦へ（通過者のみ）
```

**不変条件:**
- 同一選手の同一回戦にスコアは1件のみ（複合ユニーク制約で保証）
- standings は選手1人につき1レコード（PK = player_id）
- 回戦を遡って結果を変更する仕組みはない（追記型）

## テーブル定義

### players - 選手マスタ

全テーブルの起点となるマスタテーブル。選手が削除されると関連する全データがCASCADEで削除される。

| カラム | 型 | NULL | デフォルト | 説明 |
|---|---|---|---|---|
| id | INTEGER | NO | auto_increment | PK |
| name | VARCHAR(50) | NO | - | 選手名 |

| インデックス | 種別 | カラム | 設計意図 |
|---|---|---|---|
| players_pkey | PRIMARY KEY | id | 行の一意識別 |
| players_name_key | UNIQUE | name | 同名選手の登録を防止 |

---

### tables_info - 対局卓情報

各回戦における卓の構成情報。1回戦に複数卓が存在する。

| カラム | 型 | NULL | デフォルト | 説明 |
|---|---|---|---|---|
| id | INTEGER | NO | auto_increment | PK |
| round_number | INTEGER | NO | - | 回戦番号（1, 2, 3, ...） |
| table_name | VARCHAR(20) | NO | - | 卓名（例: 1卓, 2卓） |
| schedule | VARCHAR(50) | YES | '' | 対局日程 |
| done | BOOLEAN | YES | false | 対局完了フラグ |

| インデックス | 種別 | カラム | 設計意図 |
|---|---|---|---|
| tables_info_pkey | PRIMARY KEY | id | 行の一意識別 |

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

各回戦における選手ごとのスコアと通過判定を記録する。大会の中核データ。

| カラム | 型 | NULL | デフォルト | 説明 |
|---|---|---|---|---|
| id | INTEGER | NO | auto_increment | PK |
| player_id | INTEGER | NO | - | FK -> players.id |
| round_number | INTEGER | NO | - | 回戦番号 |
| score | NUMERIC | NO | - | スコア（小数第1位まで） |
| is_above_cutoff | BOOLEAN | NO | true | ボーダー通過フラグ |

| インデックス | 種別 | カラム | 設計意図 |
|---|---|---|---|
| round_results_pkey | PRIMARY KEY | id | 行の一意識別 |
| round_results_player_id_round_number_key | UNIQUE | (player_id, round_number) | 同一選手の同一回戦に重複登録を防止 |

| 外部キー | 参照先 | 削除時 |
|---|---|---|
| round_results_player_id_fkey | players(id) | CASCADE |

---

### standings - 総合順位

大会全体を通した選手の最終順位と累計スコア。1選手につき1レコード。

| カラム | 型 | NULL | デフォルト | 説明 |
|---|---|---|---|---|
| player_id | INTEGER | NO | - | PK / FK -> players.id |
| rank | INTEGER | NO | - | 順位（1 = 最上位） |
| total | NUMERIC | NO | 0 | 累計スコア |
| pending | BOOLEAN | YES | false | 成績確定待ちフラグ |
| eliminated_round | INTEGER | YES | 0 | 敗退回戦（0 = 残留中） |

| インデックス | 種別 | カラム | 設計意図 |
|---|---|---|---|
| standings_pkey | PRIMARY KEY | player_id | 1選手1レコードを保証 |

| 外部キー | 参照先 | 削除時 |
|---|---|---|
| standings_player_id_fkey | players(id) | CASCADE |

---

### tournament_meta - 大会メタ情報

大会全体に関する設定値・統計値をKey-Value形式で保持する。

| カラム | 型 | NULL | デフォルト | 説明 |
|---|---|---|---|---|
| key | VARCHAR(50) | NO | - | PK / メタキー |
| value | VARCHAR(200) | NO | - | メタ値 |

| インデックス | 種別 | カラム | 設計意図 |
|---|---|---|---|
| tournament_meta_pkey | PRIMARY KEY | key | キーの一意性を保証 |

**現在登録されているキー:**

| key | value | 説明 |
|---|---|---|
| record_score | 65400 | 最高スコア記録 |
| record_player | するが | 最高スコア保持者 |
| total_players | 20 | 総参加者数 |
| current_round | 3 | 現在の回戦 |
| remaining_players | 4 | 残留選手数 |

## 命名規約

| 対象 | ルール | 例 |
|---|---|---|
| テーブル名 | snake_case / 複数形 | `players`, `round_results` |
| カラム名 | snake_case | `player_id`, `round_number` |
| 外部キー | `{テーブル名}_{カラム名}_fkey` | `round_results_player_id_fkey` |
| ユニーク制約 | `{テーブル名}_{カラム名}_key` | `players_name_key` |
| 複合ユニーク | `{テーブル名}_{カラム1}_{カラム2}_key` | `round_results_player_id_round_number_key` |
| PK | `{テーブル名}_pkey` | `standings_pkey` |
| ブール型 | `is_` / `done` / `pending` 等の状態語 | `is_above_cutoff`, `done` |
| FK参照カラム | `{参照先テーブル単数形}_id` | `player_id`, `table_id` |

## データ量（現時点）

| テーブル | 件数 | 増加パターン |
|---|---|---|
| players | 20 | 大会ごとに固定 |
| tables_info | 12 | 回戦数 x 卓数（最大 ~15/大会） |
| table_players | 48 | tables_info x 4（1卓4名） |
| round_results | 48 | 回戦ごとの参加者数の合計 |
| standings | 20 | players と同数 |
| tournament_meta | 5 | 少数のKVペア |

## モデル層マッピング

各テーブルへのアクセスは `models/` 配下のクラスに集約されている。
ビュー（`public/*.php`）からは直接SQLを発行せず、必ずモデル経由でデータを取得する。

| モデル | テーブル | 主なメソッド |
|---|---|---|
| Player | players | `all()`, `find($id)`, `count()` |
| Standing | standings + players | `all()`, `findByPlayer($playerId)` |
| RoundResult | round_results + players | `byRound($roundNumber)`, `byPlayer($playerId)` |
| TableInfo | tables_info + table_players + players | `byRound($roundNumber)` |
| TournamentMeta | tournament_meta | `all()`, `get($key, $default)` |

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
