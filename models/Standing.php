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
