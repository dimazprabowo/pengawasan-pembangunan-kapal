<?php

namespace App\Policies;

use App\Models\LaporanNcForm;
use App\Models\User;

/**
 * Form NC dikelola di List NC — permission sama dengan kelola NC
 * (laporan_nc_update), lihat daftar ikut laporan_view.
 */
class LaporanNcFormPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('laporan_view');
    }

    /**
     * Generate draft (temuan/penutupan) & unduh dokumen Word.
     */
    public function create(User $user): bool
    {
        return $user->can('laporan_nc_update');
    }

    /**
     * Unggah scan bertanda tangan — form penutupan sekaligus menutup NC.
     */
    public function upload(User $user, LaporanNcForm $form): bool
    {
        return $user->can('laporan_nc_update');
    }

    /**
     * Hanya draft (belum bertanda tangan) yang boleh dihapus.
     */
    public function delete(User $user, LaporanNcForm $form): bool
    {
        return $user->can('laporan_nc_update') && $form->isDraft();
    }
}
