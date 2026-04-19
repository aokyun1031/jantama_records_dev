<?php

declare(strict_types=1);

/**
 * 全大会を横断した集計・ランキング・殿堂入りレコード。
 * LP（トップページ）の動的セクション向け。
 */
class HallOfFame
{
    /**
     * 通算優勝数ランキング（完了大会のみ）。
     *
     * @return array<array{player_id:int, player_name:string, character_icon:?string, win_count:int}>
     */
    public static function championCounts(int $limit = 3): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            WITH champions AS (
                SELECT DISTINCT ON (s.tournament_id) s.tournament_id, s.player_id
                FROM standings s
                JOIN tournaments t ON t.id = s.tournament_id
                WHERE s.eliminated_round = 0 AND t.status = 'completed'
                ORDER BY s.tournament_id, s.total DESC
            )
            SELECT p.id AS player_id,
                   COALESCE(p.nickname, p.name) AS player_name,
                   c.icon_filename AS character_icon,
                   COUNT(*) AS win_count
            FROM champions ch
            JOIN players p ON p.id = ch.player_id
            LEFT JOIN characters c ON c.id = p.character_id
            GROUP BY p.id, p.name, p.nickname, c.icon_filename
            ORDER BY win_count DESC, p.name ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * 通算獲得ポイント上位（完了大会のみ）。
     *
     * @return array<array{player_id:int, player_name:string, character_icon:?string, total_pt:float, tournament_count:int}>
     */
    public static function totalPointLeaders(int $limit = 10): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT p.id AS player_id,
                   COALESCE(p.nickname, p.name) AS player_name,
                   c.icon_filename AS character_icon,
                   SUM(s.total) AS total_pt,
                   COUNT(DISTINCT s.tournament_id) AS tournament_count
            FROM standings s
            JOIN tournaments t ON t.id = s.tournament_id
            JOIN players p ON p.id = s.player_id
            LEFT JOIN characters c ON c.id = p.character_id
            WHERE t.status = 'completed'
            GROUP BY p.id, p.name, p.nickname, c.icon_filename
            ORDER BY total_pt DESC, tournament_count DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * 歴代最高ラウンドスコア（全大会横断）。
     */
    public static function highestRoundScoreAllTime(): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->query('
            SELECT rr.score, rr.round_number, rr.tournament_id,
                   t.name AS tournament_name,
                   p.id AS player_id,
                   COALESCE(p.nickname, p.name) AS player_name,
                   c.icon_filename AS character_icon
            FROM round_results rr
            JOIN tournaments t ON t.id = rr.tournament_id
            JOIN players p ON p.id = rr.player_id
            LEFT JOIN characters c ON c.id = p.character_id
            ORDER BY rr.score DESC, rr.round_number ASC
            LIMIT 1
        ');
        return $stmt->fetch() ?: null;
    }

    /**
     * 歴代最多卓1位（全大会横断、同率は配列で返却）。
     *
     * @return array{top_count:int, winners: array<array{player_id:int, player_name:string, character_icon:?string}>}|null
     */
    public static function mostTopFinishesAllTime(): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->query('
            WITH table_scores AS (
                SELECT tp.table_id, tp.player_id, SUM(rr.score) AS table_score
                FROM table_players tp
                JOIN tables_info ti ON ti.id = tp.table_id
                JOIN round_results rr ON rr.player_id = tp.player_id
                     AND rr.round_number = ti.round_number
                     AND rr.tournament_id = ti.tournament_id
                GROUP BY tp.table_id, tp.player_id
            ),
            table_ranks AS (
                SELECT table_id, player_id,
                       RANK() OVER (PARTITION BY table_id ORDER BY table_score DESC) AS tbl_rank
                FROM table_scores
            ),
            player_tops AS (
                SELECT player_id, COUNT(*) AS top_count
                FROM table_ranks WHERE tbl_rank = 1
                GROUP BY player_id
            )
            SELECT p.id AS player_id,
                   COALESCE(p.nickname, p.name) AS player_name,
                   pt.top_count,
                   c.icon_filename AS character_icon
            FROM player_tops pt
            JOIN players p ON p.id = pt.player_id
            LEFT JOIN characters c ON c.id = p.character_id
            WHERE pt.top_count = (SELECT MAX(top_count) FROM player_tops)
            ORDER BY p.name ASC
        ');
        $rows = $stmt->fetchAll();
        if (!$rows) {
            return null;
        }
        return [
            'top_count' => (int) $rows[0]['top_count'],
            'winners' => $rows,
        ];
    }

