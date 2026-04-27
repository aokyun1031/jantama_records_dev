<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * discord_scheduled_events テーブルを追加する。
 *
 * 当サイトのエンティティ（卓 等）と Discord サーバーの Guild Scheduled Event を紐付ける。
 * (entity_type, entity_id) で一意 → 同一エンティティに対して常に同じイベントを PATCH/DELETE できる。
 *
 * entity_type: 現状 'table' のみ。将来 'tournament' を足す余地を残して汎用キーにしてある。
 * entity_id: tables_info.id 等の参照先 ID（FK は entity_type が複数になる前提なので張らない）
 */
final class AddDiscordScheduledEvents extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS discord_scheduled_events (
                entity_type VARCHAR(16) NOT NULL,
                entity_id INTEGER NOT NULL,
                discord_event_id VARCHAR(32) NOT NULL,
                guild_id VARCHAR(32) NOT NULL,
                last_synced_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (entity_type, entity_id)
            );
        SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS discord_scheduled_events');
    }
}
