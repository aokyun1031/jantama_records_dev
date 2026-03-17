<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateInitialSchema extends AbstractMigration
{
    public function up(): void
    {
        // 選手マスタ
        $this->table('players')
            ->addColumn('name', 'string', ['limit' => 50, 'null' => false])
            ->addIndex(['name'], ['unique' => true])
            ->create();

        // 各ラウンドの卓情報
        $this->table('tables_info')
            ->addColumn('round_number', 'integer', ['null' => false])
            ->addColumn('table_name', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('schedule', 'string', ['limit' => 50, 'default' => ''])
            ->addColumn('done', 'boolean', ['default' => false])
            ->create();

        // 卓ごとの選手割り当て
        $this->table('table_players')
            ->addColumn('table_id', 'integer', ['null' => false])
            ->addColumn('player_id', 'integer', ['null' => false])
            ->addColumn('seat_order', 'integer', ['null' => false, 'default' => 0])
            ->addForeignKey('table_id', 'tables_info', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('player_id', 'players', 'id', ['delete' => 'CASCADE'])
            ->create();

        // ラウンドごとの成績
        $this->table('round_results')
            ->addColumn('player_id', 'integer', ['null' => false])
            ->addColumn('round_number', 'integer', ['null' => false])
            ->addColumn('score', 'decimal', ['precision' => 10, 'scale' => 1, 'null' => false])
            ->addColumn('is_above_cutoff', 'boolean', ['null' => false, 'default' => true])
            ->addIndex(['player_id', 'round_number'], ['unique' => true])
            ->addForeignKey('player_id', 'players', 'id', ['delete' => 'CASCADE'])
            ->create();

        // 総合順位
        $this->table('standings', ['id' => false, 'primary_key' => ['player_id']])
            ->addColumn('player_id', 'integer', ['null' => false])
            ->addColumn('rank', 'integer', ['null' => false])
            ->addColumn('total', 'decimal', ['precision' => 10, 'scale' => 1, 'null' => false, 'default' => '0'])
            ->addColumn('pending', 'boolean', ['default' => false])
            ->addColumn('eliminated_round', 'integer', ['default' => 0])
            ->addForeignKey('player_id', 'players', 'id', ['delete' => 'CASCADE'])
            ->create();

        // 大会メタ情報
        $this->table('tournament_meta', ['id' => false, 'primary_key' => ['key']])
            ->addColumn('key', 'string', ['limit' => 50, 'null' => false])
            ->addColumn('value', 'string', ['limit' => 200, 'null' => false])
            ->create();
    }

    public function down(): void
    {
        $this->table('tournament_meta')->drop()->save();
        $this->table('standings')->drop()->save();
        $this->table('round_results')->drop()->save();
        $this->table('table_players')->drop()->save();
        $this->table('tables_info')->drop()->save();
        $this->table('players')->drop()->save();
    }
}
