<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Super Admin - All permissions (bypass via Gate::before in AuthServiceProvider)
        $superAdmin = Role::firstOrCreate(['name' => 'super admin']);
        $superAdmin->syncPermissions(Permission::all());

        // 2. Admin - Full Access (explicit permissions)
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        // 3. User - No default permissions
        // Admin must explicitly grant permissions via UI
        $user = Role::firstOrCreate(['name' => 'user']);
        $user->syncPermissions([]);
    }
}
