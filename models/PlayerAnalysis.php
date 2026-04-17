<?php

declare(strict_types=1);

class PlayerAnalysis
{
    /**
     * 選択した大会種別のみに絞り込むSQLフラグメント。
     * $selectedEventTypes は EventType の value で検証済みの配列を受け取る前提。
     * $tournamentIdColumn は呼び出し側で指定するハードコードのカラム参照。
     */
    private static function eventTypeFilterFragment(array $selectedEventTypes, string $tournamentIdColumn): string
    {
        if (empty($selectedEventTypes)) {
            return '';
        }
        $quoted = array_map(fn(string $v): string => "'{$v}'", $selectedEventTypes);
        $list = implode(',', $quoted);
        return " AND EXISTS (SELECT 1 FROM tournament_meta tm_ef"
            . " WHERE tm_ef.tournament_id = {$tournamentIdColumn}"
            . " AND tm_ef.key = 'event_type' AND tm_ef.value IN ({$list}))";
    }

    /**
     * 選択した大会種別のリストを tm.value 参照用の IN 句に変換する。
     * eventTypeStats 内の JOIN 条件で使用する。
     */
    private static function eventTypeInClause(array $selectedEventTypes): string
    {
        if (empty($selectedEventTypes)) {
            return '';
        }
        $quoted = array_map(fn(string $v): string => "'{$v}'", $selectedEventTypes);
        return ' AND tm.value IN (' . implode(',', $quoted) . ')';
    }

