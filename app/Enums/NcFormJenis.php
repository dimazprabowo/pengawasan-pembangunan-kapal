<?php

namespace App\Enums;

enum NcFormJenis: string
{
    case Temuan = 'temuan';
    case Penutupan = 'penutupan';

    public function label(): string
    {
        return match ($this) {
            self::Temuan => 'Form Temuan',
            self::Penutupan => 'Form Penutupan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Temuan => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
            self::Penutupan => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
        };
    }

    /**
     * Judul dokumen Word.
     */
    public function judulDokumen(): string
    {
        return match ($this) {
            self::Temuan => 'NON CONFORMANCE REPORT',
            self::Penutupan => 'NON CONFORMANCE REPORT (PENUTUPAN)',
        };
    }

    /**
     * Status NC yang dicetak di kolom Status dokumen.
     */
    public function statusDokumen(): NcStatus
    {
        return match ($this) {
            self::Temuan => NcStatus::Open,
            self::Penutupan => NcStatus::Closed,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
