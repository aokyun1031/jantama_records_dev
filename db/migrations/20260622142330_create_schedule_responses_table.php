<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * schedule_responses テーブルを追加する。
 *
 * 選手が schedule_candidates の中から「参加可能」と回答した候補を記録する。
 * 不参加は単に行が存在しないだけで表現する（参加可能のみ記録）。
 */
final class CreateScheduleResponsesTable extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS schedule_responses (
                schedule_candidate_id INTEGER NOT NULL REFERENCES schedule_candidates(id) ON DELETE CASCADE,
                player_id INTEGER NOT NULL REFERENCES players(id) ON DELETE CASCADE,
                responded_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (schedule_candidate_id, player_id)
            );
        SQL);
        $this->execute(
            'CREATE INDEX idx_schedule_responses_player ON schedule_responses (player_id)'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS schedule_responses');
    }
}
