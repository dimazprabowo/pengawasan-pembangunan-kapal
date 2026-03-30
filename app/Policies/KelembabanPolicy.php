<?php

namespace App\Policies;

use App\Models\Kelembaban;
use App\Models\User;

class KelembabanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('kelembaban_view');
    }

    public function view(User $user, Kelembaban $kelembaban): bool
    {
        return $user->can('kelembaban_view');
    }

    public function create(User $user): bool
    {
        return $user->can('kelembaban_create');
    }

    public function update(User $user, Kelembaban $kelembaban): bool
    {
        return $user->can('kelembaban_update');
    }

    public function delete(User $user, Kelembaban $kelembaban): bool
    {
        return $user->can('kelembaban_delete');
    }

    public function toggleStatus(User $user, Kelembaban $kelembaban): bool
    {
        return $user->can('kelembaban_update');
    }

    public function exportExcel(User $user): bool
    {
        return $user->can('kelembaban_export_excel');
    }

    public function exportPdf(User $user): bool
    {
        return $user->can('kelembaban_export_pdf');
    }
}
