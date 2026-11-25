<?php

namespace App\Enums;

/**
 * Status tunggal untuk setiap riwayat revisi drawing register.
 */
enum DrawingStatus: string
{
    case Seen = 'seen';
    case SeenWithNote = 'seen with note';
    case ApproveWithNote = 'approve with note';
    case NotApprove = 'not approve';
    case Return = 'return';
    case InProgress = 'in-progress';
    case Examined = 'examined';

    public function label(): string
    {
        return match ($this) {
            self::Seen => 'Seen',
            self::SeenWithNote => 'Seen with Note',
            self::ApproveWithNote => 'Approve with Note',
            self::NotApprove => 'Not Approve',
            self::Return => 'Return',
            self::InProgress => 'In-Progress',
            self::Examined => 'Examined',
        };
    }

    public function badgeClass(): string
    {
        return 'text-center '.match ($this) {
            self::Seen => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
            self::SeenWithNote, self::ApproveWithNote => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
            self::NotApprove, self::Return => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
            self::InProgress => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
            self::Examined => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
