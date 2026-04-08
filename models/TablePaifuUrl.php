<?php

declare(strict_types=1);

class TablePaifuUrl
{
    /**
     * 卓の牌譜URLをゲーム番号順に取得する。
     */
    public static function byTable(int $tableId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT game_number, url FROM table_paifu_urls WHERE table_id = ? ORDER BY game_number');
        $stmt->execute([$tableId]);
        return $stmt->fetchAll();
    }

    /**
     * 牌譜URLを一括保存する（UPSERT）。
     *
     * @param array<int, string> $urls game_number => url
     */
    public static function saveAll(int $tableId, array $urls): void
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            INSERT INTO table_paifu_urls (table_id, game_number, url)
            VALUES (?, ?, ?)
            ON CONFLICT (table_id, game_number)
            DO UPDATE SET url = EXCLUDED.url
        ');
        foreach ($urls as $gameNumber => $url) {
            $stmt->execute([$tableId, $gameNumber, $url]);
        }
    }
}
