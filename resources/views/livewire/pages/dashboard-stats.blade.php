{{-- Partial: Dashboard Statistik & Peta Galangan (permission dashboard_view) --}}
<div class="space-y-6">

    {{-- Hero --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-700 via-blue-800 to-slate-900 p-6 sm:p-8 text-white shadow-lg">
        <svg class="absolute -right-10 -bottom-10 w-72 h-72 text-white/5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M20 21c-1.39 0-2.78-.47-4-1.32-2.44 1.71-5.56 1.71-8 0C6.78 20.53 5.39 21 4 21H2v2h2c1.38 0 2.74-.35 4-.99a8.752 8.752 0 008 0c1.26.65 2.62.99 4 .99h2v-2h-2zM3.95 19H4c1.6 0 3.02-.88 4-2 .98 1.12 2.4 2 4 2s3.02-.88 4-2c.98 1.12 2.4 2 4 2h.05l1.89-6.68c.08-.26.06-.54-.06-.78s-.34-.42-.6-.5L20 10.62V6c0-1.1-.9-2-2-2h-3V1H9v3H6c-1.1 0-2 .9-2 2v4.62l-1.29.42a1.007 1.007 0 00-.66 1.28L3.95 19zM6 6h12v3.97L12 8 6 9.97V6z"/>
        </svg>
        <div class="relative flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
            <div>
                <p class="text-sm text-blue-200">{{ now()->translatedFormat('l, d F Y') }}</p>
                <h2 class="mt-1 text-2xl sm:text-3xl font-bold">{{ $greeting }}, {{ $authUser->name }}!</h2>
                <p class="mt-2 text-sm text-blue-100 max-w-xl">
                    Pantau sebaran galangan, progres pembangunan kapal, dan temuan NC dalam satu layar.
                </p>
                <span class="mt-3 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>{{ $authUserRole }}
                </span>
            </div>
            <div class="grid grid-cols-3 gap-2 sm:gap-3 text-center">
                <div class="rounded-xl bg-white/10 px-3 sm:px-4 py-2.5 backdrop-blur">
                    <p class="text-lg sm:text-xl font-bold">{{ number_format($totalUsers) }}</p>
                    <p class="text-[11px] text-blue-200">Pengguna</p>
                </div>
                <div class="rounded-xl bg-white/10 px-3 sm:px-4 py-2.5 backdrop-blur">
                    <p class="text-lg sm:text-xl font-bold">{{ number_format($totalCompanies) }}</p>
                    <p class="text-[11px] text-blue-200">Perusahaan</p>
                </div>
                <div class="rounded-xl bg-white/10 px-3 sm:px-4 py-2.5 backdrop-blur">
                    <p class="text-lg sm:text-xl font-bold">{{ number_format($totalRoles) }}</p>
                    <p class="text-[11px] text-blue-200">Role</p>
                </div>
            </div>
        </div>
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        @php
            $kpis = [
                [
                    'label' => 'Galangan Aktif', 'value' => $summary['galangan'], 'tone' => 'blue',
                    'meta' => $summary['galangan_berlokasi'].' sudah ditandai di peta',
                    'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
                ],
                [
                    'label' => 'Kapal Dikerjakan', 'value' => $summary['kapal'], 'tone' => 'indigo',
                    'meta' => $summary['kontrak_segera_berakhir'].' kontrak berakhir ≤ 30 hari',
                    'icon' => 'M3 17l2 4h14l2-4M5 17l1-6h12l1 6M12 3v8m-4-4h8',
                ],
                [
                    'label' => 'Laporan Harian (7 hari)', 'value' => $summary['laporan_7_hari'], 'tone' => 'emerald',
                    'meta' => $summary['laporan_hari_ini'].' laporan masuk hari ini',
                    'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
                ],
                [
                    'label' => 'NC Open', 'value' => $summary['nc_open'], 'tone' => $summary['nc_open'] > 0 ? 'red' : 'emerald',
                    'meta' => $summary['nc_closed_30_hari'].' NC ditutup 30 hari terakhir',
                    'icon' => 'M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z',
                ],
            ];
            $toneClass = [
                'blue' => 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
                'indigo' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400',
                'emerald' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400',
                'red' => 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400',
            ];
        @endphp
        @foreach ($kpis as $kpi)
            <div class="group bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 hover:shadow-md hover:-translate-y-0.5 transition-all">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($kpi['value']) }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate">{{ $kpi['meta'] }}</p>
                    </div>
                    <div class="p-3 rounded-xl {{ $toneClass[$kpi['tone']] }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kpi['icon'] }}"/></svg>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Peta sebaran galangan + panel galangan --}}
    <div wire:ignore.self x-data="galanganMap({ markers: @js($markers), selected: $wire.entangle('galanganKode') })"
         class="grid grid-cols-1 xl:grid-cols-12 gap-6">

        <div wire:ignore class="xl:col-span-8">
            <div :class="fullscreen ? 'fixed inset-0 z-50 rounded-none' : 'relative rounded-xl'"
                 class="bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col"
                 @keydown.escape.window="fullscreen && toggleFullscreen()">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Peta Sebaran Galangan</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Klik penanda untuk melihat kapal yang sedang dikerjakan.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden text-xs font-medium">
                            <button type="button" @click="mode = 'peta'" :class="mode === 'peta' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600'" class="px-3 py-1.5 transition-colors">Peta</button>
                            <button type="button" @click="mode = 'satellite'" :class="mode === 'satellite' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600'" class="px-3 py-1.5 transition-colors">Satelit</button>
                        </div>
                        <button type="button" @click="fitAll()" title="Tampilkan semua galangan" aria-label="Tampilkan semua galangan"
                            class="p-2 rounded-lg border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        </button>
                        <button type="button" @click="toggleFullscreen()" :title="fullscreen ? 'Keluar layar penuh (Esc)' : 'Layar penuh'" aria-label="Layar penuh"
                            class="p-2 rounded-lg border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <svg x-show="!fullscreen" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/></svg>
                            <svg x-show="fullscreen" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>

                <div class="relative isolate flex-1">
                    <div x-ref="map" :class="fullscreen ? 'h-full' : 'h-[420px] sm:h-[520px]'" class="w-full z-0"></div>

                    <div class="absolute left-3 top-3 z-[500] rounded-lg bg-white/95 dark:bg-gray-800/95 shadow border border-gray-200 dark:border-gray-700 px-3 py-2 text-[11px] text-gray-600 dark:text-gray-300 space-y-1">
                        <p class="font-semibold text-gray-800 dark:text-gray-100">Legenda</p>
                        <p class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-blue-700"></span>Ada kapal dikerjakan</p>
                        <p class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-slate-500"></span>Belum ada kapal</p>
                        <p class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-red-600"></span>Ada NC open</p>
                        <p class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-amber-500"></span>Terpilih</p>
                    </div>

                    <template x-if="!located.length">
                        <div class="absolute inset-0 z-[500] flex items-center justify-center bg-white/70 dark:bg-gray-900/70">
                            <div class="text-center px-6">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <p class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-200">Belum ada galangan yang ditandai di peta</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Atur titik lokasi melalui Master Data &rsaquo; Galangan.</p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Panel kanan: daftar galangan (client) / detail galangan (server) --}}
        <div class="xl:col-span-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 h-full flex flex-col xl:max-h-[598px]">

                <div wire:ignore x-show="!selected" class="flex flex-col min-h-0 flex-1">
                    <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Daftar Galangan</h3>
                        <div class="relative mt-3">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" x-model="search" placeholder="Cari galangan, kota, provinsi..."
                                class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                    <ul class="flex-1 overflow-y-auto custom-scrollbar divide-y divide-gray-100 dark:divide-gray-700 max-h-[420px] xl:max-h-none">
                        <template x-for="m in filtered" :key="m.kode">
                            <li>
                                <button type="button" @click="select(m.kode)"
                                    class="w-full text-left px-5 py-3 flex items-center gap-3 hover:bg-blue-50/60 dark:hover:bg-blue-900/10 transition-colors">
                                    <span class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white"
                                          :class="m.kapal > 0 ? 'bg-gradient-to-br from-blue-600 to-blue-900' : 'bg-gradient-to-br from-slate-400 to-slate-600'">
                                        <span x-text="m.kapal"></span>
                                        <span x-show="m.nc > 0" class="absolute -top-0.5 -right-0.5 h-3 w-3 rounded-full bg-red-600 ring-2 ring-white dark:ring-gray-800"></span>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-medium text-gray-900 dark:text-white truncate" x-text="m.nama"></span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400 truncate" x-text="[m.kota, m.provinsi].filter(Boolean).join(', ') || m.kode"></span>
                                    </span>
                                    <span x-show="m.lat === null" class="shrink-0 rounded-full bg-amber-100 dark:bg-amber-900/30 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300">Tanpa lokasi</span>
                                    <span x-show="m.lat !== null && m.nc > 0" class="shrink-0 rounded-full bg-red-100 dark:bg-red-900/30 px-2 py-0.5 text-[10px] font-semibold text-red-700 dark:text-red-300" x-text="m.nc + ' NC'"></span>
                                </button>
                            </li>
                        </template>
                        <li x-show="!filtered.length" class="px-5 py-12 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Galangan tidak ditemukan.</p>
                        </li>
                    </ul>
                    @can('galangan_update')
                        <div class="px-5 py-3 border-t border-gray-200 dark:border-gray-700 text-xs">
                            <a href="{{ route('master-data.galangan') }}" wire:navigate
                               x-data="{ nav: false }" x-on:click="nav = true" x-on:livewire:navigated.window="nav = false"
                               class="inline-flex items-center gap-1 font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                Kelola lokasi galangan
                                <span x-show="!nav">&rarr;</span>
                                <svg x-show="nav" x-cloak class="w-3 h-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            </a>
                        </div>
                    @endcan
                </div>

                <div x-show="selected" x-cloak class="relative flex flex-col min-h-0 flex-1">
                    <div wire:loading.flex wire:target="selectGalangan"
                         class="absolute inset-0 z-10 items-center justify-center rounded-xl bg-white/80 dark:bg-gray-800/80">
                        <svg class="animate-spin w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    </div>

                    @if ($detail)
                        @php
                            $g = $detail['galangan'];
                            $ncOpenGalangan = $detail['kapal']->sum('nc_open_count');
                        @endphp
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                            <button type="button" @click="clear()" class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                <svg wire:loading.class="hidden" wire:target="selectGalangan" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                <svg wire:loading wire:target="selectGalangan" class="w-3.5 h-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Semua galangan
                            </button>
                            <div class="mt-2 flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $g->nama }}</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ collect([$g->kota, $g->provinsi])->filter()->join(', ') ?: '-' }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-[11px] font-semibold text-gray-600 dark:text-gray-300">{{ $g->kode }}</span>
                            </div>
                            @if ($g->alamat)
                                <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">{{ $g->alamat }}</p>
                            @endif
                            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                @if ($g->hasLokasi())
                                    <a href="https://www.google.com/maps/dir/?api=1&destination={{ $g->latitude }},{{ $g->longitude }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-1 font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                                        Petunjuk arah
                                    </a>
                                @endif
                                @if ($g->pic_name)
                                    <span class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-300">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        {{ $g->pic_name }}
                                    </span>
                                @endif
                                @if ($g->telepon)
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $g->telepon) }}" class="inline-flex items-center gap-1 font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                        {{ $g->telepon }}
                                    </a>
                                @endif
                            </div>
                            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                                <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 py-2">
                                    <p class="text-lg font-bold text-blue-700 dark:text-blue-300">{{ $detail['kapal']->count() }}</p>
                                    <p class="text-[11px] text-blue-700/80 dark:text-blue-300/80">Kapal</p>
                                </div>
                                <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/20 py-2">
                                    <p class="text-lg font-bold text-emerald-700 dark:text-emerald-300">{{ number_format($detail['kapal']->sum('laporan_harian_count')) }}</p>
                                    <p class="text-[11px] text-emerald-700/80 dark:text-emerald-300/80">Laporan Harian</p>
                                </div>
                                <div class="rounded-lg {{ $ncOpenGalangan > 0 ? 'bg-red-50 dark:bg-red-900/20' : 'bg-gray-50 dark:bg-gray-700/50' }} py-2">
                                    <p class="text-lg font-bold {{ $ncOpenGalangan > 0 ? 'text-red-700 dark:text-red-300' : 'text-gray-700 dark:text-gray-200' }}">{{ $ncOpenGalangan }}</p>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400">NC Open</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto custom-scrollbar p-4 space-y-3 max-h-[520px] xl:max-h-none">
                            @forelse ($detail['kapal'] as $kapal)
                                @php
                                    $sisaHari = $kapal->sisaHariKontrak();
                                @endphp
                                <div wire:key="dash-kapal-{{ $kapal->id }}" class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 hover:border-blue-300 dark:hover:border-blue-700 hover:shadow-sm transition-all">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $kapal->nama }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $kapal->company?->name ?? '-' }}{{ $kapal->nomor_kontrak ? ' · '.$kapal->nomor_kontrak : '' }}</p>
                                        </div>
                                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $kapal->progress_status->badgeClass() }}">{{ $kapal->progress_status->label() }}</span>
                                    </div>

                                    <div class="mt-3">
                                        <div class="flex justify-between text-[11px] text-gray-500 dark:text-gray-400 mb-1">
                                            <span>Aktual <strong class="text-gray-800 dark:text-gray-100">{{ $kapal->progress_aktual !== null ? number_format($kapal->progress_aktual, 1) . '%' : '-' }}</strong></span>
                                            <span>Rencana {{ $kapal->progress_rencana !== null ? number_format($kapal->progress_rencana, 1) . '%' : '-' }}</span>
                                        </div>
                                        <div class="relative h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                            <div class="h-full rounded-full {{ $kapal->progress_status->barClass() }}" style="width: {{ min(100, max(0, $kapal->progress_aktual ?? 0)) }}%"></div>
                                            @if ($kapal->progress_rencana !== null)
                                                <div class="absolute top-0 h-full w-0.5 bg-gray-800/70 dark:bg-white/70" style="left: {{ min(100, max(0, $kapal->progress_rencana)) }}%" title="Rencana"></div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="mt-3 grid grid-cols-2 gap-2 text-[11px] text-gray-600 dark:text-gray-300">
                                        <div class="flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            @if ($sisaHari === null)
                                                Kontrak belum diisi
                                            @elseif ($sisaHari >= 0)
                                                <span class="{{ $sisaHari <= 30 ? 'font-semibold text-amber-600 dark:text-amber-400' : '' }}">Sisa {{ $sisaHari }} hari</span>
                                            @else
                                                <span class="font-semibold text-red-600 dark:text-red-400">Lewat {{ abs($sisaHari) }} hari</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            {{ $kapal->laporan_harian_max_tanggal_laporan ? \Illuminate\Support\Carbon::parse($kapal->laporan_harian_max_tanggal_laporan)->translatedFormat('d M Y') : 'Belum ada laporan' }}
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            {{ $kapal->laporan_harian_count }} H · {{ $kapal->laporan_mingguan_count }} M · {{ $kapal->laporan_bulanan_count }} B
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 {{ $kapal->nc_open_count ? 'text-red-500' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                            <span class="{{ $kapal->nc_open_count ? 'font-semibold text-red-600 dark:text-red-400' : '' }}">{{ $kapal->nc_open_count }} NC open</span>
                                        </div>
                                    </div>

                                    @can('laporan_view')
                                        <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700 flex flex-wrap items-center gap-2">
                                            <x-loading-button wire:click="openLaporan({{ $kapal->id }}, 'harian')" target="openLaporan({{ $kapal->id }}, 'harian')" variant="secondary" size="xs" title="Buka laporan harian">Harian</x-loading-button>
                                            <x-loading-button wire:click="openLaporan({{ $kapal->id }}, 'mingguan')" target="openLaporan({{ $kapal->id }}, 'mingguan')" variant="secondary" size="xs" title="Buka laporan mingguan">Mingguan</x-loading-button>
                                            <x-loading-button wire:click="openLaporan({{ $kapal->id }}, 'bulanan')" target="openLaporan({{ $kapal->id }}, 'bulanan')" variant="secondary" size="xs" title="Buka laporan bulanan">Bulanan</x-loading-button>
                                            @can('proyek_data_view')
                                                <a href="{{ route('master-data.data-kapal.proyek', $kapal) }}" wire:navigate
                                                   x-data="{ nav: false }" x-on:click="nav = true" x-on:livewire:navigated.window="nav = false"
                                                   class="ml-auto inline-flex items-center gap-1 text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                                    Data Proyek
                                                    <span x-show="!nav">&rarr;</span>
                                                    <svg x-show="nav" x-cloak class="w-3 h-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                </a>
                                            @endcan
                                        </div>
                                    @endcan
                                </div>
                            @empty
                                <div class="py-12 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Belum ada kapal yang dikerjakan di galangan ini.</p>
                                </div>
                            @endforelse
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Aktivitas terbaru + aksi cepat --}}
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
        <div class="xl:col-span-8 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Laporan Harian Terbaru</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Aktivitas pelaporan terkini dari seluruh galangan.</p>
            </div>
            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($recentLaporan as $laporan)
                    <li wire:key="recent-{{ $laporan->id }}" class="px-5 py-3 flex items-center gap-4 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                        <div class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300">
                            <span class="text-sm font-bold leading-none">{{ $laporan->tanggal_laporan->format('d') }}</span>
                            <span class="text-[10px] uppercase">{{ $laporan->tanggal_laporan->translatedFormat('M') }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $laporan->dataKapal?->nama ?? '-' }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                {{ $laporan->dataKapal?->galangan?->nama ?? 'Tanpa galangan' }} · oleh {{ $laporan->user?->name ?? '-' }}
                            </p>
                        </div>
                        @can('laporan_show')
                            <a href="{{ route('laporan-harian.show', [$laporan->dataKapal, $laporan]) }}" wire:navigate
                               x-data="{ nav: false }" x-on:click="nav = true" x-on:livewire:navigated.window="nav = false"
                               class="shrink-0 inline-flex items-center gap-1 text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                Lihat
                                <span x-show="!nav">&rarr;</span>
                                <svg x-show="nav" x-cloak class="w-3 h-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            </a>
                        @endcan
                    </li>
                @empty
                    <li class="px-5 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Belum ada laporan harian.</p>
                    </li>
                @endforelse
            </ul>
        </div>

        <div class="xl:col-span-4 space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">Aksi Cepat</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-3">
                    @php
                        $quickActions = [
                            ['can' => 'laporan_view', 'route' => 'laporan.index', 'title' => 'Manajemen Laporan', 'desc' => 'Laporan harian, mingguan, bulanan', 'tone' => 'blue', 'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                            ['can' => 'data_kapal_view', 'route' => 'master-data.data-kapal', 'title' => 'Data Kapal', 'desc' => 'Kontrak & Kurva-S', 'tone' => 'indigo', 'icon' => 'M3 17l2 4h14l2-4M5 17l1-6h12l1 6M12 3v8m-4-4h8'],
                            ['can' => 'galangan_view', 'route' => 'master-data.galangan', 'title' => 'Galangan', 'desc' => 'Lokasi & kontak galangan', 'tone' => 'emerald', 'icon' => 'M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z'],
                            ['can' => 'users_view', 'route' => 'settings.users', 'title' => 'Manajemen User', 'desc' => 'Kelola akun pengguna', 'tone' => 'violet', 'icon' => 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z'],
                            ['can' => 'roles_view', 'route' => 'settings.roles', 'title' => 'Roles & Permissions', 'desc' => 'Kelola hak akses', 'tone' => 'amber', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
                            ['can' => 'configuration_view', 'route' => 'settings.system', 'title' => 'Konfigurasi System', 'desc' => 'Pengaturan aplikasi', 'tone' => 'slate', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z'],
                        ];
                        $quickTone = [
                            'blue' => 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
                            'indigo' => 'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400',
                            'emerald' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400',
                            'violet' => 'bg-violet-100 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400',
                            'amber' => 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400',
                            'slate' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                        ];
                    @endphp
                    @foreach ($quickActions as $action)
                        @can($action['can'])
                            <a href="{{ route($action['route']) }}" wire:navigate
                               x-data="{ nav: false }" x-on:click="nav = true" x-on:livewire:navigated.window="nav = false"
                               class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all group">
                                <span class="flex-shrink-0 p-2 rounded-lg {{ $quickTone[$action['tone']] }}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $action['icon'] }}"/></svg>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ $action['title'] }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400 truncate">{{ $action['desc'] }}</span>
                                </span>
                                <svg x-show="!nav" class="ml-auto w-4 h-4 text-gray-300 group-hover:text-blue-500 group-hover:translate-x-0.5 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                <svg x-show="nav" x-cloak class="ml-auto w-4 h-4 animate-spin text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            </a>
                        @endcan
                    @endforeach
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">Informasi System</h3>
                <dl class="grid grid-cols-2 gap-3 text-xs">
                    <div><dt class="text-gray-500 dark:text-gray-400">Framework</dt><dd class="font-medium text-gray-900 dark:text-white">Laravel {{ app()->version() }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Runtime</dt><dd class="font-medium text-gray-900 dark:text-white">PHP {{ phpversion() }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Environment</dt><dd class="font-medium text-gray-900 dark:text-white">{{ config('app.env') }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Timezone</dt><dd class="font-medium text-gray-900 dark:text-white">{{ config('app.timezone') }}</dd></div>
                </dl>
            </div>
        </div>
    </div>
</div>
