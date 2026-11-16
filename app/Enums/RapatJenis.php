<?php

namespace App\Enums;

enum RapatJenis: string
{
    case Mingguan = 'mingguan';
    case Bulanan = 'bulanan';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Mingguan => 'Rapat Mingguan',
            self::Bulanan => 'Rapat Bulanan',
            self::Lainnya => 'Lainnya',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Mingguan => 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-400',
            self::Bulanan => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400',
            self::Lainnya => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
