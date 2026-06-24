<?php

declare(strict_types=1);

class ScheduleCandidate
{
    /**
     * ラウンドの候補日程一覧を取得する（並び順）。
     */
    public static function byRound(int $tournamentId, int $roundNumber): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT id, tournament_id, round_number, played_date, day_of_week, played_time, sort_order
            FROM schedule_candidates
            WHERE tournament_id = ? AND round_number = ?
            ORDER BY sort_order, id
        ');
        $stmt->execute([$tournamentId, $roundNumber]);
        return $stmt->fetchAll();
    }

    /**
     * ラウンドの候補日程を一括で置き換える（既存候補を全削除→再作成）。
     * 既存の回答（schedule_responses）は CASCADE で削除される。
     *
     * @param array<array{played_date: string, played_time: string}> $candidates
     */
    public static function createBatch(int $tournamentId, int $roundNumber, array $candidates): void
    {
        $pdo = getDbConnection();
        $pdo->beginTransaction();
        try {
            $deleteStmt = $pdo->prepare(
                'DELETE FROM schedule_candidates WHERE tournament_id = ? AND round_number = ?'
            );
            $deleteStmt->execute([$tournamentId, $roundNumber]);

            $insertStmt = $pdo->prepare(
                'INSERT INTO schedule_candidates
                    (tournament_id, round_number, played_date, day_of_week, played_time, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($candidates as $i => $c) {
                $dayOfWeek = DayOfWeek::fromDate($c['played_date']);
                $insertStmt->execute([
                    $tournamentId, $roundNumber, $c['played_date'], $dayOfWeek, $c['played_time'], $i,
                ]);
            }

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 候補ごとの回答人数を取得する。
     *
     * @return array<int, int> candidate_id => 回答人数
     */
    public static function responseCountsByRound(int $tournamentId, int $roundNumber): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT sc.id, COUNT(sr.player_id) AS cnt
            FROM schedule_candidates sc
            LEFT JOIN schedule_responses sr ON sr.schedule_candidate_id = sc.id
            WHERE sc.tournament_id = ? AND sc.round_number = ?
            GROUP BY sc.id
        ');
        $stmt->execute([$tournamentId, $roundNumber]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['id']] = (int) $row['cnt'];
        }
        return $map;
    }
}
