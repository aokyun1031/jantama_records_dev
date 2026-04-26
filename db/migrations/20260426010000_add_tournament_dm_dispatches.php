<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * tournament_dm_dispatches テーブルを追加する。
 *
 * 大会作成時に Discord DM で参加表明URLを送った履歴を記録するテーブル。
 * 「未配信選手のみ再送」「個別再送」を実現するため、(tournament_id, player_id) で一意。
 *
 * status:
 *   - sent          : DM 送信成功
 *   - failed        : Discord API エラー（DM 拒否設定 / 相互サーバー外 等）
 *   - no_discord_id : 選手が discord_user_id 未登録（DM 不可）
 */
final class AddTournamentDmDispatches extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS tournament_dm_dispatches (
                tournament_id INTEGER NOT NULL REFERENCES tournaments(id) ON DELETE CASCADE,
                player_id INTEGER NOT NULL REFERENCES players(id) ON DELETE CASCADE,
                sent_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                status VARCHAR(16) NOT NULL,
                PRIMARY KEY (tournament_id, player_id)
            );
        SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS tournament_dm_dispatches');
    }
}
