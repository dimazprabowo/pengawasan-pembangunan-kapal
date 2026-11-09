<?php

namespace App\Enums;

/**
 * Status Form NC — diturunkan dari file_status scan TTD (bukan kolom tersendiri):
 * null = draft menunggu TTD, pending/processing = diproses, completed = selesai.
 */
enum NcFormStatus: string
{
    case MenungguTtd = 'menunggu_ttd';
    case Diproses = 'diproses';
    case Selesai = 'selesai';
    case Gagal = 'gagal';

    public static function fromFileStatus(?string $fileStatus): self
    {
        return match ($fileStatus) {
            null => self::MenungguTtd,
            'pending', 'processing' => self::Diproses,
            'completed' => self::Selesai,
            default => self::Gagal,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::MenungguTtd => 'Menunggu TTD',
            self::Diproses => 'Diproses',
            self::Selesai => 'Selesai',
            self::Gagal => 'Gagal',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::MenungguTtd => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
            self::Diproses => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            self::Selesai => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
            self::Gagal => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        };
    }
}
