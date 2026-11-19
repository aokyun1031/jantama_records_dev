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

    /** ローカルの DB とみなす接続先ホスト（docker-compose の db サービスとループバック） */
    private const LOCAL_HOSTS = ['db', 'localhost', '127.0.0.1', '::1'];

    public function run(): void
    {
        // 全テーブルを TRUNCATE するため、ローカル以外の DB（Neon dev / 本番）では実行しない。
        // .env の DATABASE_URL を切り替えたまま seed:run して全データを消す事故を防ぐ。
        // 意図的にリモートの開発 DB を巻き戻す場合のみ SEED_ALLOW_REMOTE=1 を付けて実行する。
        $host = (string) $this->getAdapter()->getOption('host');
        $appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV');
        $allowRemote = ($_ENV['SEED_ALLOW_REMOTE'] ?? getenv('SEED_ALLOW_REMOTE')) === '1';
        if ($appEnv === 'production') {
            throw new RuntimeException('DevDataSeeder は本番環境（APP_ENV=production）では実行できません。');
        }
        if (!in_array($host, self::LOCAL_HOSTS, true) && !$allowRemote) {
            throw new RuntimeException(
                "DevDataSeeder はローカル DB 専用です（接続先ホスト: {$host}）。"
                . ' リモートの開発 DB を意図的にリセットする場合のみ SEED_ALLOW_REMOTE=1 を指定してください。'
            );
        }

        $sqlPath = __DIR__ . '/../seed_data.sql';
        $sql = file_get_contents($sqlPath);
        if ($sql === false) {
            throw new RuntimeException("seed データ読み込み失敗: {$sqlPath}");
        }

        // FK 依存を無視して一括リセット
        $this->execute('TRUNCATE TABLE
            interviews,
            tournament_meta,
            tournament_dm_dispatches,
            discord_scheduled_events,
            schedule_responses,
            schedule_candidates,
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
