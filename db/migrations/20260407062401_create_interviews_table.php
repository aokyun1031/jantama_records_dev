<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateInterviewsTable extends AbstractMigration
{
    public function up(): void
    {
        $this->table('interviews')
            ->addColumn('tournament_id', 'integer', ['null' => false])
            ->addColumn('sort_order', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('question', 'text', ['null' => false])
            ->addColumn('answer', 'text', ['null' => false, 'default' => ''])
            ->addForeignKey('tournament_id', 'tournaments', 'id', ['delete' => 'CASCADE'])
            ->addIndex(['tournament_id', 'sort_order'])
            ->create();
    }

    public function down(): void
    {
        $this->table('interviews')->drop()->save();
    }
}
