<?php

declare(strict_types=1);

class RoundResult
{
    /**
     * 特定ラウンドの成績を取得（選手名付き）。
     */
    public static function byRound(int $tournamentId, int $roundNumber): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT p.name, p.nickname, r.score, r.is_above_cutoff,
                   c.icon_filename AS character_icon
            FROM round_results r
            JOIN players p ON p.id = r.player_id
            LEFT JOIN characters c ON c.id = p.character_id
            WHERE r.tournament_id = ? AND r.round_number = ?
            ORDER BY r.score DESC
        ');
        $stmt->execute([$tournamentId, $roundNumber]);
        return $stmt->fetchAll();
    }

    /**
     * スコアを一括保存する（UPSERT）。
     *
     * @param array<array{player_id: int, score: float, is_above_cutoff: bool}> $scores
     */
    public static function saveScores(int $tournamentId, int $roundNumber, array $scores): void
    {
        $pdo = getDbConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('
                INSERT INTO round_results (tournament_id, player_id, round_number, score, is_above_cutoff)
                VALUES (?, ?, ?, ?, ?)
                ON CONFLICT (tournament_id, player_id, round_number)
                DO UPDATE SET score = EXCLUDED.score, is_above_cutoff = EXCLUDED.is_above_cutoff
            ');
            foreach ($scores as $s) {
                $stmt->execute([
                    $tournamentId,
                    $s['player_id'],
                    $roundNumber,
                    $s['score'],
                    $s['is_above_cutoff'],
                ]);
            }
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 特定選手の全ラウンド成績を取得。
     */
    public static function byPlayer(int $tournamentId, int $playerId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT round_number, score, is_above_cutoff
            FROM round_results
            WHERE tournament_id = ? AND player_id = ?
            ORDER BY round_number
        ');
        $stmt->execute([$tournamentId, $playerId]);
        return $stmt->fetchAll();
    }
}
