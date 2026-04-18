<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * round_results.is_above_cutoff カラムを削除する。
 * 全行 true 固定で機能していなかった死カラム。
 * 通過/敗退判定は standings.eliminated_round に統一済み。
 */
final class RemoveRoundResultsIsAboveCutoff extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('ALTER TABLE round_results DROP COLUMN IF EXISTS is_above_cutoff');
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE round_results ADD COLUMN IF NOT EXISTS is_above_cutoff boolean NOT NULL DEFAULT true');
    }
}
