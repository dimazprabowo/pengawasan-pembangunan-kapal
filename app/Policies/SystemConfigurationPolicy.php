<?php

namespace App\Policies;

use App\Models\SystemConfiguration;
use App\Models\User;

class SystemConfigurationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('configuration_view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('configuration_create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SystemConfiguration $config): bool
    {
        return $user->can('configuration_update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SystemConfiguration $config): bool
    {
        if (!$config->is_editable) {
            return false;
        }

        return $user->can('configuration_delete');
    }

    /**
     * Determine whether the user can toggle active status.
     */
    public function toggleActive(User $user, SystemConfiguration $config): bool
    {
        return $user->can('configuration_update');
    }

    /**
     * Determine whether the user can export to Excel.
     */
    public function exportExcel(User $user): bool
    {
        return $user->can('configuration_export_excel');
    }

    /**
     * Determine whether the user can export to PDF.
     */
    public function exportPdf(User $user): bool
    {
        return $user->can('configuration_export_pdf');
    }
}
