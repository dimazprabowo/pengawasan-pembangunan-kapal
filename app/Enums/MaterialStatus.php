<?php

namespace App\Enums;

enum MaterialStatus: string
{
    case Direncanakan = 'direncanakan';
    case Pengadaan = 'pengadaan';
    case Tiba = 'tiba';
    case Terpasang = 'terpasang';

    public function label(): string
    {
        return match ($this) {
            self::Direncanakan => 'Direncanakan',
            self::Pengadaan => 'Dalam Pengadaan',
            self::Tiba => 'Tiba di Galangan',
            self::Terpasang => 'Terpasang',
        };
    }

    public function badgeClass(): string
    {
        return 'text-center '.match ($this) {
            self::Direncanakan => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
            self::Pengadaan => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
            self::Tiba => 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-400',
            self::Terpasang => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
