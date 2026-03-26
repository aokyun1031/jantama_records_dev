<?php

declare(strict_types=1);

class Player
{
    public static function all(): array
    {
        $pdo = getDbConnection();
        return $pdo->query('SELECT id, name FROM players ORDER BY id')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT id, name FROM players WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function count(): int
    {
        $pdo = getDbConnection();
        return (int) $pdo->query('SELECT COUNT(*) FROM players')->fetchColumn();
    }
}
