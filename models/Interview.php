<?php

declare(strict_types=1);

class Interview
{
    /**
     * 大会のインタビューQ&Aを取得する。
     */
    public static function byTournament(int $tournamentId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT id, sort_order, question, answer FROM interviews WHERE tournament_id = ? ORDER BY sort_order');
        $stmt->execute([$tournamentId]);
        return $stmt->fetchAll();
    }

    /**
     * 大会のインタビューを一括保存する（全削除→再作成）。
     *
     * @param array<array{question: string, answer: string}> $items
     */
    public static function save(int $tournamentId, array $items): void
    {
        $pdo = getDbConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('DELETE FROM interviews WHERE tournament_id = ?');
            $stmt->execute([$tournamentId]);

            $ins = $pdo->prepare(
                'INSERT INTO interviews (tournament_id, sort_order, question, answer) VALUES (?, ?, ?, ?)'
            );
            foreach ($items as $i => $item) {
                $ins->execute([$tournamentId, $i + 1, $item['question'], $item['answer']]);
            }

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
