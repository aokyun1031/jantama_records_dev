<?php

declare(strict_types=1);

class TableInfo
{
    /**
     * 特定ラウンドの卓情報と卓メンバーを取得。
     */
    public static function byRound(int $roundNumber): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT t.id AS table_id, t.table_name, t.schedule, t.done,
                   p.name AS player_name, tp.seat_order
            FROM tables_info t
            JOIN table_players tp ON tp.table_id = t.id
            JOIN players p ON p.id = tp.player_id
            WHERE t.round_number = ?
            ORDER BY t.table_name, tp.seat_order
        ');
        $stmt->execute([$roundNumber]);
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
}
