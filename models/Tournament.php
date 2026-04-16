<?php

declare(strict_types=1);

class Tournament
{
    public static function all(): array
    {
        $pdo = getDbConnection();
        return $pdo->query('SELECT id, name, status, created_at FROM tournaments ORDER BY created_at DESC')->fetchAll();
    }

    /**
     * 大会一覧（参加人数・イベント種別付き）。
     */
    public static function allWithDetails(): array
    {
        $pdo = getDbConnection();
        return $pdo->query("
            WITH player_counts AS (
                SELECT tournament_id, COUNT(*) AS cnt
                FROM standings GROUP BY tournament_id
            ),
            winner AS (
                SELECT DISTINCT ON (s.tournament_id)
                       s.tournament_id, COALESCE(p.nickname, p.name) AS winner_name
                FROM standings s
                JOIN players p ON p.id = s.player_id
                WHERE s.eliminated_round = 0
                ORDER BY s.tournament_id, s.total DESC
            ),
            date_range AS (
                SELECT tournament_id,
                       MIN(played_date) AS start_date, MAX(played_date) AS end_date
                FROM tables_info WHERE played_date IS NOT NULL
                GROUP BY tournament_id
            )
            SELECT t.id, t.name, t.status, t.created_at,
                   COALESCE(tm.value, '') AS event_type,
                   COALESCE(pc.cnt, 0) AS player_count,
                   w.winner_name, dr.start_date, dr.end_date
            FROM tournaments t
            LEFT JOIN tournament_meta tm ON tm.tournament_id = t.id AND tm.key = 'event_type'
            LEFT JOIN player_counts pc ON pc.tournament_id = t.id
            LEFT JOIN winner w ON w.tournament_id = t.id
            LEFT JOIN date_range dr ON dr.tournament_id = t.id
            ORDER BY t.created_at DESC
        ")->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT id, name, status, created_at FROM tournaments WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * 大会をメタ情報付きで取得する。
     */
    public static function findWithMeta(int $id): ?array
    {
        $tournament = self::find($id);
        if (!$tournament) {
            return null;
        }
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT key, value FROM tournament_meta WHERE tournament_id = ?');
        $stmt->execute([$id]);
        $meta = [];
        foreach ($stmt->fetchAll() as $row) {
            $meta[$row['key']] = $row['value'];
        }
        $tournament['meta'] = $meta;
        return $tournament;
    }

    /**
     * 大会の参加選手IDを取得する。
     */
    public static function playerIds(int $id): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT player_id FROM standings WHERE tournament_id = ?');
        $stmt->execute([$id]);
        return array_map(fn($row) => (int) $row['player_id'], $stmt->fetchAll());
    }

    /**
     * 大会名とメタ情報を更新する。
     */
    public static function updateDetails(int $id, string $name, array $meta): void
    {
        $pdo = getDbConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE tournaments SET name = ? WHERE id = ?');
            $stmt->execute([$name, $id]);

            $metaStmt = $pdo->prepare(
                'INSERT INTO tournament_meta (tournament_id, key, value) VALUES (?, ?, ?)
                 ON CONFLICT (tournament_id, key) DO UPDATE SET value = EXCLUDED.value'
            );
            foreach ($meta as $key => $value) {
                $metaStmt->execute([$id, $key, $value]);
            }

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 大会で対局済み（卓に割り当て済み）の選手IDを取得する。
     */
    public static function playedPlayerIds(int $id): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT DISTINCT tp.player_id
            FROM table_players tp
            JOIN tables_info ti ON ti.id = tp.table_id
            WHERE ti.tournament_id = ?
        ');
        $stmt->execute([$id]);
        return array_map(fn($row) => (int) $row['player_id'], $stmt->fetchAll());
    }

    /**
     * 大会の参加選手を更新する（差分で追加・削除）。
     * 対局済みの選手は削除できない。
     */
    public static function updatePlayers(int $id, array $playerIds): void
    {
        // 対局済み選手をトランザクション外で事前取得
        $playedIds = self::playedPlayerIds($id);
        // 対局済み選手はリストに強制追加（削除防止）
        $playerIds = array_values(array_unique(array_merge($playerIds, $playedIds)));

        $pdo = getDbConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT player_id FROM standings WHERE tournament_id = ?');
            $stmt->execute([$id]);
            $currentIds = array_map(fn($row) => (int) $row['player_id'], $stmt->fetchAll());

            $toAdd = array_diff($playerIds, $currentIds);
            $toRemove = array_diff($currentIds, $playerIds);

            if ($toRemove) {
                $placeholders = implode(',', array_fill(0, count($toRemove), '?'));
                $stmt = $pdo->prepare(
                    "DELETE FROM standings WHERE tournament_id = ? AND player_id IN ($placeholders)"
                );
                $stmt->execute(array_merge([$id], array_values($toRemove)));
            }

            $addStmt = $pdo->prepare(
                'INSERT INTO standings (tournament_id, player_id, rank, total, pending, eliminated_round)
                 VALUES (?, ?, 0, 0, false, 0)'
            );
            foreach ($toAdd as $playerId) {
                $addStmt->execute([$id, $playerId]);
            }

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 大会を作成し、メタ情報と参加選手を登録する。
     *
     * @param array<string, string> $meta
     * @param int[] $playerIds
     */
    public static function createWithDetails(string $name, array $meta, array $playerIds): int
    {
        $pdo = getDbConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO tournaments (name, status) VALUES (?, ?)');
            $stmt->execute([$name, TournamentStatus::Preparing->value]);
            $tournamentId = (int) $pdo->lastInsertId();

            $metaStmt = $pdo->prepare(
                'INSERT INTO tournament_meta (tournament_id, key, value) VALUES (?, ?, ?)
                 ON CONFLICT (tournament_id, key) DO UPDATE SET value = EXCLUDED.value'
            );
            foreach ($meta as $key => $value) {
                $metaStmt->execute([$tournamentId, $key, $value]);
            }

            $standingStmt = $pdo->prepare(
                'INSERT INTO standings (tournament_id, player_id, rank, total, pending, eliminated_round)
                 VALUES (?, ?, 0, 0, false, 0)'
            );
            foreach ($playerIds as $playerId) {
                $standingStmt->execute([$tournamentId, $playerId]);
            }

            $pdo->commit();
            return $tournamentId;
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 大会を開催中にする（準備中の場合のみ）。
     */
    public static function start(int $id): void
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('UPDATE tournaments SET status = ? WHERE id = ? AND status = ?');
        $stmt->execute([TournamentStatus::InProgress->value, $id, TournamentStatus::Preparing->value]);
    }

    /**
     * ラウンド完了処理。全卓完了なら勝ち抜き判定を行い、フラッシュメッセージを返す。
     */
    public static function processRoundCompletion(int $tournamentId, int $roundNumber): string
    {
        $rounds = TableInfo::byTournament($tournamentId);
        $currentTables = $rounds[$roundNumber] ?? [];
        $allDone = !empty($currentTables) && empty(array_filter($currentTables, fn($t) => !$t['done']));

        $msg = '卓を完了しました。';
        if ($allDone) {
            $rk = 'round_' . $roundNumber;
            $isFinal = (TournamentMeta::get($tournamentId, $rk . '_is_final') === '1');
            $advanceCount = (int) TournamentMeta::get($tournamentId, $rk . '_advance_count', '0');
            $advanceMode = TournamentMeta::get($tournamentId, $rk . '_advance_mode', 'per_table');

            if (!$isFinal && $advanceCount > 0) {
                Standing::processRoundAdvancement($tournamentId, $roundNumber, $advanceCount, $advanceMode);
                $msg = $roundNumber . '回戦が全卓完了しました。勝ち抜き判定を行いました。';
            } elseif ($isFinal) {
                $msg = '決勝が完了しました！';
            } else {
                $msg = $roundNumber . '回戦が全卓完了しました。';
            }
        }
        return $msg;
    }

    /**
     * 大会を完了にする。
     */
    public static function complete(int $id): void
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('UPDATE tournaments SET status = ? WHERE id = ?');
        $stmt->execute([TournamentStatus::Completed->value, $id]);
    }

    /**
     * 大会を削除する（CASCADE で関連データも削除される）。
     */
    public static function delete(int $id): void
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('DELETE FROM tournaments WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function byPlayer(int $playerId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            "SELECT t.id, t.name, t.status, t.created_at,
                    s.total, s.eliminated_round,
                    COALESCE(tm.value, '') AS event_type,
                    (SELECT MAX(rr.round_number)
                     FROM round_results rr
                     WHERE rr.tournament_id = t.id AND rr.player_id = s.player_id
                    ) AS last_round,
                    (SELECT MAX(rr2.round_number)
                     FROM round_results rr2
                     WHERE rr2.tournament_id = t.id
                    ) AS max_round,
                    (s.eliminated_round = 0 AND s.total = (
                        SELECT MAX(s2.total) FROM standings s2
                        WHERE s2.tournament_id = t.id AND s2.eliminated_round = 0
                    )) AS is_champion
             FROM tournaments t
             JOIN standings s ON s.tournament_id = t.id AND s.player_id = ?
             LEFT JOIN tournament_meta tm ON tm.tournament_id = t.id AND tm.key = 'event_type'
             ORDER BY t.created_at DESC"
        );
        $stmt->execute([$playerId]);
        return $stmt->fetchAll();
    }
}
