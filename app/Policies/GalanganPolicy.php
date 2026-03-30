<?php

namespace App\Policies;

use App\Models\Galangan;
use App\Models\User;

class GalanganPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('galangan_view');
    }

    public function view(User $user, Galangan $galangan): bool
    {
        return $user->can('galangan_view');
    }

    public function create(User $user): bool
    {
        return $user->can('galangan_create');
    }

    public function update(User $user, Galangan $galangan): bool
    {
        return $user->can('galangan_update');
    }

    public function delete(User $user, Galangan $galangan): bool
    {
        return $user->can('galangan_delete');
    }

    public function toggleStatus(User $user, Galangan $galangan): bool
    {
        return $user->can('galangan_update');
    }

    public function exportExcel(User $user): bool
    {
        return $user->can('galangan_export_excel');
    }

    public function exportPdf(User $user): bool
    {
        return $user->can('galangan_export_pdf');
    }
}