    /**
     * サイト全体の集計統計。
     *
     * @return array{total_tournaments:int, completed_tournaments:int, total_players:int, total_tables:int, done_tables:int, total_rounds:int}
     */
    public static function siteStats(): array
    {
        $pdo = getDbConnection();
        $row = $pdo->query("
            SELECT
                (SELECT COUNT(*) FROM tournaments) AS total_tournaments,
                (SELECT COUNT(*) FROM tournaments WHERE status = 'completed') AS completed_tournaments,
                (SELECT COUNT(*) FROM players) AS total_players,
                (SELECT COUNT(*) FROM tables_info) AS total_tables,
                (SELECT COUNT(*) FROM tables_info WHERE done = true) AS done_tables,
                (SELECT COUNT(*) FROM (SELECT DISTINCT tournament_id, round_number, game_number FROM round_results) s) AS total_rounds
        ")->fetch();
        return [
            'total_tournaments' => (int) $row['total_tournaments'],
            'completed_tournaments' => (int) $row['completed_tournaments'],
            'total_players' => (int) $row['total_players'],
            'total_tables' => (int) $row['total_tables'],
            'done_tables' => (int) $row['done_tables'],
            'total_rounds' => (int) $row['total_rounds'],
        ];
    }

    /**
     * イベント種別ごとの最新大会。
     *
     * @return array<string, array>  event_type (EventType::value) => tournament row
     */
    public static function latestByEventType(): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->query("
            SELECT DISTINCT ON (tm.value)
                   tm.value AS event_type,
                   t.id, t.name, t.status, t.created_at,
                   COALESCE(pc.cnt, 0) AS player_count,
                   w.winner_name
            FROM tournaments t
            JOIN tournament_meta tm ON tm.tournament_id = t.id AND tm.key = 'event_type'
            LEFT JOIN (
                SELECT tournament_id, COUNT(*) AS cnt FROM standings GROUP BY tournament_id
            ) pc ON pc.tournament_id = t.id
            LEFT JOIN (
                SELECT DISTINCT ON (s.tournament_id) s.tournament_id,
                       COALESCE(p.nickname, p.name) AS winner_name
                FROM standings s
                JOIN players p ON p.id = s.player_id
                WHERE s.eliminated_round = 0
                ORDER BY s.tournament_id, s.total DESC
            ) w ON w.tournament_id = t.id
            WHERE tm.value IS NOT NULL AND tm.value != ''
            ORDER BY tm.value, t.created_at DESC
        ");
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['event_type']] = $row;
        }
        return $result;
    }

    /**
     * 進行中大会の現在ラウンド。未完の卓のうち最小ラウンドを返す。
     * 全卓完了ならラウンド最大値を返す。
     */
    public static function currentRound(int $tournamentId): ?int
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT
                COALESCE(
                    (SELECT MIN(round_number) FROM tables_info WHERE tournament_id = ? AND done = false),
                    (SELECT MAX(round_number) FROM tables_info WHERE tournament_id = ?)
                ) AS current_round
        ');
        $stmt->execute([$tournamentId, $tournamentId]);
        $row = $stmt->fetch();
        return ($row && $row['current_round'] !== null) ? (int) $row['current_round'] : null;
    }

    /**
     * 直近のインタビュー掲載大会を取得する。
     *
     * @return array{tournament_id:int, tournament_name:string, qa_count:int}|null
     */
    public static function latestInterview(): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->query('
            SELECT t.id AS tournament_id, t.name AS tournament_name,
                   COUNT(i.id) AS qa_count
            FROM tournaments t
            JOIN interviews i ON i.tournament_id = t.id
            GROUP BY t.id, t.name, t.created_at
            HAVING COUNT(i.id) > 0
            ORDER BY t.created_at DESC
            LIMIT 1
        ');
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return [
            'tournament_id' => (int) $row['tournament_id'],
            'tournament_name' => $row['tournament_name'],
            'qa_count' => (int) $row['qa_count'],
        ];
    }
}
