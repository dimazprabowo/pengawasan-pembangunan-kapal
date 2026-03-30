<?php

namespace App\Enums;

enum GalanganStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Tidak Aktif',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Active => 'success',
            self::Inactive => 'danger',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
