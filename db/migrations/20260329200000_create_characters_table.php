<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateCharactersTable extends AbstractMigration
{
    public function up(): void
    {
        // キャラクターマスタテーブル
        $this->execute("
            CREATE TABLE characters (
                id SERIAL PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                icon_filename VARCHAR(100) DEFAULT NULL
            )
        ");

        // players に character_id を追加
        $this->execute("ALTER TABLE players ADD COLUMN character_id INTEGER DEFAULT NULL");
        $this->execute("
            ALTER TABLE players
            ADD CONSTRAINT players_character_id_fkey
            FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE SET NULL
        ");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE players DROP CONSTRAINT IF EXISTS players_character_id_fkey");
        $this->execute("ALTER TABLE players DROP COLUMN IF EXISTS character_id");
        $this->execute("DROP TABLE IF EXISTS characters");
    }
}
