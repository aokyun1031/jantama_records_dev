<?php

declare(strict_types=1);

enum DayOfWeek: int
{
    case Sunday = 0;
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;

    public function label(): string
    {
        return match ($this) {
            self::Sunday => '日曜',
            self::Monday => '月曜',
            self::Tuesday => '火曜',
            self::Wednesday => '水曜',
            self::Thursday => '木曜',
            self::Friday => '金曜',
            self::Saturday => '土曜',
        };
    }

    /**
     * 日付文字列から曜日ラベルを取得する。
     */
    public static function fromDate(string $date): string
    {
        $ts = strtotime($date);
        if ($ts === false) {
            return '';
        }
        $dow = self::tryFrom((int) date('w', $ts));
        return $dow ? $dow->label() : '';
    }
}
