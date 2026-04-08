<?php

declare(strict_types=1);

class TournamentMeta
{
    /**
     * 全メタ情報をキーバリューの連想配列で取得。
     */
    public static function all(int $tournamentId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT key, value FROM tournament_meta WHERE tournament_id = ?');
        $stmt->execute([$tournamentId]);
        $rows = $stmt->fetchAll();

        $meta = [];
        foreach ($rows as $row) {
            $meta[$row['key']] = $row['value'];
        }
        return $meta;
    }

    /**
     * 複数大会のメタ情報を一括取得する。
     *
     * @param int[] $tournamentIds
     * @return array<int, array<string, string>> tournament_id => [key => value]
     */
    public static function allByTournamentIds(array $tournamentIds): array
    {
        if (empty($tournamentIds)) {
            return [];
        }
        $pdo = getDbConnection();
        $placeholders = implode(',', array_fill(0, count($tournamentIds), '?'));
        $stmt = $pdo->prepare("SELECT tournament_id, key, value FROM tournament_meta WHERE tournament_id IN ({$placeholders})");
        $stmt->execute($tournamentIds);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['tournament_id']][$row['key']] = $row['value'];
        }
        return $result;
    }

    /**
     * 特定キーの値を設定（UPSERT）。
     */
    public static function set(int $tournamentId, string $key, string $value): void
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO tournament_meta (tournament_id, key, value) VALUES (?, ?, ?)
             ON CONFLICT (tournament_id, key) DO UPDATE SET value = EXCLUDED.value'
        );
        $stmt->execute([$tournamentId, $key, $value]);
    }

    /**
     * 特定キーの値を取得。
     */
    public static function get(int $tournamentId, string $key, string $default = ''): string
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT value FROM tournament_meta WHERE tournament_id = ? AND key = ?');
        $stmt->execute([$tournamentId, $key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : $default;
    }
}
