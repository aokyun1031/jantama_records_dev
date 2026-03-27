<?php

declare(strict_types=1);

class Tournament
{
    public static function all(): array
    {
        $pdo = getDbConnection();
        return $pdo->query('SELECT id, name, status, created_at FROM tournaments ORDER BY created_at DESC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT id, name, status, created_at FROM tournaments WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function byPlayer(int $playerId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'SELECT t.id, t.name, t.status, t.created_at,
                    s.rank, s.total, s.eliminated_round,
                    (SELECT MAX(rr.round_number)
                     FROM round_results rr
                     WHERE rr.tournament_id = t.id AND rr.player_id = s.player_id
                    ) AS last_round
             FROM tournaments t
             JOIN standings s ON s.tournament_id = t.id AND s.player_id = ?
             ORDER BY t.created_at DESC'
        );
        $stmt->execute([$playerId]);
        return $stmt->fetchAll();
    }
}
