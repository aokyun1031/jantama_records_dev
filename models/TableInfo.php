<?php

declare(strict_types=1);

class TableInfo
{
    /**
     * 特定ラウンドの卓情報と卓メンバーを取得。
     */
    public static function byRound(int $tournamentId, int $roundNumber): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT t.id AS table_id, t.table_name, t.schedule, t.done,
                   p.name AS player_name, tp.seat_order
            FROM tables_info t
            JOIN table_players tp ON tp.table_id = t.id
            JOIN players p ON p.id = tp.player_id
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
                    'schedule'   => $row['schedule'],
                    'done'       => $row['done'],
                    'players'    => [],
                ];
            }
            $tables[$key]['players'][] = [
                'name'       => $row['player_name'],
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
            SELECT ti.round_number, ti.table_name, ti.schedule, ti.done,
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
                    'schedule'     => $row['schedule'],
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
