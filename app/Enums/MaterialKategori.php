<?php

namespace App\Enums;

enum MaterialKategori: string
{
    case SteelPlate = 'steel_plate';
    case SystemKomponen = 'system_komponen';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::SteelPlate => 'Steel Plate',
            self::SystemKomponen => 'System / Komponen',
            self::Lainnya => 'Lainnya',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::SteelPlate => 'bg-slate-100 text-slate-800 dark:bg-slate-900/30 dark:text-slate-400',
            self::SystemKomponen => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400',
            self::Lainnya => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
