<?php

namespace App\Enums;

enum NcStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Open => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
            self::Closed => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
