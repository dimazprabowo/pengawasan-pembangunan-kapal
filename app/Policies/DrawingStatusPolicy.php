<?php

namespace App\Policies;

use App\Models\DrawingStatus;
use App\Models\User;

class DrawingStatusPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('drawing_statuses_view');
    }

    public function create(User $user): bool
    {
        return $user->can('drawing_statuses_create');
    }

    public function update(User $user, DrawingStatus $status): bool
    {
        return $user->can('drawing_statuses_update');
    }

    public function delete(User $user, DrawingStatus $status): bool
    {
        return $user->can('drawing_statuses_delete');
    }
}
