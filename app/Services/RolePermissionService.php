<?php

namespace App\Services;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Eloquent\Collection;

class RolePermissionService
{
    public function getAllRolesWithPermissions(): Collection
    {
        return Role::with('permissions')->get();
    }

    public function getAllPermissions(): Collection
    {
        return Permission::all();
    }

    public function getRolePermissions(int $roleId): array
    {
        $role = Role::with('permissions')->find($roleId);

        return $role ? $role->permissions->pluck('name')->toArray() : [];
    }

    public function createRole(string $name, array $permissions = []): Role
    {
        $role = Role::create(['name' => $name]);
        $role->syncPermissions($permissions);

        return $role;
    }

    public function updateRole(Role $role, string $name, array $permissions = []): Role
    {
        $role->update(['name' => $name]);
        $role->syncPermissions($permissions);

        return $role;
    }

    public function deleteRole(Role $role): void
    {
        $role->delete();
    }

    public function togglePermission(Role $role, string $permission): string
    {
        if ($role->hasPermissionTo($permission)) {
            $role->revokePermissionTo($permission);

            return 'revoked';
        }

        $role->givePermissionTo($permission);

        return 'granted';
    }

    public function roleHasUsers(Role $role): bool
    {
        return $role->users()->count() > 0;
    }

    /**
     * Build permission groups dynamically from database.
     * Maps permissions to groups based on naming convention.
     * Any unmatched permissions go to 'Lainnya' group.
     *
     * Returns: ['Group Name' => [['name' => 'permission_key', 'label' => 'Human Label'], ...]]
     */
    public function buildPermissionGroups(): array
    {
        $groupMapping = [
            'Dashboard' => [
                ['name' => 'dashboard_view', 'label' => 'Lihat Dashboard'],
            ],
            'Perusahaan' => [
                ['name' => 'companies_view',         'label' => 'Lihat Perusahaan'],
                ['name' => 'companies_create',       'label' => 'Tambah Perusahaan'],
                ['name' => 'companies_update',       'label' => 'Edit Perusahaan'],
                ['name' => 'companies_delete',       'label' => 'Hapus Perusahaan'],
                ['name' => 'companies_export_excel', 'label' => 'Export Excel Perusahaan'],
                ['name' => 'companies_export_pdf',   'label' => 'Export PDF Perusahaan'],
            ],
            'Jenis Kapal' => [
                ['name' => 'jenis_kapal_view',         'label' => 'Lihat Jenis Kapal'],
                ['name' => 'jenis_kapal_create',       'label' => 'Tambah Jenis Kapal'],
                ['name' => 'jenis_kapal_update',       'label' => 'Edit Jenis Kapal'],
                ['name' => 'jenis_kapal_delete',       'label' => 'Hapus Jenis Kapal'],
                ['name' => 'jenis_kapal_export_excel', 'label' => 'Export Excel Jenis Kapal'],
                ['name' => 'jenis_kapal_export_pdf',   'label' => 'Export PDF Jenis Kapal'],
            ],
            'Galangan' => [
                ['name' => 'galangan_view',         'label' => 'Lihat Galangan'],
                ['name' => 'galangan_create',       'label' => 'Tambah Galangan'],
                ['name' => 'galangan_update',       'label' => 'Edit Galangan'],
                ['name' => 'galangan_delete',       'label' => 'Hapus Galangan'],
                ['name' => 'galangan_export_excel', 'label' => 'Export Excel Galangan'],
                ['name' => 'galangan_export_pdf',   'label' => 'Export PDF Galangan'],
            ],
            'Kelembaban' => [
                ['name' => 'kelembaban_view',         'label' => 'Lihat Kelembaban'],
                ['name' => 'kelembaban_create',       'label' => 'Tambah Kelembaban'],
                ['name' => 'kelembaban_update',       'label' => 'Edit Kelembaban'],
                ['name' => 'kelembaban_delete',       'label' => 'Hapus Kelembaban'],
                ['name' => 'kelembaban_export_excel', 'label' => 'Export Excel Kelembaban'],
                ['name' => 'kelembaban_export_pdf',   'label' => 'Export PDF Kelembaban'],
            ],
            'Cuaca' => [
                ['name' => 'cuaca_view',         'label' => 'Lihat Cuaca'],
                ['name' => 'cuaca_create',       'label' => 'Tambah Cuaca'],
                ['name' => 'cuaca_update',       'label' => 'Edit Cuaca'],
                ['name' => 'cuaca_delete',       'label' => 'Hapus Cuaca'],
                ['name' => 'cuaca_export_excel', 'label' => 'Export Excel Cuaca'],
                ['name' => 'cuaca_export_pdf',   'label' => 'Export PDF Cuaca'],
            ],
            'Konfigurasi System' => [
                ['name' => 'configuration_view',         'label' => 'Lihat Konfigurasi'],
                ['name' => 'configuration_update',       'label' => 'Edit Konfigurasi'],
                ['name' => 'configuration_export_excel', 'label' => 'Export Excel Konfigurasi'],
                ['name' => 'configuration_export_pdf',   'label' => 'Export PDF Konfigurasi'],
            ],
            'Manajemen User' => [
                ['name' => 'users_view',         'label' => 'Lihat User'],
                ['name' => 'users_create',       'label' => 'Tambah User'],
                ['name' => 'users_update',       'label' => 'Edit User'],
                ['name' => 'users_delete',       'label' => 'Hapus User'],
                ['name' => 'users_export_excel', 'label' => 'Export Excel User'],
                ['name' => 'users_export_pdf',   'label' => 'Export PDF User'],
            ],
            'Roles & Permissions' => [
                ['name' => 'roles_view',         'label' => 'Lihat Roles'],
                ['name' => 'roles_create',       'label' => 'Tambah Role'],
                ['name' => 'roles_update',       'label' => 'Edit Role'],
                ['name' => 'roles_delete',       'label' => 'Hapus Role'],
                ['name' => 'roles_export_excel', 'label' => 'Export Excel Roles'],
                ['name' => 'roles_export_pdf',   'label' => 'Export PDF Roles'],
            ],
            'Notifikasi' => [
                ['name' => 'notifications_view', 'label' => 'Lihat Notifikasi'],
                ['name' => 'notifications_send', 'label' => 'Kirim Notifikasi'],
            ],
            'Chat' => [
                ['name' => 'chat_view',   'label' => 'Lihat Chat'],
                ['name' => 'chat_create', 'label' => 'Buat Chat'],
                ['name' => 'chat_delete', 'label' => 'Hapus Chat'],
            ],
            'Manajemen Laporan' => [
                ['name' => 'laporan_view',                'label' => 'Lihat Laporan'],
                ['name' => 'laporan_show',                'label' => 'Detail Laporan'],
                ['name' => 'laporan_create',              'label' => 'Tambah Laporan'],
                ['name' => 'laporan_update',              'label' => 'Edit Laporan'],
                ['name' => 'laporan_delete',              'label' => 'Hapus Laporan'],
                ['name' => 'laporan_download',            'label' => 'Download File Laporan'],
                ['name' => 'laporan_lampiran_preview',    'label' => 'Preview Lampiran'],
                ['name' => 'laporan_lampiran_download',   'label' => 'Download Lampiran'],
                ['name' => 'laporan_export_excel',        'label' => 'Export Excel Laporan'],
                ['name' => 'laporan_export_pdf',          'label' => 'Export PDF Laporan'],
                ['name' => 'laporan_view_all_jenis_kapal', 'label' => 'Laporan View All Jenis Kapal'],
            ],
        ];

        $allPermissions = Permission::pluck('name')->toArray();
        $mapped = collect($groupMapping)->flatten(1)->pluck('name')->toArray();
        $unmapped = array_diff($allPermissions, $mapped);

        $groups = $groupMapping;

        if (!empty($unmapped)) {
            $groups['Lainnya'] = array_values(array_map(
                fn($p) => ['name' => $p, 'label' => ucwords(str_replace('_', ' ', $p))],
                $unmapped
            ));
        }

        return $groups;
    }
}
