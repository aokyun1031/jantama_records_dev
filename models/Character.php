<?php

declare(strict_types=1);

class Character
{
    public static function all(): array
    {
        $pdo = getDbConnection();
        return $pdo->query('SELECT id, name, icon_filename FROM characters ORDER BY name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT id, name, icon_filename FROM characters WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
