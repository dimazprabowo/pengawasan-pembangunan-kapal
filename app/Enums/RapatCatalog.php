<?php

namespace App\Enums;

use App\Models\RapatJenis as JenisModel;

enum RapatCatalog: string
{
    case Jenis = 'jenis';

    public function label(): string
    {
        return 'Jenis Rapat';
    }

    public function modelClass(): string
    {
        return JenisModel::class;
    }

    public function permission(string $action): string
    {
        return 'rapat_jenis_'.$action;
    }
}
