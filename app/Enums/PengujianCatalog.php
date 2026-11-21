<?php

namespace App\Enums;

use App\Models\PengujianStatus as StatusModel;

enum PengujianCatalog: string
{
    case Status = 'status';

    public function label(): string
    {
        return 'Status';
    }

    public function modelClass(): string
    {
        return StatusModel::class;
    }

    public function permission(string $action): string
    {
        return 'pengujian_statuses_'.$action;
    }
}
