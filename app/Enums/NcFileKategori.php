<?php

namespace App\Enums;

enum NcFileKategori: string
{
    case Before = 'before';
    case After = 'after';

    public function label(): string
    {
        return match ($this) {
            self::Before => 'Before',
            self::After => 'After',
        };
    }
}
