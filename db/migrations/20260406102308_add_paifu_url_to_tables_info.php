<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddPaifuUrlToTablesInfo extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('ALTER TABLE tables_info ADD COLUMN paifu_url TEXT');
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE tables_info DROP COLUMN paifu_url');
    }
}
