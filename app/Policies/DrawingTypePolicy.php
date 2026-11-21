<?php

namespace App\Policies;

use App\Models\DrawingType;
use App\Models\User;

class DrawingTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('drawing_types_view');
    }

    public function create(User $user): bool
    {
        return $user->can('drawing_types_create');
    }

    public function update(User $user, DrawingType $type): bool
    {
        return $user->can('drawing_types_update');
    }

    public function delete(User $user, DrawingType $type): bool
    {
        return $user->can('drawing_types_delete');
    }
}
