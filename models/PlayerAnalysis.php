<?php

declare(strict_types=1);

class PlayerAnalysis
{
    /**
     * 通算成績サマリー（全大会合算）。
     */
    public static function summary(int $playerId): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            WITH final_rounds AS (
                SELECT tournament_id, MAX(round_number) AS final_round
                FROM round_results
                GROUP BY tournament_id
            )
            SELECT COUNT(*) AS total_rounds,
                   COUNT(DISTINCT rr.tournament_id) AS total_tournaments,
                   AVG(rr.score) AS avg_score,
                   MAX(rr.score) AS best_score,
                   MIN(rr.score) AS worst_score,
                   SUM(CASE WHEN rr.round_number < fr.final_round AND rr.is_above_cutoff THEN 1 ELSE 0 END) AS qualifying_passes,
                   SUM(CASE WHEN rr.round_number < fr.final_round THEN 1 ELSE 0 END) AS qualifying_rounds
            FROM round_results rr
            JOIN final_rounds fr ON fr.tournament_id = rr.tournament_id
            WHERE rr.player_id = ?
        ');
        $stmt->execute([$playerId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * 卓内平均着順（全大会合算）。
     */
    public static function avgTableRank(int $playerId): ?string
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT AVG(table_rank)
            FROM (
                SELECT rr.player_id,
                       RANK() OVER (PARTITION BY tp.table_id ORDER BY rr.score DESC) AS table_rank
                FROM table_players tp
                JOIN tables_info ti ON ti.id = tp.table_id
                JOIN round_results rr ON rr.player_id = tp.player_id
                     AND rr.round_number = ti.round_number
                     AND rr.tournament_id = ti.tournament_id
            ) sub
            WHERE player_id = ?
        ');
        $stmt->execute([$playerId]);
        return $stmt->fetchColumn() ?: null;
    }

    /**
     * 同卓対戦成績（全大会合算）。
     */
    public static function headToHead(int $playerId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            WITH table_ranks AS (
                SELECT tp.table_id, tp.player_id,
                       RANK() OVER (PARTITION BY tp.table_id ORDER BY rr.score DESC) AS tbl_rank
                FROM table_players tp
                JOIN tables_info ti ON ti.id = tp.table_id
                JOIN round_results rr ON rr.player_id = tp.player_id
                     AND rr.round_number = ti.round_number
                     AND rr.tournament_id = ti.tournament_id
            )
            SELECT p.id AS opponent_id,
                   regexp_replace(p.name, '^[0-9]+', '') AS opponent_name,
                   COALESCE(h.games, 0) AS games,
                   COALESCE(h.wins, 0) AS wins,
                   COALESCE(h.losses, 0) AS losses,
                   h.avg_my_score,
                   h.avg_opp_score,
                   h.avg_my_rank,
                   h.avg_opp_rank
            FROM players p
            LEFT JOIN (
                SELECT tp2.player_id AS opp_id,
                       COUNT(*) AS games,
                       SUM(CASE WHEN rr1.score > rr2.score THEN 1 ELSE 0 END) AS wins,
                       SUM(CASE WHEN rr1.score < rr2.score THEN 1 ELSE 0 END) AS losses,
                       AVG(rr1.score) AS avg_my_score,
                       AVG(rr2.score) AS avg_opp_score,
                       AVG(tr_me.tbl_rank) AS avg_my_rank,
                       AVG(tr.tbl_rank) AS avg_opp_rank
                FROM table_players tp1
                JOIN table_players tp2 ON tp2.table_id = tp1.table_id AND tp2.player_id != tp1.player_id
                JOIN tables_info ti ON ti.id = tp1.table_id
                JOIN round_results rr1 ON rr1.player_id = tp1.player_id
                     AND rr1.round_number = ti.round_number AND rr1.tournament_id = ti.tournament_id
                JOIN round_results rr2 ON rr2.player_id = tp2.player_id
                     AND rr2.round_number = ti.round_number AND rr2.tournament_id = ti.tournament_id
                JOIN table_ranks tr ON tr.table_id = tp2.table_id AND tr.player_id = tp2.player_id
                JOIN table_ranks tr_me ON tr_me.table_id = tp1.table_id AND tr_me.player_id = tp1.player_id
                WHERE tp1.player_id = ?
                GROUP BY tp2.player_id
            ) h ON h.opp_id = p.id
            WHERE p.id != ?
            ORDER BY games DESC, opponent_name
        ");
        $stmt->execute([$playerId, $playerId]);
        return $stmt->fetchAll();
    }

    /**
     * スコア推移（全大会、新しい大会��）。
     */
    public static function scoreHistory(int $playerId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT t.name AS tournament_name, rr.round_number, rr.score, rr.is_above_cutoff,
                   tbl.played_date, tbl.day_of_week, tbl.played_time
            FROM round_results rr
            JOIN tournaments t ON t.id = rr.tournament_id
            LEFT JOIN LATERAL (
                SELECT ti.played_date, ti.day_of_week, ti.played_time
                FROM table_players tp
                JOIN tables_info ti ON ti.id = tp.table_id
                WHERE tp.player_id = rr.player_id
                  AND ti.tournament_id = rr.tournament_id
                  AND ti.round_number = rr.round_number
                LIMIT 1
            ) tbl ON true
            WHERE rr.player_id = ?
            ORDER BY t.created_at DESC, rr.round_number
        ');
        $stmt->execute([$playerId]);
        return $stmt->fetchAll();
    }
}
