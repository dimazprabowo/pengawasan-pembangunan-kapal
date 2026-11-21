<?php

namespace App\Policies;

use App\Models\PengujianStatus;
use App\Models\User;

class PengujianStatusPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pengujian_statuses_view');
    }

    public function create(User $user): bool
    {
        return $user->can('pengujian_statuses_create');
    }

    public function update(User $user, PengujianStatus $status): bool
    {
        return $user->can('pengujian_statuses_update');
    }

    public function delete(User $user, PengujianStatus $status): bool
    {
        return $user->can('pengujian_statuses_delete');
    }
}
