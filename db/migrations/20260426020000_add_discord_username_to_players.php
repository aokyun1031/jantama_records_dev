<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * players.discord_username カラムを追加する。
 * Discord OAuth2 連携時に取得したユーザー名（@表示用）をキャッシュ保存する。
 * 表示のたびに Discord API を叩かず DB から読むため。
 */
final class AddDiscordUsernameToPlayers extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('ALTER TABLE players ADD COLUMN IF NOT EXISTS discord_username VARCHAR(64) NULL');
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE players DROP COLUMN IF EXISTS discord_username');
    }
}
