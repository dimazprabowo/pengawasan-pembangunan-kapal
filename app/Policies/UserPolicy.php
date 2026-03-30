<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users_view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('users_create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->can('users_update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        return $user->can('users_delete');
    }

    /**
     * Determine whether the user can toggle active status.
     */
    public function toggleActive(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        return $user->can('users_update');
    }

    /**
     * Determine whether the user can reset password.
     */
    public function resetPassword(User $user, User $model): bool
    {
        return $user->can('users_update');
    }

    /**
     * Determine whether the user can export to Excel.
     */
    public function exportExcel(User $user): bool
    {
        return $user->can('users_export_excel');
    }

    /**
     * Determine whether the user can export to PDF.
     */
    public function exportPdf(User $user): bool
    {
        return $user->can('users_export_pdf');
    }
}
