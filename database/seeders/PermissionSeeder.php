<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Single source of truth untuk semua permissions.
     *
     * Idempotent — aman dijalankan berulang kali di production:
     *   php artisan db:seed --class=PermissionSeeder
     *
     * Konvensi penamaan:
     *   {entity}_{action}
     *   entity : dashboard, companies, configuration, users, roles, notifications, chat, laporan
     *   action : view, create, update, delete, export_excel, export_pdf, send
     *
     * Format ini memudahkan grouping otomatis di UI berdasarkan entity prefix.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Dashboard
            'dashboard_view',

            // Master Data — Perusahaan
            'companies_view',
            'companies_create',
            'companies_update',
            'companies_delete',
            'companies_export_excel',
            'companies_export_pdf',

            // Master Data — Jenis Kapal
            'jenis_kapal_view',
            'jenis_kapal_create',
            'jenis_kapal_update',
            'jenis_kapal_delete',
            'jenis_kapal_export_excel',
            'jenis_kapal_export_pdf',
            'jenis_kapal_upload_template',
            'jenis_kapal_download_template',

            // Master Data — Galangan
            'galangan_view',
            'galangan_create',
            'galangan_update',
            'galangan_delete',
            'galangan_export_excel',
            'galangan_export_pdf',

            // Master Data — Kelembaban
            'kelembaban_view',
            'kelembaban_create',
            'kelembaban_update',
            'kelembaban_delete',
            'kelembaban_export_excel',
            'kelembaban_export_pdf',

            // Master Data — Cuaca
            'cuaca_view',
            'cuaca_create',
            'cuaca_update',
            'cuaca_delete',
            'cuaca_export_excel',
            'cuaca_export_pdf',

            // Konfigurasi System
            'configuration_view',
            'configuration_update',
            'configuration_export_excel',
            'configuration_export_pdf',

            // Manajemen User
            'users_view',
            'users_create',
            'users_update',
            'users_delete',
            'users_export_excel',
            'users_export_pdf',

            // Roles & Permissions
            'roles_view',
            'roles_create',
            'roles_update',
            'roles_delete',
            'roles_export_excel',
            'roles_export_pdf',

            // Notifikasi
            'notifications_view',
            'notifications_send',

            // Chat / Pesan
            'chat_view',
            'chat_create',
            'chat_delete',

            // Manajemen Laporan
            'laporan_view',
            'laporan_show',
            'laporan_create',
            'laporan_update',
            'laporan_delete',
            'laporan_download',
            'laporan_lampiran_preview',
            'laporan_lampiran_download',
            'laporan_export_excel',
            'laporan_export_pdf',
            'laporan_view_all_jenis_kapal',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
