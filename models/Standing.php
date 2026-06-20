<?php

declare(strict_types=1);

class Standing
{
    /**
     * 総合順位を取得（選手名・アイコン付き）。
     * 勝ち抜き中 → 総合ポイント降順、敗退 → 敗退ラウンド降順・ポイント降順。
     */
    public static function all(int $tournamentId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT s.rank, s.player_id, p.name, p.nickname, p.discord_user_id,
                   s.total, s.pending, s.eliminated_round,
                   c.icon_filename AS character_icon
            FROM standings s
            JOIN players p ON p.id = s.player_id
            LEFT JOIN characters c ON c.id = p.character_id
            WHERE s.tournament_id = ?
            ORDER BY s.total DESC
        ');
        $stmt->execute([$tournamentId]);
        return $stmt->fetchAll();
    }

    /**
     * 決勝進出者を取得（ラウンドスコア付き）。
     */
    public static function finalists(int $tournamentId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT p.name, p.nickname, s.total, c.icon_filename AS character_icon,
                   string_agg(
                       CASE WHEN round_sum >= 0 THEN '+' ELSE '' END || round_sum::text,
                       ' → ' ORDER BY round_number
                   ) AS trend
            FROM standings s
            JOIN players p ON p.id = s.player_id
            LEFT JOIN characters c ON c.id = p.character_id
            JOIN (
                SELECT player_id, tournament_id, round_number, SUM(score) AS round_sum
                FROM round_results
                GROUP BY player_id, tournament_id, round_number
            ) r ON r.player_id = s.player_id AND r.tournament_id = s.tournament_id
            WHERE s.tournament_id = ? AND s.eliminated_round = 0
            GROUP BY p.name, p.nickname, s.total, c.icon_filename
            ORDER BY s.total DESC
        ");
        $stmt->execute([$tournamentId]);
        return $stmt->fetchAll();
    }

    /**
     * 大会の全順位を round_results から再計算する。
     */
    public static function updateTotals(int $tournamentId): void
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            UPDATE standings s
            SET total = COALESCE(sub.sum_score, 0)
            FROM (
                SELECT player_id, SUM(score) AS sum_score
                FROM round_results
                WHERE tournament_id = ?
                GROUP BY player_id
            ) sub
            WHERE s.tournament_id = ? AND s.player_id = sub.player_id
        ');
        $stmt->execute([$tournamentId, $tournamentId]);
    }

    /**
     * ラウンド完了後の勝ち抜き判定。各卓上位または全体上位で判定する。
     */
    public static function processRoundAdvancement(int $tournamentId, int $roundNumber, int $advanceCount, string $advanceMode = 'per_table'): void
    {
        $pdo = getDbConnection();

        // ラウンドの全卓と選手を取得（ゲーム別スコアの合計）
        $stmt = $pdo->prepare('
            SELECT ti.id AS table_id, tp.player_id, SUM(rr.score) AS score
            FROM tables_info ti
            JOIN table_players tp ON tp.table_id = ti.id
            LEFT JOIN round_results rr ON rr.player_id = tp.player_id
                  AND rr.tournament_id = ti.tournament_id AND rr.round_number = ti.round_number
            WHERE ti.tournament_id = ? AND ti.round_number = ?
            GROUP BY ti.id, tp.player_id
            ORDER BY ti.id, SUM(rr.score) DESC NULLS LAST
        ');
        $stmt->execute([$tournamentId, $roundNumber]);
        $rows = $stmt->fetchAll();

        if ($advanceMode === 'overall') {
            // 全体上位: 全プレイヤーをスコア降順でソートし、上位N名以外を敗退に
            usort($rows, fn($a, $b) => (float) ($b['score'] ?? 0) <=> (float) ($a['score'] ?? 0));
            $allPlayers = array_map(fn($r) => (int) $r['player_id'], $rows);
            $eliminatedIds = array_slice($allPlayers, $advanceCount);
        } else {
            // 各卓上位: 卓ごとにグループ化し、各卓の上位N名以外を敗退に
            $tables = [];
            foreach ($rows as $row) {
                $tables[$row['table_id']][] = (int) $row['player_id'];
            }
            $eliminatedIds = [];
            foreach ($tables as $players) {
                $eliminated = array_slice($players, $advanceCount);
                $eliminatedIds = array_merge($eliminatedIds, $eliminated);
            }
        }

        if ($eliminatedIds) {
            $placeholders = implode(',', array_fill(0, count($eliminatedIds), '?'));
            $stmt = $pdo->prepare(
                "UPDATE standings SET eliminated_round = ?
                 WHERE tournament_id = ? AND player_id IN ($placeholders) AND eliminated_round = 0"
            );
            $stmt->execute(array_merge([$roundNumber, $tournamentId], $eliminatedIds));
        }
    }

    /**
     * 優勝者（勝ち抜き中で最高ポイント）を取得する。
     */
    public static function champion(int $tournamentId): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT s.player_id, p.name, p.nickname, s.total, s.eliminated_round,
                   c.icon_filename AS character_icon
            FROM standings s
            JOIN players p ON p.id = s.player_id
            LEFT JOIN characters c ON c.id = p.character_id
            WHERE s.tournament_id = ? AND s.eliminated_round = 0
            ORDER BY s.total DESC
            LIMIT 1
        ');
        $stmt->execute([$tournamentId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * 勝ち抜き中（未敗退）の選手IDを取得する。
     */
    public static function activePlayerIds(int $tournamentId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT player_id FROM standings WHERE tournament_id = ? AND eliminated_round = 0');
        $stmt->execute([$tournamentId]);
        return array_map(fn($r) => (int) $r['player_id'], $stmt->fetchAll());
    }

    /**
     * 選手ごとの合計ポイントをマップで取得する。
     *
     * @return array<int, float> player_id => total
     */
    public static function totalMap(int $tournamentId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT player_id, total FROM standings WHERE tournament_id = ?');
        $stmt->execute([$tournamentId]);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['player_id']] = (float) $row['total'];
        }
        return $map;
    }

    /**
     * 特定選手の順位を取得。
     */
    public static function findByPlayer(int $tournamentId, int $playerId): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT p.name, s.total, s.pending, s.eliminated_round,
                   (s.eliminated_round = 0 AND s.total = (
                       SELECT MAX(s2.total) FROM standings s2
                       WHERE s2.tournament_id = s.tournament_id AND s2.eliminated_round = 0
                   )) AS is_champion
            FROM standings s
            JOIN players p ON p.id = s.player_id
            WHERE s.tournament_id = ? AND s.player_id = ?
        ');
        $stmt->execute([$tournamentId, $playerId]);
        return $stmt->fetch() ?: null;
    }
}
