<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddForeignKeyIndexes extends AbstractMigration
{
    public function change(): void
    {
        $this->table('tables_info')
            ->addIndex(['tournament_id'], ['name' => 'idx_tables_info_tournament_id'])
            ->update();

        $this->table('table_players')
            ->addIndex(['table_id'], ['name' => 'idx_table_players_table_id'])
            ->update();
    }
}
