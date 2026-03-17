-- ========================================
-- seed.sql
-- js/data.js の既存データを投入
-- ========================================

-- 選手登録
INSERT INTO players (name) VALUES
  ('あはん'),('みか'),('がちゃ'),('ホロホロ'),('ぎり'),
  ('シーマ'),('みーた'),('こいぬ'),('あき'),('するが'),
  ('ぶる'),('そぼろ'),('ぱーらめんこ'),('りあ'),('けちゃこ'),
  ('あーす'),('がう'),('なぎ'),('梅'),('イラチ')
ON CONFLICT (name) DO NOTHING;

-- ======== 1回戦 卓情報 ========
INSERT INTO tables_info (round_number, table_name, schedule, done) VALUES
  (1, '1卓', '', true),
  (1, '2卓', '金曜 21:00', true),
  (1, '3卓', '土曜 21:00', true),
  (1, '4卓', '日曜 13:00', true),
  (1, '5卓', '日曜 22:00', true);

-- 1回戦 卓メンバー
INSERT INTO table_players (table_id, player_id, seat_order)
SELECT t.id, p.id, s.seat_order
FROM (VALUES
  ('1卓', 1, 'みか', 1),('1卓', 1, 'こいぬ', 2),('1卓', 1, 'ぱーらめんこ', 3),('1卓', 1, 'けちゃこ', 4),
  ('2卓', 1, 'ぶる', 1),('2卓', 1, 'がちゃ', 2),('2卓', 1, 'なぎ', 3),('2卓', 1, 'そぼろ', 4),
  ('3卓', 1, 'ぎり', 1),('3卓', 1, 'ホロホロ', 2),('3卓', 1, 'あーす', 3),('3卓', 1, 'シーマ', 4),
  ('4卓', 1, 'みーた', 1),('4卓', 1, 'りあ', 2),('4卓', 1, 'がう', 3),('4卓', 1, 'イラチ', 4),
  ('5卓', 1, 'あき', 1),('5卓', 1, 'あはん', 2),('5卓', 1, '梅', 3),('5卓', 1, 'するが', 4)
) AS s(tname, rnd, pname, seat_order)
JOIN tables_info t ON t.table_name = s.tname AND t.round_number = s.rnd
JOIN players p ON p.name = s.pname;

-- ======== 2回戦 卓情報 ========
INSERT INTO tables_info (round_number, table_name, schedule, done) VALUES
  (2, '1卓', '', true),
  (2, '2卓', '', true),
  (2, '3卓', '', true),
  (2, '4卓', '', true);

-- 2回戦 卓メンバー
INSERT INTO table_players (table_id, player_id, seat_order)
SELECT t.id, p.id, s.seat_order
FROM (VALUES
  ('1卓', 2, 'みか', 1),('1卓', 2, 'ホロホロ', 2),('1卓', 2, '梅', 3),('1卓', 2, 'そぼろ', 4),
  ('2卓', 2, 'ぶる', 1),('2卓', 2, 'シーマ', 2),('2卓', 2, 'あき', 3),('2卓', 2, 'イラチ', 4),
  ('3卓', 2, 'ぎり', 1),('3卓', 2, 'がう', 2),('3卓', 2, 'するが', 3),('3卓', 2, 'けちゃこ', 4),
  ('4卓', 2, 'みーた', 1),('4卓', 2, 'あはん', 2),('4卓', 2, 'がちゃ', 3),('4卓', 2, 'りあ', 4)
) AS s(tname, rnd, pname, seat_order)
JOIN tables_info t ON t.table_name = s.tname AND t.round_number = s.rnd
JOIN players p ON p.name = s.pname;

-- ======== 3回戦 卓情報 ========
INSERT INTO tables_info (round_number, table_name, schedule, done) VALUES
  (3, '1卓', '', true),
  (3, '2卓', '日曜夜', true),
  (3, '3卓', '', true);

