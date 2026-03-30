<?php

namespace App\Enums;

enum LaporanTipe: string
{
    case Harian = 'harian';
    case Mingguan = 'mingguan';
    case Bulanan = 'bulanan';

    public function label(): string
    {
        return match ($this) {
            self::Harian => 'Harian',
            self::Mingguan => 'Mingguan',
            self::Bulanan => 'Bulanan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Harian => 'blue',
            self::Mingguan => 'amber',
            self::Bulanan => 'emerald',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
