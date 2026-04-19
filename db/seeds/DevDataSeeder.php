<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * 開発用データ投入 seeder。
 *
 * `db/seed_data.sql`（Neon dev から `pg_dump --data-only --column-inserts` で取得した生 SQL）
 * を読み込み、全テーブルを一度 TRUNCATE CASCADE してから流し込む。
 *
 * 何度実行しても同じ状態になるため、ローカル開発で自由にテストデータを壊してリセット可能。
 * dev dump を更新したい場合: docs/local-dev-seed.md 参照。
 */
class DevDataSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return [];
    }

    public function run(): void
    {
        $sqlPath = __DIR__ . '/../seed_data.sql';
        $sql = file_get_contents($sqlPath);
        if ($sql === false) {
            throw new RuntimeException("seed データ読み込み失敗: {$sqlPath}");
        }

        // FK 依存を無視して一括リセット
        $this->execute('TRUNCATE TABLE
            interviews,
            tournament_meta,
            standings,
            round_results,
            table_paifu_urls,
            table_players,
            tables_info,
            tournaments,
            players,
            characters
            RESTART IDENTITY CASCADE
        ');

        $this->execute($sql);
    }
}
