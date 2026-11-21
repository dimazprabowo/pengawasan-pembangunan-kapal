<?php

namespace App\Policies;

use App\Models\MaterialCategory;
use App\Models\User;

class MaterialCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('material_categories_view');
    }

    public function create(User $user): bool
    {
        return $user->can('material_categories_create');
    }

    public function update(User $user, MaterialCategory $category): bool
    {
        return $user->can('material_categories_update');
    }

    public function delete(User $user, MaterialCategory $category): bool
    {
        return $user->can('material_categories_delete');
    }
}
