<?php

namespace App\Enums;

use App\Models\MaterialCategory;
use App\Models\MaterialStatus as StatusModel;

enum MaterialCatalog: string
{
    case Category = 'category';
    case Status = 'status';

    public function label(): string
    {
        return $this === self::Category ? 'Kategori' : 'Status';
    }

    public function modelClass(): string
    {
        return $this === self::Category ? MaterialCategory::class : StatusModel::class;
    }

    public function permission(string $action): string
    {
        return ($this === self::Category ? 'material_categories_' : 'material_statuses_').$action;
    }
}
