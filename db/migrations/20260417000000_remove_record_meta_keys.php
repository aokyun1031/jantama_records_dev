<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * tournament_meta から record_score / record_player を削除する。
 * これらは手動入力メタだったが、round_results から自動算出する
 * TournamentRecords モデルに置き換えたため不要。
 */
final class RemoveRecordMetaKeys extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("DELETE FROM tournament_meta WHERE key IN ('record_score', 'record_player')");
    }

    public function down(): void
    {
        // 手動入力メタは復元不能（元データが残っていない）ため no-op
    }
}
