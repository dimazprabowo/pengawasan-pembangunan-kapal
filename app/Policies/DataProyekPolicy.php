<?php

namespace App\Policies;

use App\Models\User;

/**
 * Satu policy untuk semua entity data proyek (DrawingRegister,
 * ProjectVendor, ProjectMaterial, Pengujian, Rapat) — diregistrasi ke
 * kelima model di AppServiceProvider (pola ChatPolicy → Chat/ChatMessage).
 * Parameter model tidak dipakai karena izin berbasis permission prefix
 * proyek_data, bukan per-instance.
 */
class DataProyekPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('proyek_data_view');
    }

    public function view(User $user, $model): bool
    {
        return $user->can('proyek_data_view');
    }

    public function create(User $user): bool
    {
        return $user->can('proyek_data_create');
    }

    public function update(User $user, $model): bool
    {
        return $user->can('proyek_data_update');
    }

    public function delete(User $user, $model): bool
    {
        return $user->can('proyek_data_delete');
    }
}
