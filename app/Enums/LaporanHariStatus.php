<?php

namespace App\Enums;

enum LaporanHariStatus: string
{
    case Kerja = 'kerja';
    case Libur = 'libur';

    public function label(): string
    {
        return match ($this) {
            self::Kerja => 'Hari Kerja',
            self::Libur => 'Libur',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Kerja => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            self::Libur => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        };
    }
}
