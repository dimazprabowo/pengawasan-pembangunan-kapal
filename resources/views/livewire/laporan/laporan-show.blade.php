<div
    @if($laporan->isDocProcessing())
        wire:poll.4s="refreshDocStatus"
    @endif
>
    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('laporan.index') }}" wire:navigate
                    x-data="{ loading: false }" x-on:click="loading = true"
                    x-bind:class="loading ? 'opacity-50 pointer-events-none' : ''"
                    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
                    <svg x-show="!loading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <svg x-show="loading" x-cloak class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </a>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Detail Laporan {{ $tipeEnum->label() }}</h2>
            </div>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Informasi lengkap laporan</p>
        </div>
        <div class="flex items-center gap-2">
            @can('laporan_update')
                <a href="{{ route('laporan.edit', $laporan) }}" wire:navigate
                    x-data="{ loading: false }" x-on:click="loading = true"
                    x-bind:class="loading ? 'opacity-75 pointer-events-none' : ''"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                    <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-show="!loading">Edit</span>
                    <span x-show="loading" x-cloak>Memuat...</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- Jenis Kapal Info Card --}}
    @if($laporan->jenisKapal)
        <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex items-center gap-2 mb-2">
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-sm font-medium text-blue-800 dark:text-blue-300">Informasi Kapal</span>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-sm text-blue-700 dark:text-blue-300">
                <div class="flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="font-medium">{{ $laporan->jenisKapal->nama }}</span>
                </div>
                @if($laporan->jenisKapal->company)
                    <span class="text-blue-600 dark:text-blue-400">•</span>
                    <span>{{ $laporan->jenisKapal->company->name }}</span>
                @endif
                @if($laporan->jenisKapal->galangan)
                    <span class="text-blue-600 dark:text-blue-400">•</span>
                    <span>{{ $laporan->jenisKapal->galangan->nama }}</span>
                @endif
            </div>
        </div>
    @endif

    {{-- Word Document Card (Harian only) --}}
    @if($laporan->tipe->value === 'harian')
        @can('laporan_download')
        <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-5 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    {{-- Word icon --}}
                    <svg class="w-5 h-5 text-blue-700 dark:text-blue-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM8.5 17l1.5-5 1.5 5 1.5-4.5 1 3.5h1l-2-6-1.5 4.5L10 10l-2 7h1z"/>
                    </svg>
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Dokumen Word Laporan Harian</h3>
                </div>

                {{-- Status Badge --}}
                @if($laporan->isDocProcessing())
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                        <svg class="animate-spin w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ $laporan->doc_status === 'pending' ? 'Menunggu antrian...' : 'Sedang diproses...' }}
                    </span>
                @elseif($laporan->isDocCompleted())
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Siap diunduh
                    </span>
                @elseif($laporan->isDocFailed())
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Gagal
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                        Belum digenerate
                    </span>
                @endif
            </div>

            <div class="p-5">
                @if($laporan->isDocFailed())
                    <div class="mb-4 flex items-start gap-2 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                        <svg class="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm text-red-700 dark:text-red-400">{{ $laporan->doc_error ?? 'Terjadi kesalahan saat generate dokumen.' }}</p>
                    </div>
                @endif

                @if($laporan->isDocCompleted())
                    <div class="mb-4 flex flex-col sm:flex-row sm:items-center gap-2 p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg">
                        <svg class="w-8 h-8 text-blue-700 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-emerald-800 dark:text-emerald-300 truncate">{{ $laporan->doc_name }}</p>
                            @if($laporan->doc_generated_at)
                                <p class="text-xs text-emerald-600 dark:text-emerald-400">
                                    Digenerate: {{ $laporan->doc_generated_at->translatedFormat('d F Y, H:i') }}
                                </p>
                            @endif
                        </div>
                    </div>
                @elseif(!$laporan->isDocProcessing())
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Generate dokumen Word (.docx) berdasarkan data laporan ini. Proses dilakukan di background — Anda dapat meninggalkan halaman ini dan kembali nanti.
                    </p>
                @endif

                <div class="flex items-center gap-2 flex-wrap">
                    {{-- Generate / Regenerate button --}}
                    @if(!$laporan->isDocProcessing())
                        <button wire:click="generateWord"
                            wire:loading.attr="disabled"
                            wire:target="generateWord"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-60
                                {{ $laporan->isDocCompleted()
                                    ? 'bg-amber-500 hover:bg-amber-600 text-white focus:ring-amber-500'
                                    : 'bg-indigo-600 hover:bg-indigo-700 text-white focus:ring-indigo-500' }}">
                            <svg wire:loading.class="hidden" wire:target="generateWord" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                @if($laporan->isDocCompleted())
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                @else
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                @endif
                            </svg>
                            <svg wire:loading wire:target="generateWord" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span wire:loading.remove wire:target="generateWord">
                                {{ $laporan->isDocCompleted() ? 'Generate Ulang' : ($laporan->isDocFailed() ? 'Coba Generate Ulang' : 'Generate Dokumen Word') }}
                            </span>
                            <span wire:loading wire:target="generateWord">Memproses...</span>
                        </button>
                    @else
                        <button disabled
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 cursor-not-allowed opacity-75">
                            <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Sedang Diproses...
                        </button>
                    @endif

                    {{-- Download button --}}
                    @if($laporan->isDocCompleted() && $laporan->hasDoc())
                        <a href="{{ route('laporan.download-word', $laporan) }}"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Download .docx
                        </a>
                    @endif
                </div>

                {{-- Queue status hint --}}
                @if($laporan->isDocProcessing() && !$queueStatus['active'])
                    <div class="mt-3 flex items-center gap-2 text-xs text-amber-600 dark:text-amber-400">
                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Queue worker tidak aktif. Jalankan: <code class="font-mono bg-amber-100 dark:bg-amber-900/30 px-1 rounded">php artisan queue:listen</code>
                    </div>
                @endif
            </div>
        </div>
        @endcan
    @endif

    {{-- Detail Card --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
        {{-- Card Header --}}
        <div class="px-5 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Informasi Laporan</h3>
                @php
                    $colorMap = [
                        'harian' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
                        'mingguan' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
                        'bulanan' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
                    ];
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $colorMap[$laporan->tipe->value] ?? '' }}">
                    {{ $laporan->tipe->label() }}
                </span>
            </div>
        </div>

        {{-- Card Body --}}
        <div class="p-5 space-y-5">
            {{-- Judul + Tanggal --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Judul</label>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $laporan->judul }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Tanggal Laporan</label>
                    <p class="text-sm text-gray-900 dark:text-white">{{ $laporan->tanggal_laporan->translatedFormat('d F Y') }}</p>
                </div>
            </div>

            {{-- Pembuat + Dibuat --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Pembuat</label>
                    <div class="flex items-center gap-2">
                        <div class="h-7 w-7 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold text-xs">
                            {{ substr($laporan->user->name ?? '-', 0, 1) }}
                        </div>
                        <span class="text-sm text-gray-900 dark:text-white">{{ $laporan->user->name ?? '-' }}</span>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Dibuat</label>
                    <p class="text-sm text-gray-900 dark:text-white">{{ $laporan->created_at->translatedFormat('d F Y H:i') }}</p>
                </div>
            </div>



            {{-- Weather Information for Harian --}}
            @if($laporan->tipe->value === 'harian' && ($laporan->suhu || $laporan->cuacaPagi || $laporan->kelembabanPagi || $laporan->cuacaSiang || $laporan->kelembabanSiang || $laporan->cuacaSore || $laporan->kelembabanSore))
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">A. Informasi Cuaca</label>
                    
                    {{-- Suhu --}}
                    @if($laporan->suhu)
                        <div class="mb-3">
                            <div class="inline-flex items-center gap-2 px-3 py-2 bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 rounded-lg">
                                <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                <span class="text-sm font-medium text-red-800 dark:text-red-300">Suhu: {{ number_format($laporan->suhu, 1) }}°C</span>
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        {{-- Pagi --}}
                        @if($laporan->cuacaPagi || $laporan->kelembabanPagi)
                            <x-laporan.weather-section
                                period="pagi"
                                :cuacaId="$laporan->cuaca_pagi_id"
                                :kelembabanId="$laporan->kelembaban_pagi_id"
                                :cuacaList="[['id' => $laporan->cuacaPagi?->id, 'nama' => $laporan->cuacaPagi?->nama]]"
                                :kelembabanList="[['id' => $laporan->kelembabanPagi?->id, 'nama' => $laporan->kelembabanPagi?->nama]]"
                                readonly
                            />
                        @endif

                        {{-- Siang --}}
                        @if($laporan->cuacaSiang || $laporan->kelembabanSiang)
                            <x-laporan.weather-section
                                period="siang"
                                :cuacaId="$laporan->cuaca_siang_id"
                                :kelembabanId="$laporan->kelembaban_siang_id"
                                :cuacaList="[['id' => $laporan->cuacaSiang?->id, 'nama' => $laporan->cuacaSiang?->nama]]"
                                :kelembabanList="[['id' => $laporan->kelembabanSiang?->id, 'nama' => $laporan->kelembabanSiang?->nama]]"
                                readonly
                            />
                        @endif

                        {{-- Sore --}}
                        @if($laporan->cuacaSore || $laporan->kelembabanSore)
                            <x-laporan.weather-section
                                period="sore"
                                :cuacaId="$laporan->cuaca_sore_id"
                                :kelembabanId="$laporan->kelembaban_sore_id"
                                :cuacaList="[['id' => $laporan->cuacaSore?->id, 'nama' => $laporan->cuacaSore?->nama]]"
                                :kelembabanList="[['id' => $laporan->kelembabanSore?->id, 'nama' => $laporan->kelembabanSore?->nama]]"
                                readonly
                            />
                        @endif
                    </div>
                </div>
            @endif

            {{-- Personel Section --}}
            @if($laporan->tipe->value === 'harian' && $laporan->personel->count() > 0)
                <x-laporan.personel-table
                    :rows="$laporan->personel->toArray()"
                    wireKeyPrefix="show-personel"
                    readonly
                />
            @endif

            {{-- Peralatan Section --}}
            @if($laporan->tipe->value === 'harian' && $laporan->peralatan->count() > 0)
                <x-laporan.peralatan-table
                    :rows="$laporan->peralatan->toArray()"
                    wireKeyPrefix="show-peralatan"
                    readonly
                />
            @endif

            {{-- Consumable Section --}}
            @if($laporan->tipe->value === 'harian' && $laporan->consumable->count() > 0)
                <x-laporan.consumable-table
                    :rows="$laporan->consumable->toArray()"
                    wireKeyPrefix="show-consumable"
                    readonly
                />
            @endif

            {{-- Aktivitas Section --}}
            @if($laporan->tipe->value === 'harian' && $laporan->aktivitas->count() > 0)
                <x-laporan.aktivitas-table
                    :rows="$laporan->aktivitas->toArray()"
                    wireKeyPrefix="show-aktivitas"
                    readonly
                />
            @endif

            {{-- Lampiran Section --}}
            @if($laporan->lampiran->count() > 0)
                <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
                        Lampiran ({{ $laporan->lampiran->count() }} file)
                    </label>
                    <div class="space-y-2">
                        @foreach($laporan->lampiran as $lampiranItem)
                            <div class="flex items-center gap-3 px-3 py-2.5 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg">
                                @if($lampiranItem->isFileProcessing())
                                    <svg class="animate-spin w-5 h-5 text-blue-500 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                @elseif($lampiranItem->isFileFailed())
                                    <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-700 dark:text-gray-300 truncate">{{ $lampiranItem->file_name }}</p>
                                    @if($lampiranItem->keterangan)
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $lampiranItem->keterangan }}</p>
                                    @endif
                                </div>
                                <span class="text-xs text-gray-400 flex-shrink-0">{{ number_format($lampiranItem->file_size / 1024, 0) }} KB</span>
                                @if($lampiranItem->hasFile() && $lampiranItem->isFileCompleted())
                                    <div class="flex items-center gap-1.5 flex-shrink-0">
                                        @if($lampiranItem->isPreviewable())
                                            @can('laporan_lampiran_preview')
                                                <button wire:click="openLampiranPreview({{ $lampiranItem->id }})" 
                                                    type="button"
                                                    wire:loading.attr="disabled"
                                                    wire:target="openLampiranPreview({{ $lampiranItem->id }})"
                                                    class="text-emerald-500 hover:text-emerald-700 p-1.5 rounded hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors disabled:opacity-50" 
                                                    title="Preview">
                                                    <svg wire:loading.class="hidden" wire:target="openLampiranPreview({{ $lampiranItem->id }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                    </svg>
                                                    <svg wire:loading wire:target="openLampiranPreview({{ $lampiranItem->id }})" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </button>
                                            @endcan
                                        @endif
                                        @can('laporan_lampiran_download')
                                            <a href="{{ route('laporan.lampiran.download', [$laporan, $lampiranItem]) }}" 
                                                class="text-blue-500 hover:text-blue-700 p-1.5 rounded hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors inline-block" 
                                                title="Download">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                                </svg>
                                            </a>
                                        @endcan
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Back button --}}
    <div class="flex items-center">
        <a href="{{ route('laporan.index') }}" wire:navigate
            x-data="{ loading: false }" x-on:click="loading = true"
            x-bind:class="loading ? 'opacity-75 pointer-events-none' : ''"
            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span x-show="!loading">Kembali ke Daftar</span>
            <span x-show="loading" x-cloak>Memuat...</span>
        </a>
    </div>

    {{-- Lampiran Preview Modal --}}
    <x-lampiran-preview-modal 
        :show="$showPreviewModal" 
        :lampiran="$this->previewLampiran" 
        :laporan="$laporan" 
    />
</div>
