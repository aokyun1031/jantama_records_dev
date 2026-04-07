<?php

declare(strict_types=1);

enum PlayerMode: string
{
    case Four = '4';
    case Three = '3';

    public function label(): string
    {
        return match ($this) {
            self::Four => '四麻',
            self::Three => '三麻',
        };
    }

    public function fullLabel(): string
    {
        return match ($this) {
            self::Four => '四人麻雀',
            self::Three => '三人麻雀',
        };
    }
}
