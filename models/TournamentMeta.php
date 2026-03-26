<?php

declare(strict_types=1);

class TournamentMeta
{
    /**
     * 全メタ情報をキーバリューの連想配列で取得。
     */
    public static function all(): array
    {
        $pdo = getDbConnection();
        $rows = $pdo->query('SELECT key, value FROM tournament_meta')->fetchAll();
        $meta = [];
        foreach ($rows as $row) {
            $meta[$row['key']] = $row['value'];
        }
        return $meta;
    }

    /**
     * 特定キーの値を取得。
     */
    public static function get(string $key, string $default = ''): string
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT value FROM tournament_meta WHERE key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : $default;
    }
}
