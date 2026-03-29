<?php

declare(strict_types=1);

class Player
{
    public static function all(): array
    {
        $pdo = getDbConnection();
        return $pdo->query(
            "SELECT p.id, p.name, p.nickname,
                    c.icon_filename AS character_icon
               FROM players p
               LEFT JOIN characters c ON c.id = p.character_id
              ORDER BY p.name"
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'SELECT p.id, p.name, p.nickname, p.character_id, c.icon_filename AS character_icon
               FROM players p
               LEFT JOIN characters c ON c.id = p.character_id
              WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $name, string $nickname, int $characterId): int
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('INSERT INTO players (name, nickname, character_id) VALUES (?, ?, ?)');
        $stmt->execute([$name, $nickname, $characterId]);
        return (int) $pdo->lastInsertId();
    }

    public static function existsByName(string $name): bool
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT EXISTS(SELECT 1 FROM players WHERE name = ?)');
        $stmt->execute([$name]);
        return (bool) $stmt->fetchColumn();
    }

    public static function update(int $id, ?string $nickname, ?int $characterId): void
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('UPDATE players SET nickname = ?, character_id = ? WHERE id = ?');
        $stmt->execute([$nickname, $characterId, $id]);
    }

    public static function hasTournaments(int $id): bool
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT EXISTS(SELECT 1 FROM standings WHERE player_id = ?)');
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }

    public static function delete(int $id): void
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('DELETE FROM players WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function count(): int
    {
        $pdo = getDbConnection();
        return (int) $pdo->query('SELECT COUNT(*) FROM players')->fetchColumn();
    }
}
