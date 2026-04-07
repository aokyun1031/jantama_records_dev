<?php

declare(strict_types=1);

enum HanRestriction: string
{
    case One = '1';
    case Two = '2';
    case Four = '4';

    public function label(): string
    {
        return match ($this) {
            self::One => '一翻縛り',
            self::Two => '二翻縛り',
            self::Four => '四翻縛り',
        };
    }
}
