<?php

declare(strict_types=1);

enum RoundType: string
{
    case Hanchan = 'hanchan';
    case Tonpu = 'tonpu';
    case Ikkyoku = 'ikkyoku';

    public function label(): string
    {
        return match ($this) {
            self::Hanchan => '半荘',
            self::Tonpu => '東風',
            self::Ikkyoku => '一局',
        };
    }
}
