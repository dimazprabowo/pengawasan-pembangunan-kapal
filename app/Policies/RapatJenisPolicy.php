<?php

namespace App\Policies;

use App\Models\RapatJenis;
use App\Models\User;

class RapatJenisPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('rapat_jenis_view');
    }

    public function create(User $user): bool
    {
        return $user->can('rapat_jenis_create');
    }

    public function update(User $user, RapatJenis $jenis): bool
    {
        return $user->can('rapat_jenis_update');
    }

    public function delete(User $user, RapatJenis $jenis): bool
    {
        return $user->can('rapat_jenis_delete');
    }
}
