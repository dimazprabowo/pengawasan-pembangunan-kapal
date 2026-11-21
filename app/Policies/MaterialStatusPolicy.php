<?php

namespace App\Policies;

use App\Models\MaterialStatus;
use App\Models\User;

class MaterialStatusPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('material_statuses_view');
    }

    public function create(User $user): bool
    {
        return $user->can('material_statuses_create');
    }

    public function update(User $user, MaterialStatus $status): bool
    {
        return $user->can('material_statuses_update');
    }

    public function delete(User $user, MaterialStatus $status): bool
    {
        return $user->can('material_statuses_delete');
    }
}
