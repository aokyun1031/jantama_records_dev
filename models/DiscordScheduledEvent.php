<?php

declare(strict_types=1);

/**
 * 当サイトのエンティティ（卓 等）と Discord Guild Scheduled Event の紐付けを管理する。
 *
 * 当サイト側でスケジュール変更が起きるたびに、ここを参照して
 *   - 既存マッピングが無ければ create
 *   - あれば PATCH
 *   - 日時クリア / 卓削除 時は DELETE
 * を判断する。
 */
class DiscordScheduledEvent
{
    public const ENTITY_TABLE = 'table';

    /**
     * エンティティに紐づく Discord イベント情報を取得する。
     *
     * @return array{entity_type:string, entity_id:int, discord_event_id:string, guild_id:string}|null
     */
    public static function find(string $entityType, int $entityId): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'SELECT entity_type, entity_id, discord_event_id, guild_id
             FROM discord_scheduled_events
             WHERE entity_type = ? AND entity_id = ?'
        );
        $stmt->execute([$entityType, $entityId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * マッピングを記録する（UPSERT）。
     */
    public static function upsert(string $entityType, int $entityId, string $discordEventId, string $guildId): void
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO discord_scheduled_events (entity_type, entity_id, discord_event_id, guild_id, last_synced_at)
             VALUES (?, ?, ?, ?, NOW())
             ON CONFLICT (entity_type, entity_id) DO UPDATE
               SET discord_event_id = EXCLUDED.discord_event_id,
                   guild_id = EXCLUDED.guild_id,
                   last_synced_at = NOW()'
        );
        $stmt->execute([$entityType, $entityId, $discordEventId, $guildId]);
    }

}
