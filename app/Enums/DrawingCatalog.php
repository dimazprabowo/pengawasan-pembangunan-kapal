<?php

namespace App\Enums;

use App\Models\DrawingStatus as StatusModel;
use App\Models\DrawingType;

enum DrawingCatalog: string
{
    case Type = 'type';
    case Status = 'status';

    public function label(): string
    {
        return $this === self::Type ? 'Jenis Gambar' : 'Status';
    }

    public function modelClass(): string
    {
        return $this === self::Type ? DrawingType::class : StatusModel::class;
    }

    public function permission(string $action): string
    {
        return ($this === self::Type ? 'drawing_types_' : 'drawing_statuses_').$action;
    }
}
