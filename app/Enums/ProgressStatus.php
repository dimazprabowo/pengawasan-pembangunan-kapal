<?php

namespace App\Enums;

/**
 * Status progres proyek kapal dari deviasi Kurva-S (aktual - rencana, poin persen).
 */
enum ProgressStatus: string
{
    case BelumAdaData = 'belum_ada_data';
    case LebihCepat = 'lebih_cepat';
    case SesuaiRencana = 'sesuai_rencana';
    case Waspada = 'waspada';
    case Terlambat = 'terlambat';

    public const AMBANG_WASPADA = -2.0;

    public const AMBANG_TERLAMBAT = -5.0;

    public static function fromDeviasi(?float $deviasi): self
    {
        return match (true) {
            $deviasi === null => self::BelumAdaData,
            $deviasi <= self::AMBANG_TERLAMBAT => self::Terlambat,
            $deviasi <= self::AMBANG_WASPADA => self::Waspada,
            $deviasi > 2.0 => self::LebihCepat,
            default => self::SesuaiRencana,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::BelumAdaData => 'Belum Ada Data',
            self::LebihCepat => 'Lebih Cepat',
            self::SesuaiRencana => 'Sesuai Rencana',
            self::Waspada => 'Waspada',
            self::Terlambat => 'Terlambat',
        };
    }

    public function badgeClass(): string
    {
        return 'text-center '.match ($this) {
            self::BelumAdaData => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
            self::LebihCepat => 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-300',
            self::SesuaiRencana => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
            self::Waspada => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
            self::Terlambat => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        };
    }

    public function barClass(): string
    {
        return match ($this) {
            self::BelumAdaData => 'bg-gray-400',
            self::LebihCepat => 'bg-sky-500',
            self::SesuaiRencana => 'bg-emerald-500',
            self::Waspada => 'bg-amber-500',
            self::Terlambat => 'bg-red-500',
        };
    }
}
