<?php

namespace App\Policies;

use App\Models\Cuaca;
use App\Models\User;

class CuacaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cuaca_view');
    }

    public function view(User $user, Cuaca $cuaca): bool
    {
        return $user->can('cuaca_view');
    }

    public function create(User $user): bool
    {
        return $user->can('cuaca_create');
    }

    public function update(User $user, Cuaca $cuaca): bool
    {
        return $user->can('cuaca_update');
    }

    public function delete(User $user, Cuaca $cuaca): bool
    {
        return $user->can('cuaca_delete');
    }

    public function toggleStatus(User $user, Cuaca $cuaca): bool
    {
        return $user->can('cuaca_update');
    }

    public function exportExcel(User $user): bool
    {
        return $user->can('cuaca_export_excel');
    }

    public function exportPdf(User $user): bool
    {
        return $user->can('cuaca_export_pdf');
    }
}
