<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddPlayedTimeToTablesInfo extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("ALTER TABLE tables_info ADD COLUMN played_time VARCHAR(5) NOT NULL DEFAULT ''");
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE tables_info DROP COLUMN played_time');
    }
}
