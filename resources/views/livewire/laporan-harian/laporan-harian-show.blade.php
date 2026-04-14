<div
    @if($laporan->isDocProcessing())
        wire:poll.4s="refreshDocStatus"
    @endif
>
    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('laporan-harian.index') }}" wire:navigate
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
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Detail Laporan Harian</h2>
            </div>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Informasi lengkap laporan</p>
        </div>
        <div class="flex items-center gap-2">
            @can('laporan_update')
                <a href="{{ route('laporan-harian.edit', $laporan) }}" wire:navigate
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

            <div class="p-6">
                {{-- Template Status Info --}}
                @if($laporan->jenisKapal)
                    @if($laporan->jenisKapal->hasTemplate('harian'))
                        <div class="mb-4 flex items-start gap-2 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                            <svg class="w-4 h-4 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-sm text-green-700 dark:text-green-400">
                                Template khusus tersedia untuk jenis kapal <strong>{{ $laporan->jenisKapal->nama }}</strong>. Dokumen akan digenerate menggunakan template khusus.
                            </p>
                        </div>
                    @else
                        <div class="mb-4 flex items-start gap-2 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                            <svg class="w-4 h-4 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <p class="text-sm text-yellow-700 dark:text-yellow-400">
                                Template khusus belum tersedia untuk jenis kapal <strong>{{ $laporan->jenisKapal->nama }}</strong>. Dokumen akan digenerate menggunakan template default. 
                                @can('jenis_kapal_upload_template')
                                    <a href="{{ route('master-data.jenis-kapal') }}" wire:navigate class="underline hover:text-yellow-800 dark:hover:text-yellow-300">Upload template di Master Jenis Kapal</a>
                                @endcan
                            </p>
                        </div>
                    @endif
                @endif

                @if($laporan->isDocFailed())
                    <div class="mb-4 flex items-start gap-2 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                        <svg class="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm text-red-700 dark:text-red-400">{{ $laporan->doc_error ?? 'Terjadi kesalahan saat generate dokumen.' }}</p>
                    </div>
                @endif

                @if($laporan->isDocCompleted())
                    <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg">
                        <div class="flex items-center gap-3 min-w-0">
                            <svg class="w-8 h-8 text-blue-700 dark:text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                        <div class="flex items-center gap-2 flex-shrink-0">
                            @can('laporan_download')
                                <button wire:click="downloadWord"
                                    x-data="{ loading: false }"
                                    x-on:click="loading = true; setTimeout(() => loading = false, 2000)"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 shadow-sm">
                                    <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                    <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </button>
                            @endcan
                            @can('laporan_download')
                                <button wire:click="confirmDeleteDoc"
                                    wire:loading.attr="disabled"
                                    wire:target="confirmDeleteDoc,deleteDoc"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-lg bg-red-600 hover:bg-red-700 text-white transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 shadow-sm disabled:opacity-60">
                                    <svg wire:loading.class="hidden" wire:target="confirmDeleteDoc,deleteDoc" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    <svg wire:loading wire:target="confirmDeleteDoc,deleteDoc" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </button>
                            @endcan
                        </div>
                    </div>
                @elseif(!$laporan->isDocProcessing())
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Generate dokumen Word (.docx) berdasarkan data laporan ini. Proses dilakukan di background — Anda dapat meninggalkan halaman ini dan kembali nanti.
                    </p>
                @endif

                <div class="flex items-center gap-3 flex-wrap">
                    {{-- Generate / Regenerate button --}}
                    @if(!$laporan->isDocProcessing())
                        <button wire:click="confirmRegenerate"
                            wire:loading.attr="disabled"
                            wire:target="confirmRegenerate,generateWord"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm shadow-indigo-500/20 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-60">
                            <svg wire:loading.class="hidden" wire:target="confirmRegenerate,generateWord" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                @if($laporan->isDocCompleted())
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                @else
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                @endif
                            </svg>
                            <svg wire:loading wire:target="confirmRegenerate,generateWord" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span wire:loading.remove wire:target="confirmRegenerate,generateWord">
                                {{ $laporan->isDocCompleted() ? 'Generate Ulang' : ($laporan->isDocFailed() ? 'Coba Generate Ulang' : 'Generate Dokumen Word') }}
                            </span>
                            <span wire:loading wire:target="confirmRegenerate,generateWord">Memproses...</span>
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

                </div>

                {{-- Queue status hint --}}
                @if($laporan->isDocProcessing() && !$queueStatus['active'])
                    <div class="mt-3 flex items-center gap-2 text-xs text-amber-600 dark:text-amber-400">
                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Queue worker tidak aktif. Jalankan: <code class="font-mono bg-amber-100 dark:bg-amber-900/30 px-1 rounded">php artisan queue:work</code>
                    </div>
                @endif
            </div>
        </div>
        @endcan

    {{-- Detail Card --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
        {{-- Card Header --}}
        <div class="px-5 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Informasi Laporan</h3>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                    Harian
                </span>
            </div>
        </div>

        {{-- Card Body --}}
        <div class="p-5 space-y-5">
            {{-- Tanggal + Judul --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Tanggal Laporan</label>
                    <p class="text-sm text-gray-900 dark:text-white">{{ $laporan->tanggal_laporan->translatedFormat('d F Y') }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Judul</label>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $laporan->judul }}</p>
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
                @php
                    $hasWeatherData = $laporan->suhu || $laporan->cuacaPagi || $laporan->kelembabanPagi || $laporan->cuacaSiang || $laporan->kelembabanSiang || $laporan->cuacaSore || $laporan->kelembabanSore;
                @endphp
                <x-laporan.section-wrapper label="A. Informasi Cuaca" :isEmpty="!$hasWeatherData">
                    @if($hasWeatherData)
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
                    @else
                        <x-laporan.empty-state icon="weather" message="Informasi cuaca belum diisi" />
                    @endif
                </x-laporan.section-wrapper>

            {{-- Personel Section --}}
                <x-laporan.section-wrapper label="B. Personel" :isEmpty="$laporan->personel->count() === 0" :count="$laporan->personel->count()">
                    @if($laporan->personel->count() > 0)
                        <x-laporan.personel-table
                            :rows="$laporan->personel->toArray()"
                            wireKeyPrefix="show-personel"
                            readonly
                        />
                    @else
                        <x-laporan.empty-state icon="personel" message="Data personel belum diisi" />
                    @endif
                </x-laporan.section-wrapper>

            {{-- Peralatan Section --}}
                <x-laporan.section-wrapper label="C. Peralatan" :isEmpty="$laporan->peralatan->count() === 0" :count="$laporan->peralatan->count()">
                    @if($laporan->peralatan->count() > 0)
                        <x-laporan.peralatan-table
                            :rows="$laporan->peralatan->toArray()"
                            wireKeyPrefix="show-peralatan"
                            readonly
                        />
                    @else
                        <x-laporan.empty-state icon="peralatan" message="Data peralatan belum diisi" />
                    @endif
                </x-laporan.section-wrapper>

            {{-- Consumable Section --}}
                <x-laporan.section-wrapper label="D. Consumable" :isEmpty="$laporan->consumable->count() === 0" :count="$laporan->consumable->count()">
                    @if($laporan->consumable->count() > 0)
                        <x-laporan.consumable-table
                            :rows="$laporan->consumable->toArray()"
                            wireKeyPrefix="show-consumable"
                            readonly
                        />
                    @else
                        <x-laporan.empty-state icon="consumable" message="Data consumable belum diisi" />
                    @endif
                </x-laporan.section-wrapper>

            {{-- Aktivitas Section --}}
                <x-laporan.section-wrapper label="E. Aktivitas" :isEmpty="$laporan->aktivitas->count() === 0" :count="$laporan->aktivitas->count()">
                    @if($laporan->aktivitas->count() > 0)
                        <x-laporan.aktivitas-table
                            :rows="$laporan->aktivitas->toArray()"
                            wireKeyPrefix="show-aktivitas"
                            readonly
                        />
                    @else
                        <x-laporan.empty-state icon="aktivitas" message="Data aktivitas belum diisi" />
                    @endif
                </x-laporan.section-wrapper>

            {{-- Lampiran Section --}}
            <x-laporan.section-wrapper label="F. Lampiran" :isEmpty="$laporan->lampiran->count() === 0" :count="$laporan->lampiran->count()" countLabel="file">
                @if($laporan->lampiran->count() > 0)
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
                                            <button wire:click="downloadLampiran({{ $lampiranItem->id }})" type="button"
                                                wire:loading.attr="disabled"
                                                wire:target="downloadLampiran({{ $lampiranItem->id }})"
                                                class="text-blue-500 hover:text-blue-700 p-1.5 rounded hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors bg-transparent border-none cursor-pointer disabled:opacity-50" 
                                                title="Download">
                                                <svg wire:loading.class="hidden" wire:target="downloadLampiran({{ $lampiranItem->id }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                                </svg>
                                                <svg wire:loading wire:target="downloadLampiran({{ $lampiranItem->id }})" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </button>
                                        @endcan
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-laporan.empty-state icon="lampiran" message="Belum ada lampiran" />
                @endif
            </x-laporan.section-wrapper>
        </div>
    </div>

    {{-- Back button --}}
    <div class="flex items-center">
        <a href="{{ route('laporan-harian.index') }}" wire:navigate
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
        :imageUrl="$this->previewLampiranImageUrl"
    />

    {{-- Regenerate Confirmation Modal --}}
    @if($showRegenerateConfirm)
        <div
            x-data
            x-init="
                document.body.style.overflow = 'hidden';
                $nextTick(() => $el.querySelector('[data-modal-panel]').focus());
            "
            x-on:keydown.escape.window="$wire.cancelRegenerate()"
            x-on:remove="document.body.style.overflow = ''"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-regenerate-title"
        >
            {{-- Backdrop --}}
            <div
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                class="fixed inset-0 bg-gray-900/70 dark:bg-black/80 backdrop-blur-sm transition-opacity"
            ></div>

            {{-- Modal panel --}}
            <div
                data-modal-panel
                tabindex="-1"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                class="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-[0_25px_60px_-10px_rgba(0,0,0,0.6)] dark:shadow-[0_25px_60px_-10px_rgba(0,0,0,0.9)] border border-gray-200 dark:border-gray-600 overflow-hidden outline-none"
            >
                {{-- Top accent stripe --}}
                <div class="h-1 bg-gradient-to-r from-amber-400 via-amber-500 to-orange-500"></div>

                {{-- Body --}}
                <div class="px-6 pt-5 pb-4">
                    {{-- Icon + Title row --}}
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-11 h-11 rounded-full bg-amber-50 dark:bg-amber-900/20 ring-4 ring-amber-100 dark:ring-amber-900/30 flex items-center justify-center">
                            <svg class="w-5 h-5 text-amber-500 dark:text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <h3 id="modal-regenerate-title" class="text-base font-semibold text-gray-900 dark:text-white leading-snug">
                                Generate Ulang Dokumen Word?
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Tindakan ini akan menghapus dokumen yang sudah ada.
                            </p>
                        </div>
                        <button
                            wire:click="cancelRegenerate"
                            wire:loading.attr="disabled"
                            wire:target="cancelRegenerate,generateWord"
                            class="flex-shrink-0 -mt-0.5 p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors disabled:opacity-50"
                            aria-label="Tutup"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Warning detail box --}}
                    <div class="mt-4 rounded-xl border border-red-200 dark:border-red-700/70 bg-red-50 dark:bg-red-950/40 px-4 py-3.5">
                        <div class="flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-red-600 dark:text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/>
                            </svg>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-red-700 dark:text-red-400">File akan dihapus permanen</p>
                                <p class="mt-0.5 text-xs text-red-600 dark:text-red-400/90 font-mono truncate">{{ $laporan->doc_name }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="px-6 pb-5 pt-5 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end gap-3">
                    <button
                        wire:click="cancelRegenerate"
                        wire:loading.attr="disabled"
                        wire:target="cancelRegenerate,generateWord"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-400 dark:focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50"
                    >
                        <svg wire:loading.class="hidden" wire:target="cancelRegenerate" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <svg wire:loading wire:target="cancelRegenerate" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Batal
                    </button>
                    <button
                        wire:click="generateWord"
                        wire:loading.attr="disabled"
                        wire:target="generateWord"
                        class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm shadow-indigo-500/20 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50"
                    >
                        <svg wire:loading.remove wire:target="generateWord" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <svg wire:loading wire:target="generateWord" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="generateWord">Ya, Generate Ulang</span>
                        <span wire:loading wire:target="generateWord">Memproses...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Delete Document Confirmation Modal --}}
    @if($showDeleteDocConfirm)
        <div
            x-data
            x-init="
                document.body.style.overflow = 'hidden';
                $nextTick(() => $el.querySelector('[data-modal-panel]').focus());
            "
            x-on:keydown.escape.window="$wire.cancelDeleteDoc()"
            x-on:remove="document.body.style.overflow = ''"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-delete-doc-title"
        >
            {{-- Backdrop --}}
            <div
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                class="fixed inset-0 bg-gray-900/70 dark:bg-black/80 backdrop-blur-sm transition-opacity"
            ></div>

            {{-- Modal panel --}}
            <div
                data-modal-panel
                tabindex="-1"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                class="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-[0_25px_60px_-10px_rgba(0,0,0,0.6)] dark:shadow-[0_25px_60px_-10px_rgba(0,0,0,0.9)] border border-gray-200 dark:border-gray-600 overflow-hidden outline-none"
            >
                {{-- Top accent stripe --}}
                <div class="h-1 bg-gradient-to-r from-red-500 to-red-600"></div>

                {{-- Body --}}
                <div class="px-6 pt-5 pb-4">
                    {{-- Icon + Title row --}}
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-11 h-11 rounded-full bg-red-50 dark:bg-red-900/20 ring-4 ring-red-100 dark:ring-red-900/30 flex items-center justify-center">
                            <svg class="w-5 h-5 text-red-600 dark:text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <h3 id="modal-delete-doc-title" class="text-base font-semibold text-gray-900 dark:text-white leading-snug">
                                Hapus Dokumen Word?
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Dokumen yang sudah digenerate akan dihapus permanen.
                            </p>
                        </div>
                        <button
                            wire:click="cancelDeleteDoc"
                            wire:loading.attr="disabled"
                            wire:target="cancelDeleteDoc,deleteDoc"
                            class="flex-shrink-0 -mt-0.5 p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors disabled:opacity-50"
                            aria-label="Tutup"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Warning detail box --}}
                    <div class="mt-4 rounded-xl border border-red-200 dark:border-red-700/70 bg-red-50 dark:bg-red-950/40 px-4 py-3.5">
                        <div class="flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-red-600 dark:text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/>
                            </svg>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-red-700 dark:text-red-400">File akan dihapus permanen</p>
                                <p class="mt-0.5 text-xs text-red-600 dark:text-red-400/90 font-mono truncate">{{ $laporan->doc_name }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="px-6 pb-5 pt-5 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end gap-3">
                    <button
                        wire:click="cancelDeleteDoc"
                        wire:loading.attr="disabled"
                        wire:target="cancelDeleteDoc,deleteDoc"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-400 dark:focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50"
                    >
                        <svg wire:loading.class="hidden" wire:target="cancelDeleteDoc" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <svg wire:loading wire:target="cancelDeleteDoc" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Batal
                    </button>
                    <button
                        wire:click="deleteDoc"
                        wire:loading.attr="disabled"
                        wire:target="deleteDoc"
                        class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-lg bg-red-600 hover:bg-red-700 text-white shadow-sm shadow-red-500/20 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50"
                    >
                        <svg wire:loading.remove wire:target="deleteDoc" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        <svg wire:loading wire:target="deleteDoc" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="deleteDoc">Ya, Hapus</span>
                        <span wire:loading wire:target="deleteDoc">Menghapus...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
