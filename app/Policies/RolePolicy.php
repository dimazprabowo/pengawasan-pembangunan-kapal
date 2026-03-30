<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('roles_view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('roles_create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->can('roles_update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Role $role): bool
    {
        if ($role->users()->count() > 0) {
            return false;
        }

        return $user->can('roles_delete');
    }

    /**
     * Determine whether the user can toggle permissions.
     */
    public function togglePermission(User $user, Role $role): bool
    {
        return $user->can('roles_update');
    }

    /**
     * Determine whether the user can export to Excel.
     */
    public function exportExcel(User $user): bool
    {
        return $user->can('roles_export_excel');
    }

    /**
     * Determine whether the user can export to PDF.
     */
    public function exportPdf(User $user): bool
    {
        return $user->can('roles_export_pdf');
    }
}