    /**
     * 通算成績サマリー + 卓内着順統計（トップ率・ラス率・連帯率・スコア標準偏差）を1クエリで返す。
     * 旧 summary() と rankStats() を統合し、DB往復を2→1に削減している。
     */
    public static function summary(int $playerId, array $selectedEventTypes = []): ?array
    {
        $pdo = getDbConnection();
        $filterTi = self::eventTypeFilterFragment($selectedEventTypes, 'ti.tournament_id');
        $filterRr = self::eventTypeFilterFragment($selectedEventTypes, 'rr.tournament_id');
        $stmt = $pdo->prepare("
            WITH final_rounds AS (
                SELECT tournament_id, MAX(round_number) AS final_round
                FROM round_results
                GROUP BY tournament_id
            ),
            table_scores AS (
                SELECT tp.table_id, tp.player_id, SUM(rr.score) AS table_score
                FROM table_players tp
                JOIN tables_info ti ON ti.id = tp.table_id
                JOIN round_results rr ON rr.player_id = tp.player_id
                     AND rr.round_number = ti.round_number
                     AND rr.tournament_id = ti.tournament_id
                WHERE 1=1{$filterTi}
                GROUP BY tp.table_id, tp.player_id
            ),
            table_ranks AS (
                SELECT table_id, player_id,
                       RANK() OVER (PARTITION BY table_id ORDER BY table_score DESC) AS tbl_rank,
                       COUNT(*) OVER (PARTITION BY table_id) AS table_size
                FROM table_scores
            ),
            round_agg AS (
                SELECT COUNT(*) AS total_rounds,
                       COUNT(DISTINCT rr.tournament_id) AS total_tournaments,
                       AVG(rr.score) AS avg_score,
                       MAX(rr.score) AS best_score,
                       SUM(CASE WHEN rr.round_number < fr.final_round AND rr.is_above_cutoff THEN 1 ELSE 0 END) AS qualifying_passes,
                       SUM(CASE WHEN rr.round_number < fr.final_round THEN 1 ELSE 0 END) AS qualifying_rounds,
                       STDDEV_SAMP(rr.score) AS score_stddev
                FROM round_results rr
                JOIN final_rounds fr ON fr.tournament_id = rr.tournament_id
                WHERE rr.player_id = ?{$filterRr}
            ),
            rank_agg AS (
                SELECT COUNT(*) AS table_games,
                       SUM(CASE WHEN tbl_rank = 1 THEN 1 ELSE 0 END) AS top_count,
                       SUM(CASE WHEN tbl_rank = table_size THEN 1 ELSE 0 END) AS last_count,
                       SUM(CASE WHEN tbl_rank <= 2 THEN 1 ELSE 0 END) AS second_or_better
                FROM table_ranks
                WHERE player_id = ?
            )
            SELECT r.total_rounds, r.total_tournaments, r.avg_score, r.best_score,
                   r.qualifying_passes, r.qualifying_rounds, r.score_stddev,
                   rk.table_games, rk.top_count, rk.last_count, rk.second_or_better
            FROM round_agg r CROSS JOIN rank_agg rk
        ");
        $stmt->execute([$playerId, $playerId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * 卓内平均着順（全大会合算）。
     */
    public static function avgTableRank(int $playerId, array $selectedEventTypes = []): ?string
    {
        $pdo = getDbConnection();
        $filter = self::eventTypeFilterFragment($selectedEventTypes, 'ti.tournament_id');
        $stmt = $pdo->prepare("
            SELECT AVG(table_rank)
            FROM (
                SELECT rr.player_id,
                       RANK() OVER (PARTITION BY tp.table_id ORDER BY rr.score DESC) AS table_rank
                FROM table_players tp
                JOIN tables_info ti ON ti.id = tp.table_id
                JOIN round_results rr ON rr.player_id = tp.player_id
                     AND rr.round_number = ti.round_number
                     AND rr.tournament_id = ti.tournament_id
                WHERE 1=1{$filter}
            ) sub
            WHERE player_id = ?
        ");
        $stmt->execute([$playerId]);
        return $stmt->fetchColumn() ?: null;
    }

    /**
     * 同卓対戦成績（全大会合算）。
     */
    public static function headToHead(int $playerId, array $selectedEventTypes = []): array
    {
        $pdo = getDbConnection();
        $filter = self::eventTypeFilterFragment($selectedEventTypes, 'ti.tournament_id');
        $stmt = $pdo->prepare("
            WITH table_ranks AS (
                SELECT tp.table_id, tp.player_id,
                       RANK() OVER (PARTITION BY tp.table_id ORDER BY rr.score DESC) AS tbl_rank
                FROM table_players tp
                JOIN tables_info ti ON ti.id = tp.table_id
                JOIN round_results rr ON rr.player_id = tp.player_id
                     AND rr.round_number = ti.round_number
                     AND rr.tournament_id = ti.tournament_id
                WHERE 1=1{$filter}
            )
            SELECT p.id AS opponent_id,
                   regexp_replace(p.name, '^[0-9]+', '') AS opponent_name,
                   COALESCE(h.games, 0) AS games,
                   COALESCE(h.wins, 0) AS wins,
                   COALESCE(h.losses, 0) AS losses,
                   h.avg_my_score,
                   h.avg_opp_score,
                   h.avg_my_rank,
                   h.avg_opp_rank
            FROM players p
            LEFT JOIN (
                SELECT tp2.player_id AS opp_id,
                       COUNT(*) AS games,
                       SUM(CASE WHEN rr1.score > rr2.score THEN 1 ELSE 0 END) AS wins,
                       SUM(CASE WHEN rr1.score < rr2.score THEN 1 ELSE 0 END) AS losses,
                       AVG(rr1.score) AS avg_my_score,
                       AVG(rr2.score) AS avg_opp_score,
                       AVG(tr_me.tbl_rank) AS avg_my_rank,
                       AVG(tr.tbl_rank) AS avg_opp_rank
                FROM table_players tp1
                JOIN table_players tp2 ON tp2.table_id = tp1.table_id AND tp2.player_id != tp1.player_id
                JOIN tables_info ti ON ti.id = tp1.table_id
                JOIN round_results rr1 ON rr1.player_id = tp1.player_id
                     AND rr1.round_number = ti.round_number AND rr1.tournament_id = ti.tournament_id
                JOIN round_results rr2 ON rr2.player_id = tp2.player_id
                     AND rr2.round_number = ti.round_number AND rr2.tournament_id = ti.tournament_id
                JOIN table_ranks tr ON tr.table_id = tp2.table_id AND tr.player_id = tp2.player_id
                JOIN table_ranks tr_me ON tr_me.table_id = tp1.table_id AND tr_me.player_id = tp1.player_id
                WHERE tp1.player_id = ?{$filter}
                GROUP BY tp2.player_id
            ) h ON h.opp_id = p.id
            WHERE p.id != ?
            ORDER BY games DESC, opponent_name
        ");
        $stmt->execute([$playerId, $playerId]);
        return $stmt->fetchAll();
    }

    /**
     * スコア推移（全大会、新しい大会順）。
     */
    public static function scoreHistory(int $playerId, array $selectedEventTypes = []): array
    {
        $pdo = getDbConnection();
        $filter = self::eventTypeFilterFragment($selectedEventTypes, 'rr.tournament_id');
        $stmt = $pdo->prepare("
            SELECT t.name AS tournament_name, rr.round_number, rr.score, rr.is_above_cutoff,
                   tbl.played_date, tbl.day_of_week, tbl.played_time
            FROM round_results rr
            JOIN tournaments t ON t.id = rr.tournament_id
            LEFT JOIN LATERAL (
                SELECT ti.played_date, ti.day_of_week, ti.played_time
                FROM table_players tp
                JOIN tables_info ti ON ti.id = tp.table_id
                WHERE tp.player_id = rr.player_id
                  AND ti.tournament_id = rr.tournament_id
                  AND ti.round_number = rr.round_number
                LIMIT 1
            ) tbl ON true
            WHERE rr.player_id = ?{$filter}
            ORDER BY t.created_at DESC, rr.round_number
        ");
        $stmt->execute([$playerId]);
        return $stmt->fetchAll();
    }

    /**
     * 卓内着順分布（1位〜4位の各出現回数）。ドーナツチャート用。
     */
    public static function rankDistribution(int $playerId, array $selectedEventTypes = []): array
    {
        $pdo = getDbConnection();
        $filter = self::eventTypeFilterFragment($selectedEventTypes, 'ti.tournament_id');
        $stmt = $pdo->prepare("
            WITH table_scores AS (
                SELECT tp.table_id, tp.player_id, SUM(rr.score) AS table_score
                FROM table_players tp
                JOIN tables_info ti ON ti.id = tp.table_id
                JOIN round_results rr ON rr.player_id = tp.player_id
                     AND rr.round_number = ti.round_number
                     AND rr.tournament_id = ti.tournament_id
                WHERE 1=1{$filter}
                GROUP BY tp.table_id, tp.player_id
            ),
            table_ranks AS (
                SELECT table_id, player_id,
                       RANK() OVER (PARTITION BY table_id ORDER BY table_score DESC) AS tbl_rank
                FROM table_scores
            )
            SELECT tbl_rank AS rank, COUNT(*) AS cnt
            FROM table_ranks
            WHERE player_id = ?
            GROUP BY tbl_rank
            ORDER BY tbl_rank
        ");
        $stmt->execute([$playerId]);
        return $stmt->fetchAll();
    }

    /**
     * スコア時系列（古い順）。累計スコア推移チャート用。
     */
    public static function scoreTimeline(int $playerId, array $selectedEventTypes = []): array
    {
        $pdo = getDbConnection();
        $filter = self::eventTypeFilterFragment($selectedEventTypes, 'rr.tournament_id');
        $stmt = $pdo->prepare("
            SELECT t.name AS tournament_name, rr.round_number, rr.game_number, rr.score
            FROM round_results rr
            JOIN tournaments t ON t.id = rr.tournament_id
            WHERE rr.player_id = ?{$filter}
            ORDER BY t.created_at ASC, rr.round_number ASC, rr.game_number ASC
        ");
        $stmt->execute([$playerId]);
        return $stmt->fetchAll();
    }

    /**
     * 回戦別パフォーマンス（1回戦目/2回戦目/...の平均スコアと試合数）。
     */
    public static function roundPerformance(int $playerId, array $selectedEventTypes = []): array
    {
        $pdo = getDbConnection();
        $filter = self::eventTypeFilterFragment($selectedEventTypes, 'tournament_id');
        $stmt = $pdo->prepare("
            SELECT round_number,
                   AVG(score) AS avg_score,
                   COUNT(*) AS games
            FROM round_results
            WHERE player_id = ?{$filter}
            GROUP BY round_number
            ORDER BY round_number
        ");
        $stmt->execute([$playerId]);
        return $stmt->fetchAll();
    }

    /**
     * イベント種別別の平均卓内着順と参加卓数。
     * tournament_meta.event_type が未設定の大会は除外する。
     * $selectedEventTypes が指定されている場合、その種別のみを集計対象にする。
     */
    public static function eventTypeStats(int $playerId, array $selectedEventTypes = []): array
    {
        $pdo = getDbConnection();
        $filter = self::eventTypeInClause($selectedEventTypes);
        $stmt = $pdo->prepare("
            WITH table_scores AS (
                SELECT tp.table_id, tp.player_id, ti.tournament_id, SUM(rr.score) AS table_score
                FROM table_players tp
                JOIN tables_info ti ON ti.id = tp.table_id
                JOIN round_results rr ON rr.player_id = tp.player_id
                     AND rr.round_number = ti.round_number
                     AND rr.tournament_id = ti.tournament_id
                GROUP BY tp.table_id, tp.player_id, ti.tournament_id
            ),
            table_ranks AS (
                SELECT player_id, tournament_id,
                       RANK() OVER (PARTITION BY table_id ORDER BY table_score DESC) AS tbl_rank
                FROM table_scores
            )
            SELECT tm.value AS event_type,
                   AVG(tr.tbl_rank) AS avg_rank,
                   COUNT(*) AS games,
                   SUM(CASE WHEN tr.tbl_rank = 1 THEN 1 ELSE 0 END) AS tops
            FROM table_ranks tr
            JOIN tournament_meta tm ON tm.tournament_id = tr.tournament_id AND tm.key = ?{$filter}
            WHERE tr.player_id = ?
            GROUP BY tm.value
        ");
        $stmt->execute(['event_type', $playerId]);
        return $stmt->fetchAll();
    }

    /**
     * 参加大会における最高最終順位（1位が最良）。
     * rank は standings.total 降順で ROW_NUMBER() により再計算する。
     */
    public static function bestFinalRank(int $playerId, array $selectedEventTypes = []): ?int
    {
        $pdo = getDbConnection();
        $filter = self::eventTypeFilterFragment($selectedEventTypes, 'tournament_id');
        $stmt = $pdo->prepare("
            SELECT MIN(final_rank) AS best_rank
            FROM (
                SELECT player_id,
                       ROW_NUMBER() OVER (PARTITION BY tournament_id ORDER BY total DESC) AS final_rank
                FROM standings
                WHERE 1=1{$filter}
            ) sub
            WHERE player_id = ?
        ");
        $stmt->execute([$playerId]);
        $value = $stmt->fetchColumn();
        return $value !== false && $value !== null ? (int) $value : null;
    }
}
