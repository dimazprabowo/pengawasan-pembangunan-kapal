<?php

namespace App\Policies;

use App\Models\LaporanNc;
use App\Models\User;

class LaporanNcPolicy
{
    /**
     * List NC berada di dalam halaman Laporan Harian — ikut permission view laporan.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('laporan_view');
    }

    public function view(User $user, LaporanNc $nc): bool
    {
        return $user->can('laporan_show');
    }

    /**
     * Ubah status open/closed + kelola file bukti di List NC.
     */
    public function update(User $user, LaporanNc $nc): bool
    {
        return $user->can('laporan_nc_update');
    }
}
