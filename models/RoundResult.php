<?php

declare(strict_types=1);

class RoundResult
{
    /**
     * 特定ラウンドの成績を取得（選手名付き）。
     */
    public static function byRound(int $roundNumber): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT p.name, r.score, r.is_above_cutoff
            FROM round_results r
            JOIN players p ON p.id = r.player_id
            WHERE r.round_number = ?
            ORDER BY r.score DESC
        ');
        $stmt->execute([$roundNumber]);
        return $stmt->fetchAll();
    }

    /**
     * 特定選手の全ラウンド成績を取得。
     */
    public static function byPlayer(int $playerId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT round_number, score, is_above_cutoff
            FROM round_results
            WHERE player_id = ?
            ORDER BY round_number
        ');
        $stmt->execute([$playerId]);
        return $stmt->fetchAll();
    }
}
