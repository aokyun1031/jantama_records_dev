<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddFinalsData extends AbstractMigration
{
    public function up(): void
    {
        // 決勝卓の追加（未登録の場合のみ）
        $this->execute("
            INSERT INTO tables_info (tournament_id, round_number, table_name, schedule, done)
            SELECT 1, 4, '決勝卓', '', true
            WHERE NOT EXISTS (SELECT 1 FROM tables_info WHERE tournament_id = 1 AND round_number = 4)
        ");

        // 決勝卓メンバーの追加（未登録の場合のみ）
        $this->execute("
            INSERT INTO table_players (table_id, player_id, seat_order)
            SELECT ti.id, p.id, sub.seat
            FROM tables_info ti
            CROSS JOIN (
                VALUES ('ホロホロ', 1), ('みか', 2), ('がちゃ', 3), ('するが', 4)
            ) AS sub(name, seat)
            JOIN players p ON p.name = sub.name
            WHERE ti.tournament_id = 1 AND ti.round_number = 4
              AND NOT EXISTS (SELECT 1 FROM table_players tp WHERE tp.table_id = ti.id)
        ");

        // 決勝成績の追加（未登録の場合のみ）
        $this->execute("
            INSERT INTO round_results (tournament_id, player_id, round_number, score, is_above_cutoff)
            SELECT 1, p.id, 4, sub.score, sub.above
            FROM (
                VALUES ('ホロホロ', 68.3, true), ('するが', 64.5, true), ('がちゃ', -58.5, false), ('みか', -74.3, false)
            ) AS sub(name, score, above)
            JOIN players p ON p.name = sub.name
            WHERE NOT EXISTS (SELECT 1 FROM round_results rr WHERE rr.tournament_id = 1 AND rr.round_number = 4)
        ");

        // スタンディング更新
        $this->execute("
            UPDATE standings SET rank = sub.new_rank, total = sub.new_total, eliminated_round = sub.new_elim
            FROM (VALUES
                ('ホロホロ', 1, 171.2, 0),  ('あはん', 2, 130.4, 3),
                ('するが', 3, 63.3, 0),     ('ぎり', 4, 56.5, 3),
                ('がちゃ', 5, 55.8, 0),     ('シーマ', 6, 55.5, 3),
                ('みーた', 7, 47.8, 3),     ('みか', 8, 43.7, 0),
                ('こいぬ', 9, 40.3, 1),     ('あき', 10, -0.7, 3),
                ('ぶる', 11, -22.7, 2),     ('そぼろ', 12, -27.4, 2),
                ('ぱーらめんこ', 13, -63.7, 1), ('りあ', 14, -68.0, 3),
                ('けちゃこ', 15, -71.2, 2), ('あーす', 16, -72.8, 1),
                ('がう', 17, -73.0, 3),     ('なぎ', 18, -76.0, 1),
                ('梅', 19, -80.4, 2),       ('イラチ', 20, -107.8, 3)
            ) AS sub(pname, new_rank, new_total, new_elim)
            JOIN players p ON p.name = sub.pname
            WHERE standings.tournament_id = 1 AND standings.player_id = p.id
        ");

        // tournament_meta更新
        $this->execute("UPDATE tournament_meta SET value = '4' WHERE tournament_id = 1 AND key = 'current_round'");
        $this->execute("UPDATE tournament_meta SET value = '1' WHERE tournament_id = 1 AND key = 'remaining_players'");
    }

    public function down(): void
    {
        $this->execute("DELETE FROM round_results WHERE tournament_id = 1 AND round_number = 4");
        $this->execute("DELETE FROM table_players WHERE table_id IN (SELECT id FROM tables_info WHERE tournament_id = 1 AND round_number = 4)");
        $this->execute("DELETE FROM tables_info WHERE tournament_id = 1 AND round_number = 4");
        $this->execute("UPDATE tournament_meta SET value = '3' WHERE tournament_id = 1 AND key = 'current_round'");
        $this->execute("UPDATE tournament_meta SET value = '4' WHERE tournament_id = 1 AND key = 'remaining_players'");

        $this->execute("
            UPDATE standings SET rank = sub.new_rank, total = sub.new_total, eliminated_round = sub.new_elim
            FROM (VALUES
                ('あはん', 1, 130.4, 3),    ('みか', 2, 118.0, 0),
                ('がちゃ', 3, 114.3, 0),    ('ホロホロ', 4, 102.9, 0),
                ('ぎり', 5, 56.5, 3),       ('シーマ', 6, 55.5, 3),
                ('みーた', 7, 47.8, 3),     ('こいぬ', 8, 40.3, 1),
                ('あき', 9, -0.7, 3),       ('するが', 10, -1.2, 0),
                ('ぶる', 11, -22.7, 2),     ('そぼろ', 12, -27.4, 2),
                ('ぱーらめんこ', 13, -63.7, 1), ('りあ', 14, -68.0, 3),
                ('けちゃこ', 15, -71.2, 2), ('あーす', 16, -72.8, 1),
                ('がう', 17, -73.0, 3),     ('なぎ', 18, -76.0, 1),
                ('梅', 19, -80.4, 2),       ('イラチ', 20, -107.8, 3)
            ) AS sub(pname, new_rank, new_total, new_elim)
            JOIN players p ON p.name = sub.pname
            WHERE standings.tournament_id = 1 AND standings.player_id = p.id
        ");
    }
}
