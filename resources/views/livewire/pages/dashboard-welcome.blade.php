{{-- Partial: Dashboard Welcome View (for users without dashboard_view permission) --}}
<div class="space-y-6">

    {{-- Welcome Hero --}}
    <div class="bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 rounded-xl shadow-lg p-8 text-white">
        <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
            {{-- Avatar --}}
            <div class="flex-shrink-0 w-20 h-20 rounded-full bg-white/20 flex items-center justify-center text-3xl font-bold uppercase ring-4 ring-white/30">
                {{ mb_substr($authUser->name, 0, 1) }}
            </div>
            {{-- Greeting --}}
            <div class="text-center md:text-left">
                <p class="text-blue-200 text-sm font-medium">Selamat datang kembali</p>
                <h2 class="mt-1 text-3xl font-bold">{{ $authUser->name }}</h2>
                <div class="mt-3 flex flex-wrap justify-center md:justify-start gap-2">
                    <span class="inline-flex items-center gap-1.5 bg-white/20 rounded-full px-3 py-1 text-xs font-semibold">
                        <span class="w-1.5 h-1.5 bg-green-400 rounded-full"></span>
                        {{ $authUserRole }}
                    </span>
                    @if($authUser->position)
                    <span class="inline-flex items-center gap-1.5 bg-white/20 rounded-full px-3 py-1 text-xs font-semibold">
                        {{ $authUser->position }}
                    </span>
                    @endif
                    @if($authUser->company)
                    <span class="inline-flex items-center gap-1.5 bg-white/20 rounded-full px-3 py-1 text-xs font-semibold">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                        </svg>
                        {{ $authUser->company->name }}
                    </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Profile Detail Card --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">Informasi Akun</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <div class="flex-shrink-0 p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Nama Lengkap</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $authUser->name }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <div class="flex-shrink-0 p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg">
                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Email</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $authUser->email }}</p>
                </div>
            </div>

            @if($authUser->phone)
            <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <div class="flex-shrink-0 p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg">
                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Telepon</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $authUser->phone }}</p>
                </div>
            </div>
            @endif

            @if($authUser->position)
            <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <div class="flex-shrink-0 p-2 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                    <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Jabatan</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $authUser->position }}</p>
                </div>
            </div>
            @endif

            @if($authUser->company)
            <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <div class="flex-shrink-0 p-2 bg-rose-100 dark:bg-rose-900/30 rounded-lg">
                    <svg class="w-4 h-4 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Perusahaan</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $authUser->company->name }}</p>
                </div>
            </div>
            @endif

            <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <div class="flex-shrink-0 p-2 bg-violet-100 dark:bg-violet-900/30 rounded-lg">
                    <svg class="w-4 h-4 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Role</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $authUserRole }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <div class="flex-shrink-0 p-2 bg-teal-100 dark:bg-teal-900/30 rounded-lg">
                    <svg class="w-4 h-4 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Bergabung</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $appJoinedAt->format('d M Y') }}</p>
                </div>
            </div>

        </div>
    </div>

    {{-- Accessible Menu Shortcuts --}}
    @php
        $shortcuts = [];
        if ($authUser->can('notifications_view')) {
            $shortcuts[] = ['route' => 'notifications.index', 'label' => 'Notifikasi', 'desc' => 'Lihat kotak masuk', 'color' => 'blue', 'icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'];
        }
        if ($authUser->can('chat_view')) {
            $shortcuts[] = ['route' => 'chat.index', 'label' => 'Chat', 'desc' => 'Buka percakapan', 'color' => 'emerald', 'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'];
        }
        if ($authUser->can('companies_view')) {
            $shortcuts[] = ['route' => 'master-data.perusahaan', 'label' => 'Perusahaan', 'desc' => 'Data master perusahaan', 'color' => 'amber', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'];
        }
    @endphp

    @if(!empty($shortcuts))
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">Menu Tersedia</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            @foreach($shortcuts as $shortcut)
            @php
                $colorMap = [
                    'blue'    => ['border' => 'hover:border-blue-400 dark:hover:border-blue-500',    'bg' => 'hover:bg-blue-50 dark:hover:bg-blue-900/10',    'icon_bg' => 'bg-blue-100 dark:bg-blue-900/30 group-hover:bg-blue-200 dark:group-hover:bg-blue-900/50',    'icon_text' => 'text-blue-600 dark:text-blue-400'],
                    'emerald' => ['border' => 'hover:border-emerald-400 dark:hover:border-emerald-500', 'bg' => 'hover:bg-emerald-50 dark:hover:bg-emerald-900/10', 'icon_bg' => 'bg-emerald-100 dark:bg-emerald-900/30 group-hover:bg-emerald-200 dark:group-hover:bg-emerald-900/50', 'icon_text' => 'text-emerald-600 dark:text-emerald-400'],
                    'amber'   => ['border' => 'hover:border-amber-400 dark:hover:border-amber-500',   'bg' => 'hover:bg-amber-50 dark:hover:bg-amber-900/10',   'icon_bg' => 'bg-amber-100 dark:bg-amber-900/30 group-hover:bg-amber-200 dark:group-hover:bg-amber-900/50',   'icon_text' => 'text-amber-600 dark:text-amber-400'],
                ];
                $c = $colorMap[$shortcut['color']];
            @endphp
            <a href="{{ route($shortcut['route']) }}" wire:navigate
               class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 dark:border-gray-700 {{ $c['border'] }} {{ $c['bg'] }} transition-all group">
                <div class="flex-shrink-0 p-2.5 {{ $c['icon_bg'] }} rounded-lg transition-colors">
                    <svg class="w-5 h-5 {{ $c['icon_text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $shortcut['icon'] }}"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $shortcut['label'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $shortcut['desc'] }}</p>
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Profile Edit CTA --}}
    <div class="flex justify-end">
        <a href="{{ route('profile') }}" wire:navigate
           class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Edit Profil
        </a>
    </div>

</div>
