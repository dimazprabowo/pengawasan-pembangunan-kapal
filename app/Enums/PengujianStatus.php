<?php

namespace App\Enums;

enum PengujianStatus: string
{
    case Direncanakan = 'direncanakan';
    case Berjalan = 'berjalan';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Direncanakan => 'Direncanakan',
            self::Berjalan => 'Sedang Berjalan',
            self::Selesai => 'Selesai',
        };
    }

    public function badgeClass(): string
    {
        return 'text-center '.match ($this) {
            self::Direncanakan => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
            self::Berjalan => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
            self::Selesai => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
