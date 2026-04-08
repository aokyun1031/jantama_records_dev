<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddMultiGameSupport extends AbstractMigration
{
    public function up(): void
    {
        // 1. round_results に game_number カラムを追加
        $this->execute("ALTER TABLE round_results ADD COLUMN game_number INTEGER NOT NULL DEFAULT 1");

        // 2. 既存のUNIQUE制約を削除し、game_numberを含む新制約を追加
        $this->execute("ALTER TABLE round_results DROP CONSTRAINT round_results_tournament_player_round_key");
        $this->execute("ALTER TABLE round_results ADD CONSTRAINT round_results_unique_game UNIQUE (tournament_id, player_id, round_number, game_number)");

        // 3. table_paifu_urls テーブルを作成
        $this->execute("
            CREATE TABLE table_paifu_urls (
                id SERIAL PRIMARY KEY,
                table_id INTEGER NOT NULL REFERENCES tables_info(id) ON DELETE CASCADE,
                game_number INTEGER NOT NULL DEFAULT 1,
                url TEXT NOT NULL DEFAULT '',
                UNIQUE (table_id, game_number)
            )
        ");

        // 4. 既存の paifu_url データを移行
        $this->execute("
            INSERT INTO table_paifu_urls (table_id, game_number, url)
            SELECT id, 1, paifu_url FROM tables_info WHERE paifu_url IS NOT NULL AND paifu_url != ''
        ");

        // 5. tables_info から paifu_url カラムを削除
        $this->execute("ALTER TABLE tables_info DROP COLUMN paifu_url");
    }

    public function down(): void
    {
        // tables_info に paifu_url カラムを復元
        $this->execute("ALTER TABLE tables_info ADD COLUMN paifu_url TEXT");

        // データを戻す
        $this->execute("
            UPDATE tables_info SET paifu_url = tpu.url
            FROM table_paifu_urls tpu
            WHERE tpu.table_id = tables_info.id AND tpu.game_number = 1
        ");

        // table_paifu_urls テーブルを削除
        $this->execute("DROP TABLE table_paifu_urls");

        // round_results の制約を元に戻す
        $this->execute("ALTER TABLE round_results DROP CONSTRAINT round_results_unique_game");
        $this->execute("ALTER TABLE round_results ADD CONSTRAINT round_results_tournament_player_round_key UNIQUE (tournament_id, player_id, round_number)");
        $this->execute("ALTER TABLE round_results DROP COLUMN game_number");
    }
}
