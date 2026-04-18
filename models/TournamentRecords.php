<?php

declare(strict_types=1);

/**
 * 大会内のデータから算出される記録集。
 * tournament_view の「トーナメントレコード」セクション向け。
 */
class TournamentRecords
{
    /**
     * 大会の全記録をまとめて取得する。各要素は null（該当データなし）の可能性あり。
     *
     * @return array{highest_score: ?array, most_tops: ?array}
     */
    public static function all(int $tournamentId): array
    {
        return [
            'highest_score' => self::highestRoundScore($tournamentId),
            'most_tops' => self::mostTopFinishes($tournamentId),
        ];
    }

    /**
     * 大会内で1回戦あたりの最高得点を記録した選手。
     */
    public static function highestRoundScore(int $tournamentId): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT rr.score,
                   rr.round_number,
                   p.id AS player_id,
                   COALESCE(p.nickname, p.name) AS player_name,
                   c.icon_filename AS character_icon
            FROM round_results rr
            JOIN players p ON p.id = rr.player_id
            LEFT JOIN characters c ON c.id = p.character_id
            WHERE rr.tournament_id = ?
            ORDER BY rr.score DESC, rr.round_number ASC
            LIMIT 1
        ');
        $stmt->execute([$tournamentId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * 大会内で卓内1位を最も多く取った選手を全員取得する（同率複数対応）。
     * 多ゲーム卓は SUM(score) で統合し、RANK() 1位をカウント。
     *
     * @return array{top_count: int, winners: array<array{player_id:int, player_name:string, character_icon:?string}>}|null
     */
    public static function mostTopFinishes(int $tournamentId): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            WITH table_scores AS (
                SELECT tp.table_id, tp.player_id, SUM(rr.score) AS table_score
                FROM table_players tp
                JOIN tables_info ti ON ti.id = tp.table_id
                JOIN round_results rr ON rr.player_id = tp.player_id
                     AND rr.round_number = ti.round_number
                     AND rr.tournament_id = ti.tournament_id
                WHERE ti.tournament_id = ?
                GROUP BY tp.table_id, tp.player_id
            ),
            table_ranks AS (
                SELECT table_id, player_id,
                       RANK() OVER (PARTITION BY table_id ORDER BY table_score DESC) AS tbl_rank
                FROM table_scores
            ),
            player_tops AS (
                SELECT tr.player_id, COUNT(*) AS top_count
                FROM table_ranks tr
                WHERE tr.tbl_rank = 1
                GROUP BY tr.player_id
            )
            SELECT pt.player_id,
                   COALESCE(p.nickname, p.name) AS player_name,
                   pt.top_count,
                   c.icon_filename AS character_icon
            FROM player_tops pt
            JOIN players p ON p.id = pt.player_id
            LEFT JOIN characters c ON c.id = p.character_id
            WHERE pt.top_count = (SELECT MAX(top_count) FROM player_tops)
            ORDER BY p.name ASC
        ');
        $stmt->execute([$tournamentId]);
        $rows = $stmt->fetchAll();
        if (!$rows) {
            return null;
        }
        return [
            'top_count' => (int) $rows[0]['top_count'],
            'winners' => $rows,
        ];
    }

}
