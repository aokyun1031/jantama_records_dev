<?php

declare(strict_types=1);

class RoundResult
{
    /**
     * 特定ラウンドの成績を取得（選手名付き、ゲーム別スコアの合計）。
     */
    public static function byRound(int $tournamentId, int $roundNumber): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT p.name, p.nickname, SUM(r.score) AS score,
                   c.icon_filename AS character_icon
            FROM round_results r
            JOIN players p ON p.id = r.player_id
            LEFT JOIN characters c ON c.id = p.character_id
            WHERE r.tournament_id = ? AND r.round_number = ?
            GROUP BY p.id, p.name, p.nickname, c.icon_filename
            ORDER BY SUM(r.score) DESC
        ');
        $stmt->execute([$tournamentId, $roundNumber]);
        return $stmt->fetchAll();
    }

    /**
     * スコアを一括保存する（UPSERT）。
     *
     * @param array<array{player_id: int, score: float}> $scores
     */
    public static function saveScores(int $tournamentId, int $roundNumber, array $scores, int $gameNumber = 1): void
    {
        $pdo = getDbConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('
                INSERT INTO round_results (tournament_id, player_id, round_number, game_number, score)
                VALUES (?, ?, ?, ?, ?)
                ON CONFLICT (tournament_id, player_id, round_number, game_number)
                DO UPDATE SET score = EXCLUDED.score
            ');
            foreach ($scores as $s) {
                $stmt->execute([
                    $tournamentId,
                    $s['player_id'],
                    $roundNumber,
                    $gameNumber,
                    $s['score'],
                ]);
            }
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
