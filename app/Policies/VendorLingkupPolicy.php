<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorLingkup;

class VendorLingkupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('vendor_lingkups_view');
    }

    public function create(User $user): bool
    {
        return $user->can('vendor_lingkups_create');
    }

    public function update(User $user, VendorLingkup $lingkup): bool
    {
        return $user->can('vendor_lingkups_update');
    }

    public function delete(User $user, VendorLingkup $lingkup): bool
    {
        return $user->can('vendor_lingkups_delete');
    }
}
