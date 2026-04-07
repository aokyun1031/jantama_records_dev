<?php

declare(strict_types=1);

enum TournamentStatus: string
{
    case Preparing = 'preparing';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Preparing => '準備中',
            self::InProgress => '開催中',
            self::Completed => '終了',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Preparing => 'preparing',
            self::InProgress => 'active',
            self::Completed => 'completed',
        };
    }
}
