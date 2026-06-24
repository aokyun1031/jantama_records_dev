<?php

declare(strict_types=1);

class ScheduleResponse
{
    /**
     * 候補ID群に対する回答済み選手IDを取得する。
     *
     * @param int[] $candidateIds
     * @return array<int, int[]> candidate_id => [player_id, ...]
     */
    public static function byCandidateIds(array $candidateIds): array
    {
        if (empty($candidateIds)) {
            return [];
        }
        $pdo = getDbConnection();
        $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT schedule_candidate_id, player_id
             FROM schedule_responses
             WHERE schedule_candidate_id IN ($placeholders)"
        );
        $stmt->execute($candidateIds);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['schedule_candidate_id']][] = (int) $row['player_id'];
        }
        return $map;
    }

    /**
     * 指定選手のラウンド内の回答済み候補ID一覧を取得する。
     *
     * @return int[]
     */
    public static function byPlayer(int $tournamentId, int $roundNumber, int $playerId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT sr.schedule_candidate_id
            FROM schedule_responses sr
            JOIN schedule_candidates sc ON sc.id = sr.schedule_candidate_id
            WHERE sc.tournament_id = ? AND sc.round_number = ? AND sr.player_id = ?
        ');
        $stmt->execute([$tournamentId, $roundNumber, $playerId]);
        return array_map(fn($row) => (int) $row['schedule_candidate_id'], $stmt->fetchAll());
    }

    /**
     * 指定選手のラウンド内の回答を一括置き換えする。
     *
     * @param int[] $candidateIds
     */
    public static function replaceForPlayer(int $tournamentId, int $roundNumber, int $playerId, array $candidateIds): void
    {
        $pdo = getDbConnection();
        $pdo->beginTransaction();
        try {
            $deleteStmt = $pdo->prepare('
                DELETE FROM schedule_responses
                WHERE player_id = ?
                  AND schedule_candidate_id IN (
                      SELECT id FROM schedule_candidates WHERE tournament_id = ? AND round_number = ?
                  )
            ');
            $deleteStmt->execute([$playerId, $tournamentId, $roundNumber]);

            $insertStmt = $pdo->prepare(
                'INSERT INTO schedule_responses (schedule_candidate_id, player_id) VALUES (?, ?)'
            );
            foreach ($candidateIds as $candidateId) {
                $insertStmt->execute([$candidateId, $playerId]);
            }

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * ラウンド内で1つ以上回答済みの選手ID一覧を取得する。
     *
     * @return int[]
     */
    public static function respondedPlayerIds(int $tournamentId, int $roundNumber): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT DISTINCT sr.player_id
            FROM schedule_responses sr
            JOIN schedule_candidates sc ON sc.id = sr.schedule_candidate_id
            WHERE sc.tournament_id = ? AND sc.round_number = ?
        ');
        $stmt->execute([$tournamentId, $roundNumber]);
        return array_map(fn($row) => (int) $row['player_id'], $stmt->fetchAll());
    }
}
