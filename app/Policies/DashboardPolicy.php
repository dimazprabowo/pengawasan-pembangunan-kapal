<?php

namespace App\Policies;

use App\Models\User;

class DashboardPolicy
{
    /**
     * Determine whether the user can view the dashboard with application statistics.
     */
    public function viewStats(User $user): bool
    {
        return $user->can('dashboard_view');
    }
}
