<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('companies_view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('companies_create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Company $company): bool
    {
        return $user->can('companies_update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Company $company): bool
    {
        // Prevent deletion if company has active users
        if ($company->users()->exists()) {
            return false;
        }

        return $user->can('companies_delete');
    }

    /**
     * Determine whether the user can toggle status.
     */
    public function toggleStatus(User $user, Company $company): bool
    {
        return $user->can('companies_update');
    }

    /**
     * Determine whether the user can export to Excel.
     */
    public function exportExcel(User $user): bool
    {
        return $user->can('companies_export_excel');
    }

    /**
     * Determine whether the user can export to PDF.
     */
    public function exportPdf(User $user): bool
    {
        return $user->can('companies_export_pdf');
    }
}
