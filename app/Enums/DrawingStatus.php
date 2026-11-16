<?php

namespace App\Enums;

/**
 * Status per tahap drawing register (produksi galangan, review MNO, approval Klas).
 */
enum DrawingStatus: string
{
    case Belum = 'belum';
    case Proses = 'proses';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Belum => 'Belum',
            self::Proses => 'Dalam Proses',
            self::Selesai => 'Selesai',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Belum => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
            self::Proses => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
            self::Selesai => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
