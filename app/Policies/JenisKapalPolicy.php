<?php

namespace App\Policies;

use App\Models\JenisKapal;
use App\Models\User;

class JenisKapalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('jenis_kapal_view');
    }

    public function view(User $user, JenisKapal $jenisKapal): bool
    {
        return $user->can('jenis_kapal_view');
    }

    public function create(User $user): bool
    {
        return $user->can('jenis_kapal_create');
    }

    public function update(User $user, JenisKapal $jenisKapal): bool
    {
        return $user->can('jenis_kapal_update');
    }

    public function delete(User $user, JenisKapal $jenisKapal): bool
    {
        return $user->can('jenis_kapal_delete');
    }

    public function toggleStatus(User $user, JenisKapal $jenisKapal): bool
    {
        return $user->can('jenis_kapal_update');
    }

    public function exportExcel(User $user): bool
    {
        return $user->can('jenis_kapal_export_excel');
    }

    public function exportPdf(User $user): bool
    {
        return $user->can('jenis_kapal_export_pdf');
    }
}
