<div
    @if($laporan->isDocProcessing())
        wire:poll.4s="refreshDocStatus"
    @endif
>
    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('laporan-mingguan.index') }}" wire:navigate
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
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Detail Laporan Mingguan</h2>
            </div>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Informasi lengkap laporan</p>
        </div>
        <div class="flex items-center gap-2">
            @can('update', $laporan)
                <a href="{{ route('laporan-mingguan.edit', $laporan) }}" wire:navigate
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

    {{-- Word Document Card --}}
    @can('download', $laporan)
        <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-5 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    {{-- Word icon --}}
                    <svg class="w-5 h-5 text-blue-700 dark:text-blue-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM8.5 17l1.5-5 1.5 5 1.5-4.5 1 3.5h1l-2-6-1.5 4.5L10 10l-2 7h1z"/>
                    </svg>
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Dokumen Word Laporan Mingguan</h3>
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
                    @if($laporan->jenisKapal->hasTemplate('mingguan'))
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
                            @can('download', $laporan)
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
                            @can('download', $laporan)
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
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        {{-- Card Header --}}
        <div class="px-5 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Informasi Laporan</h3>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                    Mingguan
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

            {{-- Periode --}}
            @if($laporan->periode_mulai || $laporan->periode_selesai)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Periode Mulai</label>
                    <p class="text-sm text-gray-900 dark:text-white">{{ $laporan->periode_mulai?->translatedFormat('d F Y') ?? '-' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Periode Selesai</label>
                    <p class="text-sm text-gray-900 dark:text-white">{{ $laporan->periode_selesai?->translatedFormat('d F Y') ?? '-' }}</p>
                </div>
            </div>
            @endif

            {{-- Ringkasan --}}
            @if($laporan->ringkasan)
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Ringkasan</label>
                <p class="text-sm text-gray-900 dark:text-white whitespace-pre-wrap">{{ $laporan->ringkasan }}</p>
            </div>
            @endif

            {{-- Pembuat + Dibuat }}--}}
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

            {{-- Diupdate --}}
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Diupdate Pada</label>
                <p class="text-sm text-gray-900 dark:text-white">{{ $laporan->updated_at->translatedFormat('d F Y H:i') }}</p>
            </div>
        </div>
    </div>

    {{-- Laporan Harian Teragregasi --}}
    @if(count($availableLaporanHarian) > 0)
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mt-6">
        <div class="px-5 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                    Laporan Harian Teragregasi
                    <span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                        {{ count($availableLaporanHarian) }} laporan harian otomatis dipilih
                    </span>
                </h3>
            </div>
        </div>
        <div class="p-5">
            <div class="space-y-2">
                @foreach($availableLaporanHarian as $item)
                    <div class="flex items-center gap-3 p-2 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center justify-center w-5 h-5 bg-green-500 rounded-full flex-shrink-0">
                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('laporan-harian.show', $item) }}" wire:navigate
                                class="font-medium text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400">
                                {{ $item->judul }}
                            </a>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item->tanggal_laporan->format('d M Y') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Lampiran Harian Terpilih --}}
    @if($laporan->periode_mulai && $laporan->periode_selesai && $laporan->lampiran->count() > 0)
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mt-6">
        <div class="px-5 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                    Lampiran Harian Terpilih
                    <span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                        {{ $laporan->lampiran->count() }}/{{ count($lampiranHarianList) }} dipilih
                    </span>
                </h3>
            </div>
        </div>
        <div class="p-5">
            <div class="space-y-2">
                @foreach($laporan->lampiran as $lampiran)
                    <div class="flex items-center gap-3 p-2 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center justify-center w-5 h-5 bg-green-500 rounded-full flex-shrink-0">
                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $lampiran->file_name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($lampiran->file_size / 1024, 1) }} KB • {{ $lampiran->laporanHarian->tanggal_laporan->format('d M Y') }}</p>
                        </div>
                        @if($lampiran->isImage())
                            <button wire:click="previewLampiranHarian({{ $lampiran->id }})" 
                                    wire:loading.attr="disabled"
                                    wire:target="previewLampiranHarian({{ $lampiran->id }})"
                                    class="p-1.5 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors disabled:opacity-50"
                                    title="Preview">
                                <svg wire:loading.class="hidden" wire:target="previewLampiranHarian({{ $lampiran->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg wire:loading wire:target="previewLampiranHarian({{ $lampiran->id }})" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Back button --}}
    <div class="flex items-center mt-6">
        <a href="{{ route('laporan-mingguan.index') }}" wire:navigate
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

        {{-- Lampiran Preview Modal --}}
        <x-lampiran-preview-modal
            :show="$showPreviewModal"
            :lampiran="$this->previewLampiran"
            :laporan="null"
            :imageUrl="$this->previewLampiranImageUrl"
        />

        {{-- Regenerate Confirmation Modal --}}
        <x-document-confirm-modal
            :show="$showRegenerateConfirm"
            showProperty="showRegenerateConfirm"
            title="Generate Ulang Dokumen Word?"
            message="Tindakan ini akan menghapus dokumen yang sudah ada."
            type="regenerate"
            :docName="$laporan->doc_name"
            confirmAction="generateWord"
            cancelAction="cancelRegenerate"
        />

        {{-- Delete Document Confirmation Modal --}}
        <x-document-confirm-modal
            :show="$showDeleteDocConfirm"
            showProperty="showDeleteDocConfirm"
            title="Hapus Dokumen Word?"
            message="Tindakan ini tidak dapat dibatalkan."
            type="delete"
            :docName="$laporan->doc_name"
            confirmAction="deleteDoc"
            cancelAction="cancelDeleteDoc"
        />
</div>
