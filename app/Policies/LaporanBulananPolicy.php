<?php

namespace App\Policies;

use App\Models\LaporanBulanan;
use App\Models\User;

class LaporanBulananPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('laporan_view');
    }

    /**
     * Determine whether the user can view the model detail.
     */
    public function view(User $user, LaporanBulanan $laporanBulanan): bool
    {
        return $user->can('laporan_show');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('laporan_create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LaporanBulanan $laporanBulanan): bool
    {
        return $user->can('laporan_update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LaporanBulanan $laporanBulanan): bool
    {
        return $user->can('laporan_delete');
    }

    /**
     * Determine whether the user can download files.
     */
    public function download(User $user, LaporanBulanan $laporanBulanan): bool
    {
        return $user->can('laporan_download');
    }

    /**
     * Determine whether the user can export to Excel.
     */
    public function exportExcel(User $user): bool
    {
        return $user->can('laporan_export_excel');
    }

    /**
     * Determine whether the user can export to PDF.
     */
    public function exportPdf(User $user): bool
    {
        return $user->can('laporan_export_pdf');
    }

    /**
     * Determine whether the user can view all jenis kapal.
     */
    public function viewAllJenisKapal(User $user): bool
    {
        return $user->can('laporan_view_all_jenis_kapal');
    }

    /**
     * Determine whether the user can download lampiran/external files.
     */
    public function lampiranDownload(User $user): bool
    {
        return $user->can('laporan_lampiran_download');
    }
}