-- 3回戦 卓メンバー
INSERT INTO table_players (table_id, player_id, seat_order)
SELECT t.id, p.id, s.seat_order
FROM (VALUES
  ('1卓', 3, 'あき', 1),('1卓', 3, 'ホロホロ', 2),('1卓', 3, 'シーマ', 3),('1卓', 3, 'りあ', 4),
  ('2卓', 3, 'みか', 1),('2卓', 3, 'みーた', 2),('2卓', 3, 'がう', 3),('2卓', 3, 'するが', 4),
  ('3卓', 3, 'あはん', 1),('3卓', 3, 'イラチ', 2),('3卓', 3, 'がちゃ', 3),('3卓', 3, 'ぎり', 4)
) AS s(tname, rnd, pname, seat_order)
JOIN tables_info t ON t.table_name = s.tname AND t.round_number = s.rnd
JOIN players p ON p.name = s.pname;

-- ======== ラウンド成績 ========
-- 1回戦
INSERT INTO round_results (player_id, round_number, score, is_above_cutoff)
SELECT p.id, 1, s.score, s.above
FROM (VALUES
  ('あはん',82.5,true),('がちゃ',51.9,true),('シーマ',51.7,true),('こいぬ',40.3,true),
  ('がう',28.7,true),('みーた',26.9,true),('みか',22.3,true),('そぼろ',12.2,true),
  ('ぶる',11.9,true),('ホロホロ',10.9,true),('ぎり',10.2,true),('けちゃこ',1.1,true),
  ('りあ',-6.5,true),('あき',-16.1,true),('梅',-22.0,true),('するが',-44.4,true),
  ('イラチ',-49.1,false),('ぱーらめんこ',-63.7,false),('あーす',-72.8,false),('なぎ',-76.0,false)
) AS s(pname, score, above)
JOIN players p ON p.name = s.pname;

-- 2回戦
INSERT INTO round_results (player_id, round_number, score, is_above_cutoff)
SELECT p.id, 2, s.score, s.above
FROM (VALUES
  ('ぎり',58.5,true),('みか',54.6,true),('ホロホロ',43.4,true),('あき',37.3,true),
  ('がちゃ',24.9,true),('あはん',18.8,true),('するが',10.0,true),('がう',3.8,true),
  ('シーマ',2.3,true),('イラチ',-4.3,true),('みーた',-10.3,true),('りあ',-33.3,true),
  ('ぶる',-34.6,false),('そぼろ',-39.6,false),('梅',-58.4,false),('けちゃこ',-72.3,false)
) AS s(pname, score, above)
JOIN players p ON p.name = s.pname;

-- 3回戦
INSERT INTO round_results (player_id, round_number, score, is_above_cutoff)
SELECT p.id, 3, s.score, s.above
FROM (VALUES
  ('ホロホロ',48.6,true),('みか',41.1,true),('がちゃ',37.5,true),('するが',33.2,true),
  ('みーた',31.2,false),('あはん',29.1,false),('シーマ',1.5,false),('ぎり',-12.2,false),
  ('あき',-21.9,false),('りあ',-28.2,false),('イラチ',-54.4,false),('がう',-105.5,false)
) AS s(pname, score, above)
JOIN players p ON p.name = s.pname;

-- ======== 総合順位 ========
INSERT INTO standings (player_id, rank, total, pending, eliminated_round)
SELECT p.id, s.rank, s.total, s.pending, s.elim
FROM (VALUES
  ('あはん',1,130.4,false,3),('みか',2,118.0,false,0),('がちゃ',3,114.3,false,0),
  ('ホロホロ',4,102.9,false,0),('ぎり',5,56.5,false,3),('シーマ',6,55.5,false,3),
  ('みーた',7,47.8,false,3),('こいぬ',8,40.3,false,1),('あき',9,-0.7,false,3),
  ('するが',10,-1.2,false,0),('ぶる',11,-22.7,false,2),('そぼろ',12,-27.4,false,2),
  ('ぱーらめんこ',13,-63.7,false,1),('りあ',14,-68.0,false,3),('けちゃこ',15,-71.2,false,2),
  ('あーす',16,-72.8,false,1),('がう',17,-73.0,false,3),('なぎ',18,-76.0,false,1),
  ('梅',19,-80.4,false,2),('イラチ',20,-107.8,false,3)
) AS s(pname, rank, total, pending, elim)
JOIN players p ON p.name = s.pname;

-- ======== 大会メタ ========
INSERT INTO tournament_meta (key, value) VALUES
  ('record_score', '65400'),
  ('record_player', 'するが'),
  ('total_players', '20'),
  ('current_round', '3'),
  ('remaining_players', '4')
ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value;
