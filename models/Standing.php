<?php

declare(strict_types=1);

class Standing
{
    /**
     * 総合順位を取得（選手名付き）。
     */
    public static function all(int $tournamentId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT s.rank, p.name, s.total, s.pending, s.eliminated_round
            FROM standings s
            JOIN players p ON p.id = s.player_id
            WHERE s.tournament_id = ?
            ORDER BY s.rank
        ');
        $stmt->execute([$tournamentId]);
        return $stmt->fetchAll();
    }

    /**
     * 決勝進出者を取得（ラウンドスコア付き）。
     */
    public static function finalists(int $tournamentId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT p.name, s.total,
                   string_agg(
                       CASE WHEN r.score >= 0 THEN '+' ELSE '' END || r.score::text,
                       ' → ' ORDER BY r.round_number
                   ) AS trend
            FROM standings s
            JOIN players p ON p.id = s.player_id
            JOIN round_results r ON r.player_id = s.player_id AND r.tournament_id = s.tournament_id
            WHERE s.tournament_id = ? AND s.eliminated_round = 0
            GROUP BY p.name, s.total
            ORDER BY s.total DESC
        ");
        $stmt->execute([$tournamentId]);
        return $stmt->fetchAll();
    }

    /**
     * 特定選手の順位を取得。
     */
    public static function findByPlayer(int $tournamentId, int $playerId): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT s.rank, p.name, s.total, s.pending, s.eliminated_round
            FROM standings s
            JOIN players p ON p.id = s.player_id
            WHERE s.tournament_id = ? AND s.player_id = ?
        ');
        $stmt->execute([$tournamentId, $playerId]);
        return $stmt->fetch() ?: null;
    }
}
