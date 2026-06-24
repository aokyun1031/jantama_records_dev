<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * schedule_candidates テーブルを追加する。
 *
 * 主催者がラウンドごとに登録する候補日程（例: 6/22昼, 6/22夜, 6/23昼...）。
 * 選手はこの候補から複数選択して参加可能日を回答する（schedule_responses）。
 */
final class CreateScheduleCandidatesTable extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS schedule_candidates (
                id SERIAL PRIMARY KEY,
                tournament_id INTEGER NOT NULL REFERENCES tournaments(id) ON DELETE CASCADE,
                round_number INTEGER NOT NULL,
                played_date DATE NOT NULL,
                day_of_week VARCHAR(10) NOT NULL DEFAULT '',
                played_time VARCHAR(20) NOT NULL DEFAULT '',
                sort_order INTEGER NOT NULL DEFAULT 0
            );
        SQL);
        $this->execute(
            'CREATE INDEX idx_schedule_candidates_tournament_round
             ON schedule_candidates (tournament_id, round_number)'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS schedule_candidates');
    }
}
