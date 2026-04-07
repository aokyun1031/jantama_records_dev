<?php

declare(strict_types=1);

class TableInfo
{
    /**
     * 卓をIDで取得する。
     */
    public static function find(int $id): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT t.id, t.tournament_id, t.round_number, t.table_name,
                   t.played_date, t.day_of_week, t.played_time, t.done, t.paifu_url
            FROM tables_info t
            WHERE t.id = ?
        ');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * 卓を選手一覧付きで取得する。既存スコアも含む。
     */
    public static function findWithPlayers(int $id): ?array
    {
        $table = self::find($id);
        if (!$table) {
            return null;
        }

        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT tp.player_id, p.name, p.nickname, tp.seat_order,
                   c.icon_filename AS character_icon,
                   rr.score, rr.is_above_cutoff
            FROM table_players tp
            JOIN players p ON p.id = tp.player_id
            LEFT JOIN characters c ON c.id = p.character_id
            LEFT JOIN round_results rr ON rr.player_id = tp.player_id
                  AND rr.tournament_id = ? AND rr.round_number = ?
            WHERE tp.table_id = ?
            ORDER BY tp.seat_order
        ');
        $stmt->execute([$table['tournament_id'], $table['round_number'], $id]);
        $table['players'] = $stmt->fetchAll();
        return $table;
    }

    /**
     * 大会の全卓をラウンド別に取得する。
     */
    public static function byTournament(int $tournamentId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT t.id AS table_id, t.round_number, t.table_name,
                   t.played_date, t.day_of_week, t.done, t.paifu_url,
                   p.name AS player_name, p.nickname AS player_nickname,
                   c.icon_filename AS player_icon, tp.seat_order,
                   s.eliminated_round, rr.score
            FROM tables_info t
            JOIN table_players tp ON tp.table_id = t.id
            JOIN players p ON p.id = tp.player_id
            LEFT JOIN characters c ON c.id = p.character_id
            LEFT JOIN standings s ON s.tournament_id = t.tournament_id AND s.player_id = tp.player_id
            LEFT JOIN round_results rr ON rr.player_id = tp.player_id
                  AND rr.tournament_id = t.tournament_id AND rr.round_number = t.round_number
            WHERE t.tournament_id = ?
            ORDER BY t.round_number, t.table_name, rr.score DESC NULLS LAST, tp.seat_order
        ');
        $stmt->execute([$tournamentId]);
        $rows = $stmt->fetchAll();

        $rounds = [];
        foreach ($rows as $row) {
            $rn = (int) $row['round_number'];
            $key = $row['table_name'];
            if (!isset($rounds[$rn])) {
                $rounds[$rn] = [];
            }
            if (!isset($rounds[$rn][$key])) {
                $rounds[$rn][$key] = [
                    'table_id'    => (int) $row['table_id'],
                    'table_name'  => $row['table_name'],
                    'played_date' => $row['played_date'],
                    'day_of_week' => $row['day_of_week'],
                    'done'        => $row['done'],
                    'paifu_url'   => $row['paifu_url'],
                    'players'     => [],
                ];
            }
            $rounds[$rn][$key]['players'][] = [
                'name' => $row['player_nickname'] ?? $row['player_name'],
                'icon' => $row['player_icon'],
                'eliminated_round' => (int) ($row['eliminated_round'] ?? 0),
                'score' => $row['score'],
            ];
        }

        // 連想配列を数値配列に変換
        $result = [];
        foreach ($rounds as $rn => $tables) {
            $result[$rn] = array_values($tables);
        }
        ksort($result);
        return $result;
    }

    /**
     * 指定ラウンドの卓ごとの選手IDグループを取得する。
     * 同卓回避アルゴリズム用。
     *
     * @return int[][] 例: [[1,2,3,4], [5,6,7,8]]
     */
    public static function playerGroupsByRound(int $tournamentId, int $roundNumber): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT ti.id AS table_id, tp.player_id
            FROM tables_info ti
            JOIN table_players tp ON tp.table_id = ti.id
            WHERE ti.tournament_id = ? AND ti.round_number = ?
            ORDER BY ti.id, tp.seat_order
        ');
        $stmt->execute([$tournamentId, $roundNumber]);

