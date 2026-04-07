<?php

declare(strict_types=1);

enum EventType: string
{
    case Saikyoi = 'saikyoi';
    case Hooh = 'hooh';
    case Masters = 'masters';
    case Hyakudanisen = 'hyakudanisen';
    case Petit = 'petit';

    public function label(): string
    {
        return match ($this) {
            self::Saikyoi => '最強位戦',
            self::Hooh => '鳳凰位戦',
            self::Masters => 'マスターズ',
            self::Hyakudanisen => '百段位戦',
            self::Petit => 'プチイベント',
        };
    }
}
