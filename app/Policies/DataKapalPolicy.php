<?php

namespace App\Policies;

use App\Models\DataKapal;
use App\Models\User;

class DataKapalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('data_kapal_view');
    }

    public function view(User $user, DataKapal $dataKapal): bool
    {
        return $user->can('data_kapal_view');
    }

    public function create(User $user): bool
    {
        return $user->can('data_kapal_create');
    }

    public function update(User $user, DataKapal $dataKapal): bool
    {
        return $user->can('data_kapal_update');
    }

    public function delete(User $user, DataKapal $dataKapal): bool
    {
        return $user->can('data_kapal_delete');
    }

    public function toggleStatus(User $user, DataKapal $dataKapal): bool
    {
        return $user->can('data_kapal_update');
    }

    public function exportExcel(User $user): bool
    {
        return $user->can('data_kapal_export_excel');
    }

    public function exportPdf(User $user): bool
    {
        return $user->can('data_kapal_export_pdf');
    }

    public function uploadTemplate(User $user, DataKapal $dataKapal): bool
    {
        return $user->can('data_kapal_upload_template');
    }

    public function downloadTemplate(User $user): bool
    {
        return $user->can('data_kapal_download_template');
    }

    public function managekurvaSRencana(User $user, DataKapal $dataKapal): bool
    {
        return $user->can('data_kapal_manage_kurva_s');
    }

    public function viewkurvaSRencana(User $user, DataKapal $dataKapal): bool
    {
        return $user->can('data_kapal_view');
    }

    public function exportKurvaSTemplate(User $user, DataKapal $dataKapal): bool
    {
        return $user->can('data_kapal_manage_kurva_s');
    }

    public function importKurvaSTemplate(User $user, DataKapal $dataKapal): bool
    {
        return $user->can('data_kapal_manage_kurva_s');
    }
}