        $groups = [];
        foreach ($stmt->fetchAll() as $row) {
            $groups[$row['table_id']][] = (int) $row['player_id'];
        }
        return array_values($groups);
    }

    /**
     * 複数の卓を一括作成する。
     *
     * @param array<array{name: string, player_ids: int[]}> $tables
     */
    public static function createBatch(int $tournamentId, int $roundNumber, array $tables): void
    {
        $pdo = getDbConnection();
        $pdo->beginTransaction();
        try {
            $tableStmt = $pdo->prepare(
                "INSERT INTO tables_info (tournament_id, round_number, table_name, day_of_week)
                 VALUES (?, ?, ?, '')"
            );
            $playerStmt = $pdo->prepare(
                'INSERT INTO table_players (table_id, player_id, seat_order) VALUES (?, ?, ?)'
            );

            foreach ($tables as $t) {
                $tableStmt->execute([$tournamentId, $roundNumber, $t['name']]);
                $tableId = (int) $pdo->lastInsertId();
                foreach ($t['player_ids'] as $i => $playerId) {
                    $playerStmt->execute([$tableId, $playerId, $i + 1]);
                }
            }

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 卓を作成し、選手を割り当てる。
     */
    public static function create(int $tournamentId, int $roundNumber, string $tableName, array $playerIds): int
    {
        $pdo = getDbConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO tables_info (tournament_id, round_number, table_name, day_of_week)
                 VALUES (?, ?, ?, '')"
            );
            $stmt->execute([$tournamentId, $roundNumber, $tableName]);
            $tableId = (int) $pdo->lastInsertId();

            $playerStmt = $pdo->prepare(
                'INSERT INTO table_players (table_id, player_id, seat_order) VALUES (?, ?, ?)'
            );
            foreach ($playerIds as $i => $playerId) {
                $playerStmt->execute([$tableId, $playerId, $i + 1]);
            }

            $pdo->commit();
            return $tableId;
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 対局日を更新する。
     */
    public static function updateSchedule(int $id, ?string $playedDate, string $dayOfWeek, string $playedTime): void
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('UPDATE tables_info SET played_date = ?, day_of_week = ?, played_time = ? WHERE id = ?');
        $stmt->execute([$playedDate, $dayOfWeek, $playedTime, $id]);
    }

    /**
     * 牌譜URLを更新する。
     */
    public static function updatePaifuUrl(int $id, string $url): void
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('UPDATE tables_info SET paifu_url = ? WHERE id = ?');
        $stmt->execute([$url, $id]);
    }

    /**
     * 卓を完了にする。
     */
    public static function markDone(int $id): void
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('UPDATE tables_info SET done = true WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * 卓を削除する（table_players も CASCADE で削除）。
     */
    public static function delete(int $id): void
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('DELETE FROM tables_info WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * 特定ラウンドの卓情報と卓メンバーを取得。
     */
    public static function byRound(int $tournamentId, int $roundNumber): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT t.id AS table_id, t.table_name, t.played_date, t.day_of_week, t.done,
                   COALESCE(p.nickname, p.name) AS player_name,
                   c.icon_filename AS player_icon, tp.seat_order
            FROM tables_info t
            JOIN table_players tp ON tp.table_id = t.id
            JOIN players p ON p.id = tp.player_id
            LEFT JOIN characters c ON c.id = p.character_id
            WHERE t.tournament_id = ? AND t.round_number = ?
            ORDER BY t.table_name, tp.seat_order
        ');
        $stmt->execute([$tournamentId, $roundNumber]);
        $rows = $stmt->fetchAll();

        // 卓ごとにグループ化
        $tables = [];
        foreach ($rows as $row) {
            $key = $row['table_name'];
            if (!isset($tables[$key])) {
                $tables[$key] = [
                    'table_name' => $row['table_name'],
                    'played_date'  => $row['played_date'],
                    'day_of_week'  => $row['day_of_week'],
                    'done'       => $row['done'],
                    'players'    => [],
                ];
            }
            $tables[$key]['players'][] = [
                'name'       => $row['player_name'],
                'icon'       => $row['player_icon'] ?? '',
                'seat_order' => $row['seat_order'],
            ];
        }
        return array_values($tables);
    }

    /**
     * 特定大会で特定選手が参加した卓情報をラウンドごとに取得。
     * 同卓メンバーのスコア・通過判定を含む。
     */
    public static function byPlayerAndTournament(int $tournamentId, int $playerId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT ti.round_number, ti.table_name, ti.played_date, ti.day_of_week, ti.done,
                   p.id AS member_id, p.name AS member_name, tp2.seat_order,
                   rr.score, rr.is_above_cutoff
            FROM table_players tp
            JOIN tables_info ti ON ti.id = tp.table_id
            JOIN table_players tp2 ON tp2.table_id = tp.table_id
            JOIN players p ON p.id = tp2.player_id
            LEFT JOIN round_results rr ON rr.player_id = tp2.player_id
                  AND rr.round_number = ti.round_number
                  AND rr.tournament_id = ti.tournament_id
            WHERE tp.player_id = ? AND ti.tournament_id = ?
            ORDER BY ti.round_number, rr.score DESC NULLS LAST
        ');
        $stmt->execute([$playerId, $tournamentId]);
        $rows = $stmt->fetchAll();

        $rounds = [];
        foreach ($rows as $row) {
            $rn = $row['round_number'];
            if (!isset($rounds[$rn])) {
                $rounds[$rn] = [
                    'round_number' => $rn,
                    'table_name'   => $row['table_name'],
                    'played_date'    => $row['played_date'],
                    'day_of_week'    => $row['day_of_week'],
                    'done'         => $row['done'],
                    'members'      => [],
                ];
            }
            $rounds[$rn]['members'][] = [
                'id'              => (int) $row['member_id'],
                'name'            => $row['member_name'],
                'seat_order'      => $row['seat_order'],
                'score'           => $row['score'],
                'is_above_cutoff' => $row['is_above_cutoff'],
            ];
        }
        return array_values($rounds);
    }
}
