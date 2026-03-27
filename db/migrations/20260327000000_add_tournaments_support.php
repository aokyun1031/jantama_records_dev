<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddTournamentsSupport extends AbstractMigration
{
    public function up(): void
    {
        // 大会テーブル作成
        $this->table('tournaments')
            ->addColumn('name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('status', 'string', ['limit' => 20, 'null' => false, 'default' => 'in_progress'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();

        // 既存データ用のデフォルト大会を作成
        $this->execute("INSERT INTO tournaments (id, name, status) VALUES (1, '第1回 最強位戦', 'completed')");
        $this->execute("SELECT setval('tournaments_id_seq', (SELECT MAX(id) FROM tournaments))");

        // tables_info に tournament_id を追加
        $this->execute('ALTER TABLE tables_info ADD COLUMN tournament_id INTEGER');
        $this->execute('UPDATE tables_info SET tournament_id = 1');
        $this->execute('ALTER TABLE tables_info ALTER COLUMN tournament_id SET NOT NULL');
        $this->execute('ALTER TABLE tables_info ADD CONSTRAINT tables_info_tournament_id_fkey FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE');

        // round_results に tournament_id を追加
        $this->execute('ALTER TABLE round_results ADD COLUMN tournament_id INTEGER');
        $this->execute('UPDATE round_results SET tournament_id = 1');
        $this->execute('ALTER TABLE round_results ALTER COLUMN tournament_id SET NOT NULL');
        $this->execute('ALTER TABLE round_results ADD CONSTRAINT round_results_tournament_id_fkey FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE');
        // ユニーク制約を大会スコープに変更
        $this->execute('ALTER TABLE round_results DROP CONSTRAINT round_results_player_id_round_number_key');
        $this->execute('ALTER TABLE round_results ADD CONSTRAINT round_results_tournament_player_round_key UNIQUE (tournament_id, player_id, round_number)');

        // standings に tournament_id を追加（PK変更）
        $this->execute('ALTER TABLE standings DROP CONSTRAINT standings_pkey');
        $this->execute('ALTER TABLE standings ADD COLUMN tournament_id INTEGER');
        $this->execute('UPDATE standings SET tournament_id = 1');
        $this->execute('ALTER TABLE standings ALTER COLUMN tournament_id SET NOT NULL');
        $this->execute('ALTER TABLE standings ADD PRIMARY KEY (tournament_id, player_id)');
        $this->execute('ALTER TABLE standings ADD CONSTRAINT standings_tournament_id_fkey FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE');

        // tournament_meta に tournament_id を追加（PK変更）
        $this->execute('ALTER TABLE tournament_meta DROP CONSTRAINT tournament_meta_pkey');
        $this->execute('ALTER TABLE tournament_meta ADD COLUMN tournament_id INTEGER');
        $this->execute('UPDATE tournament_meta SET tournament_id = 1');
        $this->execute('ALTER TABLE tournament_meta ALTER COLUMN tournament_id SET NOT NULL');
        $this->execute('ALTER TABLE tournament_meta ADD PRIMARY KEY (tournament_id, key)');
        $this->execute('ALTER TABLE tournament_meta ADD CONSTRAINT tournament_meta_tournament_id_fkey FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE');
    }

    public function down(): void
    {
        // tournament_meta を復元
        $this->execute('ALTER TABLE tournament_meta DROP CONSTRAINT tournament_meta_tournament_id_fkey');
        $this->execute('ALTER TABLE tournament_meta DROP CONSTRAINT tournament_meta_pkey');
        $this->execute('ALTER TABLE tournament_meta DROP COLUMN tournament_id');
        $this->execute("ALTER TABLE tournament_meta ADD PRIMARY KEY (key)");

        // standings を復元
        $this->execute('ALTER TABLE standings DROP CONSTRAINT standings_tournament_id_fkey');
        $this->execute('ALTER TABLE standings DROP CONSTRAINT standings_pkey');
        $this->execute('ALTER TABLE standings DROP COLUMN tournament_id');
        $this->execute('ALTER TABLE standings ADD PRIMARY KEY (player_id)');

        // round_results を復元
        $this->execute('ALTER TABLE round_results DROP CONSTRAINT round_results_tournament_player_round_key');
        $this->execute('ALTER TABLE round_results DROP CONSTRAINT round_results_tournament_id_fkey');
        $this->execute('ALTER TABLE round_results DROP COLUMN tournament_id');
        $this->execute('ALTER TABLE round_results ADD CONSTRAINT round_results_player_id_round_number_key UNIQUE (player_id, round_number)');

        // tables_info を復元
        $this->execute('ALTER TABLE tables_info DROP CONSTRAINT tables_info_tournament_id_fkey');
        $this->execute('ALTER TABLE tables_info DROP COLUMN tournament_id');

        // 大会テーブル削除
        $this->table('tournaments')->drop()->save();
    }
}
