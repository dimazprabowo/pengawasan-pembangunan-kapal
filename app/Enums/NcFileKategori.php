<?php

namespace App\Enums;

enum NcFileKategori: string
{
    case Open = 'open';
    case Close = 'close';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Close => 'Close',
        };
    }
}
