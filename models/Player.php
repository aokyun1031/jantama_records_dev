<?php

declare(strict_types=1);

class Player
{
    public static function all(): array
    {
        $pdo = getDbConnection();
        return $pdo->query(
            "SELECT p.id, regexp_replace(p.name, '^[0-9]+', '') AS name, p.nickname,
                    c.icon_filename AS character_icon
               FROM players p
               LEFT JOIN characters c ON c.id = p.character_id
              ORDER BY regexp_replace(p.name, '^[0-9]+', '')"
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'SELECT p.id, p.name, p.nickname, c.icon_filename AS character_icon
               FROM players p
               LEFT JOIN characters c ON c.id = p.character_id
              WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function count(): int
    {
        $pdo = getDbConnection();
        return (int) $pdo->query('SELECT COUNT(*) FROM players')->fetchColumn();
    }
}
