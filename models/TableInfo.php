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
                   t.played_date, t.day_of_week, t.played_time, t.done
            FROM tables_info t
            WHERE t.id = ?
        ');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * 卓を選手一覧付きで取得する。ゲーム別スコアも含む。
     */
    public static function findWithPlayers(int $id): ?array
    {
        $table = self::find($id);
        if (!$table) {
            return null;
        }

        $pdo = getDbConnection();
        // 選手一覧（合計スコア付き）
        $stmt = $pdo->prepare('
            SELECT tp.player_id, p.name, p.nickname, p.discord_user_id, tp.seat_order,
                   c.icon_filename AS character_icon,
                   SUM(rr.score) AS score
            FROM table_players tp
            JOIN players p ON p.id = tp.player_id
            LEFT JOIN characters c ON c.id = p.character_id
            LEFT JOIN round_results rr ON rr.player_id = tp.player_id
                  AND rr.tournament_id = ? AND rr.round_number = ?
            WHERE tp.table_id = ?
            GROUP BY tp.player_id, p.name, p.nickname, p.discord_user_id, tp.seat_order, c.icon_filename
            ORDER BY tp.seat_order
        ');
        $stmt->execute([$table['tournament_id'], $table['round_number'], $id]);
        $table['players'] = $stmt->fetchAll();

        // ゲーム別スコア
        $gameStmt = $pdo->prepare('
            SELECT rr.game_number, rr.player_id, rr.score
            FROM round_results rr
            JOIN table_players tp ON tp.player_id = rr.player_id AND tp.table_id = ?
            WHERE rr.tournament_id = ? AND rr.round_number = ?
            ORDER BY rr.game_number, rr.player_id
        ');
        $gameStmt->execute([$id, $table['tournament_id'], $table['round_number']]);
        $gameScores = [];
        foreach ($gameStmt->fetchAll() as $row) {
            $gn = (int) $row['game_number'];
            $gameScores[$gn][(int) $row['player_id']] = (float) $row['score'];
        }
        $table['game_scores'] = $gameScores;

        return $table;
    }

    /**
     * 大会の全卓をラウンド別に取得する。スコアはラウンド合計。
     */
    public static function byTournament(int $tournamentId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT t.id AS table_id, t.round_number, t.table_name,
                   t.played_date, t.day_of_week, t.played_time, t.done,
                   tp.player_id, p.name AS player_name, p.nickname AS player_nickname,
                   c.icon_filename AS player_icon, tp.seat_order,
                   s.eliminated_round,
                   SUM(rr.score) AS score
            FROM tables_info t
            JOIN table_players tp ON tp.table_id = t.id
            JOIN players p ON p.id = tp.player_id
            LEFT JOIN characters c ON c.id = p.character_id
            LEFT JOIN standings s ON s.tournament_id = t.tournament_id AND s.player_id = tp.player_id
            LEFT JOIN round_results rr ON rr.player_id = tp.player_id
                  AND rr.tournament_id = t.tournament_id AND rr.round_number = t.round_number
            WHERE t.tournament_id = ?
            GROUP BY t.id, t.round_number, t.table_name, t.played_date, t.day_of_week,
                     t.played_time, t.done, tp.player_id, p.name, p.nickname,
                     c.icon_filename, tp.seat_order, s.eliminated_round
            ORDER BY t.round_number, t.table_name, SUM(rr.score) DESC NULLS LAST, tp.seat_order
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
                    'played_time' => $row['played_time'],
                    'done'        => $row['done'],
                    'players'     => [],
                ];
            }
            $rounds[$rn][$key]['players'][] = [
                'player_id' => (int) $row['player_id'],
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
     * 特定ラウンドの卓と各卓メンバーを Discord 告知用に取得する。
     *
     * 各メンバーの discord_user_id を含む。Discord 未連携選手は null。
     *
     * @return array<int, array{
     *   table_id:int, table_name:string,
     *   players:array<int, array{
     *     player_id:int, name:string, nickname:?string, discord_user_id:?string
     *   }>
     * }>
     */
    public static function byRoundForAnnounce(int $tournamentId, int $roundNumber): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT t.id AS table_id, t.table_name,
                   tp.player_id, p.name, p.nickname, p.discord_user_id, tp.seat_order
            FROM tables_info t
            JOIN table_players tp ON tp.table_id = t.id
            JOIN players p ON p.id = tp.player_id
            WHERE t.tournament_id = ? AND t.round_number = ?
            ORDER BY t.id, tp.seat_order
        ');
        $stmt->execute([$tournamentId, $roundNumber]);

        $tables = [];
        foreach ($stmt->fetchAll() as $row) {
            $tableId = (int) $row['table_id'];
            if (!isset($tables[$tableId])) {
                $tables[$tableId] = [
                    'table_id'   => $tableId,
                    'table_name' => $row['table_name'],
                    'players'    => [],
                ];
            }
            $tables[$tableId]['players'][] = [
                'player_id'       => (int) $row['player_id'],
                'name'            => $row['name'],
                'nickname'        => $row['nickname'],
                'discord_user_id' => $row['discord_user_id'],
            ];
        }
        return array_values($tables);
    }

    /**
     * 全卓のフラット一覧を取得する（管理向け一覧ページ用）。
     *
     * フィルタ:
     *   - event_types: EventType::value の配列（空なら全種別）
     *   - status: 'all' | 'done' | 'pending'
     *   - keyword: 大会名・卓名・選手呼称/名前への ILIKE 部分一致
     *
     * @param array{event_types?: string[], status?: string, keyword?: string} $filters
     * @return array<int, array{
     *   table_id:int, tournament_id:int, tournament_name:string, event_type:string,
     *   round_number:int, table_name:string,
     *   played_date:?string, day_of_week:string, played_time:string, done:bool,
     *   players: array<int, array{player_id:int, name:string, icon:?string, seat_order:int}>
     * }>
     */
    public static function searchAll(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = self::buildSearchWhere($filters);

        $pdo = getDbConnection();

        // 親（卓）取得（ページング対象）
        $sql = "
            SELECT ti.id AS table_id, ti.tournament_id, t.name AS tournament_name,
                   COALESCE(tm.value, '') AS event_type,
                   ti.round_number, ti.table_name,
                   ti.played_date, ti.day_of_week, ti.played_time, ti.done,
                   t.created_at AS tournament_created_at
            FROM tables_info ti
            JOIN tournaments t ON t.id = ti.tournament_id
            LEFT JOIN tournament_meta tm
                  ON tm.tournament_id = t.id AND tm.key = 'event_type'
            WHERE $where
            ORDER BY t.created_at DESC, ti.tournament_id DESC,
                     ti.round_number ASC, ti.table_name ASC, ti.id ASC
            LIMIT ? OFFSET ?
        ";
        $stmt = $pdo->prepare($sql);
        $bindParams = array_merge($params, [$limit, $offset]);
        foreach ($bindParams as $i => $v) {
            $stmt->bindValue($i + 1, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            return [];
        }

        // 子（参加選手）を 1 クエリでまとめて取得
        $tableIds = array_map(fn($r) => (int) $r['table_id'], $rows);
        $in = implode(',', array_fill(0, count($tableIds), '?'));
        $playerStmt = $pdo->prepare("
            SELECT tp.table_id, tp.player_id, tp.seat_order,
                   p.name, p.nickname, c.icon_filename AS character_icon
            FROM table_players tp
            JOIN players p ON p.id = tp.player_id
            LEFT JOIN characters c ON c.id = p.character_id
            WHERE tp.table_id IN ($in)
            ORDER BY tp.table_id, tp.seat_order
        ");
        $playerStmt->execute($tableIds);
        $playersByTable = [];
        foreach ($playerStmt->fetchAll() as $p) {
            $tid = (int) $p['table_id'];
            $playersByTable[$tid][] = [
                'player_id'  => (int) $p['player_id'],
                'name'       => $p['nickname'] ?? $p['name'],
                'icon'       => $p['character_icon'],
                'seat_order' => (int) $p['seat_order'],
            ];
        }

        $result = [];
        foreach ($rows as $row) {
            $tid = (int) $row['table_id'];
            $result[] = [
                'table_id'        => $tid,
                'tournament_id'   => (int) $row['tournament_id'],
                'tournament_name' => $row['tournament_name'],
                'event_type'      => $row['event_type'],
                'round_number'    => (int) $row['round_number'],
                'table_name'      => $row['table_name'],
                'played_date'     => $row['played_date'],
                'day_of_week'     => $row['day_of_week'],
                'played_time'     => $row['played_time'],
                'done'            => (bool) $row['done'],
                'players'         => $playersByTable[$tid] ?? [],
            ];
        }
        return $result;
    }

    /**
     * 全卓検索の総件数。`searchAll` と同じフィルタ条件で件数のみ返す。
     *
     * @param array{event_types?: string[], status?: string, keyword?: string} $filters
     */
    public static function searchAllCount(array $filters): int
    {
        [$where, $params] = self::buildSearchWhere($filters);

        $pdo = getDbConnection();
        $sql = "
            SELECT COUNT(DISTINCT ti.id)
            FROM tables_info ti
            JOIN tournaments t ON t.id = ti.tournament_id
            LEFT JOIN tournament_meta tm
                  ON tm.tournament_id = t.id AND tm.key = 'event_type'
            WHERE $where
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * searchAll / searchAllCount 共通の WHERE 句を組み立てる。
     *
     * @return array{0:string, 1:array<int|string>}
     */
    private static function buildSearchWhere(array $filters): array
    {
        $clauses = ['1=1'];
        $params = [];

        $eventTypes = $filters['event_types'] ?? [];
        if (!empty($eventTypes)) {
            $in = implode(',', array_fill(0, count($eventTypes), '?'));
            $clauses[] = "COALESCE(tm.value, '') IN ($in)";
            foreach ($eventTypes as $v) {
                $params[] = (string) $v;
            }
        }

        $status = $filters['status'] ?? 'all';
        if ($status === 'done') {
            $clauses[] = 'ti.done = true';
        } elseif ($status === 'pending') {
            $clauses[] = 'ti.done = false';
        }

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            // '|' をエスケープ文字に指定し、ユーザー入力中の % _ | をリテラル化する。
            // 既定の '\' だと PDO の ? プレースホルダパーサが文字列リテラル内の
            // バックスラッシュで誤動作（SQLSTATE[HY093]）するため '|' を採用。
            $like = '%' . strtr($keyword, ['|' => '||', '%' => '|%', '_' => '|_']) . '%';
            $clauses[] = "(
                t.name ILIKE ? ESCAPE '|'
                OR ti.table_name ILIKE ? ESCAPE '|'
                OR EXISTS (
                    SELECT 1 FROM table_players tp2
                    JOIN players p2 ON p2.id = tp2.player_id
                    WHERE tp2.table_id = ti.id
                      AND (p2.name ILIKE ? ESCAPE '|' OR COALESCE(p2.nickname, '') ILIKE ? ESCAPE '|')
                )
            )";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        return [implode(' AND ', $clauses), $params];
    }

    /**
     * 特定大会で特定選手が参加した卓情報をラウンドごとに取得。
     * 同卓メンバーのスコア（ラウンド合計）・通過判定を含む。
     */
    public static function byPlayerAndTournament(int $tournamentId, int $playerId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT ti.round_number, ti.table_name, ti.played_date, ti.day_of_week, ti.done,
                   p.id AS member_id, p.name AS member_name, tp2.seat_order,
                   c.icon_filename AS character_icon,
                   s.eliminated_round,
                   SUM(rr.score) AS score
            FROM table_players tp
            JOIN tables_info ti ON ti.id = tp.table_id
            JOIN table_players tp2 ON tp2.table_id = tp.table_id
            JOIN players p ON p.id = tp2.player_id
            LEFT JOIN characters c ON c.id = p.character_id
            LEFT JOIN standings s ON s.tournament_id = ti.tournament_id AND s.player_id = tp2.player_id
            LEFT JOIN round_results rr ON rr.player_id = tp2.player_id
                  AND rr.round_number = ti.round_number
                  AND rr.tournament_id = ti.tournament_id
            WHERE tp.player_id = ? AND ti.tournament_id = ?
            GROUP BY ti.round_number, ti.table_name, ti.played_date, ti.day_of_week, ti.done,
                     p.id, p.name, tp2.seat_order, c.icon_filename, s.eliminated_round
            ORDER BY ti.round_number, SUM(rr.score) DESC NULLS LAST
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
                'id'               => (int) $row['member_id'],
                'name'             => $row['member_name'],
                'character_icon'   => $row['character_icon'],
                'seat_order'       => $row['seat_order'],
                'score'            => $row['score'],
                'eliminated_round' => (int) ($row['eliminated_round'] ?? 0),
            ];
        }
        return array_values($rounds);
    }
}
