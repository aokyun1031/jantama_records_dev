-- ========================================
-- init.sql
-- 麻雀トーナメント「最強位戦」スキーマ定義
-- ========================================

-- 選手マスタ
CREATE TABLE IF NOT EXISTS players (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

-- 各ラウンドの卓情報
CREATE TABLE IF NOT EXISTS tables_info (
    id SERIAL PRIMARY KEY,
    round_number INT NOT NULL,
    table_name VARCHAR(20) NOT NULL,
    schedule VARCHAR(50) DEFAULT '',
    done BOOLEAN DEFAULT false
);

-- 卓ごとの選手割り当て
CREATE TABLE IF NOT EXISTS table_players (
    id SERIAL PRIMARY KEY,
    table_id INT NOT NULL REFERENCES tables_info(id) ON DELETE CASCADE,
    player_id INT NOT NULL REFERENCES players(id) ON DELETE CASCADE,
    seat_order INT NOT NULL DEFAULT 0
);

-- ラウンドごとの成績
CREATE TABLE IF NOT EXISTS round_results (
    id SERIAL PRIMARY KEY,
    player_id INT NOT NULL REFERENCES players(id) ON DELETE CASCADE,
    round_number INT NOT NULL,
    score DECIMAL(10,1) NOT NULL,
    is_above_cutoff BOOLEAN NOT NULL DEFAULT true,
    UNIQUE(player_id, round_number)
);

-- 総合順位（集計ビューでも可だが、pending/elimの管理用にテーブル化）
CREATE TABLE IF NOT EXISTS standings (
    player_id INT PRIMARY KEY REFERENCES players(id) ON DELETE CASCADE,
    rank INT NOT NULL,
    total DECIMAL(10,1) NOT NULL DEFAULT 0,
    pending BOOLEAN DEFAULT false,
    eliminated_round INT DEFAULT 0
);

-- 大会メタ情報（最高得点などのレコード）
CREATE TABLE IF NOT EXISTS tournament_meta (
    key VARCHAR(50) PRIMARY KEY,
    value VARCHAR(200) NOT NULL
);
