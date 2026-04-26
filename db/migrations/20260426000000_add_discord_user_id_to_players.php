<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * players.discord_user_id カラムを追加する。
 * Discord 連携で大会作成時の DM 送信先 / register-player コマンド経由の自己登録に使う。
 * Discord User ID は雪片ID（17-19桁の数値文字列）。NULL 許容（未登録選手用）。
 */
final class AddDiscordUserIdToPlayers extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('ALTER TABLE players ADD COLUMN IF NOT EXISTS discord_user_id VARCHAR(32) NULL');
        $this->execute('CREATE UNIQUE INDEX IF NOT EXISTS idx_players_discord_user_id ON players (discord_user_id) WHERE discord_user_id IS NOT NULL');
    }

    public function down(): void
    {
        $this->execute('DROP INDEX IF EXISTS idx_players_discord_user_id');
        $this->execute('ALTER TABLE players DROP COLUMN IF EXISTS discord_user_id');
    }
}
