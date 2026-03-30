<?php

namespace App\Livewire\Traits;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Cuaca;
use App\Models\Galangan;
use App\Models\JenisKapal;
use App\Models\Kelembaban;
use App\Models\Laporan;
use App\Models\Notification;
use App\Models\SystemConfiguration;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

trait HasMenuItems
{
    public function getMenuItems(): array
    {
        $user = Auth::user();

        // Cache per request — Sidebar and Navigation both call this, avoid double DB hit
        static $cache = [];
        $cacheKey = 'menu_' . $user->id;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        // All checks go through Gate → policies (respects Gate::before super-admin bypass)
        $perms = [
            'dashboard_view'     => Gate::allows('viewStats'),
            'companies_view'     => Gate::allows('viewAny', Company::class),
            'jenis_kapal_view'   => Gate::allows('viewAny', JenisKapal::class),
            'galangan_view'      => Gate::allows('viewAny', Galangan::class),
            'kelembaban_view'    => Gate::allows('viewAny', Kelembaban::class),
            'cuaca_view'         => Gate::allows('viewAny', Cuaca::class),
            'notifications_view' => Gate::allows('viewAny', Notification::class),
            'notifications_send' => Gate::allows('send', Notification::class),
            'chat_view'          => Gate::allows('viewAny', Chat::class),
            'laporan_view'       => Gate::allows('viewAny', Laporan::class),
            'configuration_view' => Gate::allows('viewAny', SystemConfiguration::class),
            'users_view'         => Gate::allows('viewAny', User::class),
            'roles_view'         => Gate::allows('viewAny', Role::class),
        ];

        $req   = request();
        $items = [];

        // Dashboard — all roles
        $items[] = [
            'name'   => 'Dashboard',
            'route'  => 'dashboard',
            'icon'   => 'home',
            'active' => $req->routeIs('dashboard'),
        ];

        // Master Data
        $masterDataChildren = [];
        if ($perms['companies_view']) {
            $masterDataChildren[] = [
                'name'   => 'Perusahaan',
                'route'  => 'master-data.perusahaan',
                'active' => $req->routeIs('master-data.perusahaan'),
            ];
        }
        if ($perms['jenis_kapal_view']) {
            $masterDataChildren[] = [
                'name'   => 'Jenis Kapal',
                'route'  => 'master-data.jenis-kapal',
                'active' => $req->routeIs('master-data.jenis-kapal'),
            ];
        }
        if ($perms['galangan_view']) {
            $masterDataChildren[] = [
                'name'   => 'Galangan',
                'route'  => 'master-data.galangan',
                'active' => $req->routeIs('master-data.galangan'),
            ];
        }
        if ($perms['kelembaban_view']) {
            $masterDataChildren[] = [
                'name'   => 'Kelembaban',
                'route'  => 'master-data.kelembaban',
                'active' => $req->routeIs('master-data.kelembaban'),
            ];
        }
        if ($perms['cuaca_view']) {
            $masterDataChildren[] = [
                'name'   => 'Cuaca',
                'route'  => 'master-data.cuaca',
                'active' => $req->routeIs('master-data.cuaca'),
            ];
        }
        if (!empty($masterDataChildren)) {
            $items[] = [
                'name'     => 'Master Data',
                'icon'     => 'database',
                'active'   => $req->routeIs('master-data.*'),
                'children' => $masterDataChildren,
            ];
        }

        // Notifikasi
        $canView = $perms['notifications_view'];
        $canSend = $perms['notifications_send'];

        if ($canView || $canSend) {
            $notifChildren = [];
            if ($canView) {
                $notifChildren[] = [
                    'name'   => 'Kotak Masuk',
                    'route'  => 'notifications.index',
                    'active' => $req->routeIs('notifications.index'),
                ];
            }
            if ($canSend) {
                $notifChildren[] = [
                    'name'   => 'Kirim Notifikasi',
                    'route'  => 'notifications.send',
                    'active' => $req->routeIs('notifications.send'),
                ];
            }

            if ($canView && $canSend) {
                $items[] = [
                    'name'     => 'Notifikasi',
                    'icon'     => 'bell',
                    'active'   => $req->routeIs('notifications.*'),
                    'children' => $notifChildren,
                ];
            } else {
                $items[] = [
                    'name'   => 'Notifikasi',
                    'route'  => $canView ? 'notifications.index' : 'notifications.send',
                    'icon'   => 'bell',
                    'active' => $req->routeIs('notifications.*'),
                ];
            }
        }

        // Chat
        if ($perms['chat_view']) {
            $items[] = [
                'name'   => 'Chat',
                'route'  => 'chat.index',
                'icon'   => 'chat',
                'active' => $req->routeIs('chat.*'),
            ];
        }

        // Manajemen Laporan
        if ($perms['laporan_view']) {
            $items[] = [
                'name'   => 'Manajemen Laporan',
                'route'  => 'laporan.index',
                'icon'   => 'document-report',
                'active' => $req->routeIs('laporan.*'),
            ];
        }

        // Pengaturan
        $settingsChildren = [];
        if ($perms['configuration_view']) {
            $settingsChildren[] = [
                'name'   => 'Konfigurasi System',
                'route'  => 'settings.system',
                'active' => $req->routeIs('settings.system'),
            ];
        }
        if ($perms['users_view']) {
            $settingsChildren[] = [
                'name'   => 'Manajemen User',
                'route'  => 'settings.users',
                'active' => $req->routeIs('settings.users'),
            ];
        }
        if ($perms['roles_view']) {
            $settingsChildren[] = [
                'name'   => 'Roles & Permissions',
                'route'  => 'settings.roles',
                'active' => $req->routeIs('settings.roles'),
            ];
        }
        if (!empty($settingsChildren)) {
            $items[] = [
                'name'     => 'Pengaturan',
                'icon'     => 'cog',
                'active'   => $req->routeIs('settings.*'),
                'children' => $settingsChildren,
            ];
        }

        $result = array_filter($items);
        $cache[$cacheKey] = $result;

        return $result;
    }
}
