<?php

namespace App\Enums;

use App\Models\VendorLingkup;

enum VendorCatalog: string
{
    case Lingkup = 'lingkup';

    public function label(): string
    {
        return 'Lingkup Pekerjaan';
    }

    public function modelClass(): string
    {
        return VendorLingkup::class;
    }

    public function permission(string $action): string
    {
        return 'vendor_lingkups_'.$action;
    }
}
