<?php

declare(strict_types=1);

enum ToggleRule: string
{
    case Enabled = '1';
    case Disabled = '0';

    public function label(string $name): string
    {
        return match ($this) {
            self::Enabled => $name . '有',
            self::Disabled => $name . '無',
        };
    }
}
