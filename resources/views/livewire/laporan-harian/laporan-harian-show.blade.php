<div
    @if($laporan->isDocProcessing() || $laporan->isPdfProcessing())
        wire:poll.4s="refreshDocStatus"
    @endif
    @if($this->hasProcessingLampiran() || $this->hasProcessingNcFoto())
        wire:poll.4s="refreshLampiranStatus"
    @endif
>
    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('laporan-harian.index', $dataKapal) }}" wire:navigate
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
            @if($laporan->is_libur)
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span class="inline-block px-2 py-1 rounded-lg text-xs font-medium {{ $laporan->status_hari->badgeClass() }}">{{ $laporan->status_hari->label() }}</span>
                </div>
            @endif
        </div>
        <div class="flex items-center gap-2">
            @can('update', $laporan)
                <a href="{{ route('laporan-harian.edit', [$dataKapal, $laporan]) }}" wire:navigate
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

    {{-- Data Proyek Info Card --}}
    <x-data-kapal-info-card :data-kapal="$laporan->dataKapal" />

    {{-- Word Document Card (Harian only) --}}
        @can('download', $laporan)
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
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-center text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                        <svg class="animate-spin w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ $laporan->doc_status === 'pending' ? 'Menunggu antrian...' : 'Sedang diproses...' }}
                    </span>
                @elseif($laporan->isDocCompleted())
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-center text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Siap diunduh
                    </span>
                @elseif($laporan->isDocFailed())
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-center text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Gagal
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-center text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                        Belum digenerate
                    </span>
                @endif
            </div>

            <div class="p-6">
                {{-- Template Status Info --}}
                @if($laporan->dataKapal)
                    @if($laporan->dataKapal->hasTemplate('harian'))
                        <div class="mb-4 flex items-start gap-2 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                            <svg class="w-4 h-4 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-sm text-green-700 dark:text-green-400">
                                Template khusus tersedia untuk proyek <strong>{{ $laporan->dataKapal->nama }}</strong>. Dokumen akan digenerate menggunakan template khusus.
                            </p>
                        </div>
                    @else
                        <div class="mb-4 flex items-start gap-2 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                            <svg class="w-4 h-4 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <p class="text-sm text-yellow-700 dark:text-yellow-400">
                                Template khusus belum tersedia untuk proyek <strong>{{ $laporan->dataKapal->nama }}</strong>. Dokumen akan digenerate menggunakan template default.
                                @can('data_kapal_upload_template')
                                    <a href="{{ route('master-data.data-kapal') }}" wire:navigate class="underline hover:text-yellow-800 dark:hover:text-yellow-300">Upload template di Master Data Kapal</a>
                                @endcan
                            </p>
                        </div>
                    @endif
                @endif

                @if($laporan->isPdfFailed())
                    <div class="mb-4 flex items-start gap-2 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                        <svg class="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm text-red-700 dark:text-red-400">Release PDF gagal: {{ $laporan->pdf_error ?? 'Terjadi kesalahan saat konversi PDF.' }}</p>
                    </div>
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
                                    title="Download Dokumen Word"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 shadow-sm">
                                    <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                    <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Word
                                </button>
                            @endcan
                            @if($laporan->hasPdf() && $laporan->isPdfCompleted())
                                @can('download', $laporan)
                                    <button wire:click="downloadPdf"
                                        x-data="{ loading: false }"
                                        x-on:click="loading = true; setTimeout(() => loading = false, 2000)"
                                        title="Download PDF Rilis"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-lg bg-red-600 hover:bg-red-700 text-white transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 shadow-sm">
                                        <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        PDF
                                    </button>
                                @endcan
                            @endif
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
                        @if($laporan->isDocCompleted())
                            @can('download', $laporan)
                            @if($laporan->isPdfProcessing())
                                <button disabled
                                    class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium rounded-lg bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400 cursor-not-allowed opacity-75">
                                    <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ $laporan->pdf_status === 'pending' ? 'Menunggu antrian...' : 'Merilis PDF...' }}
                                </button>
                            @else
                                <button wire:click="confirmReleasePdf"
                                    wire:loading.attr="disabled"
                                    wire:target="confirmReleasePdf"
                                    title="Konversi dokumen Word menjadi PDF rilis"
                                    class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold rounded-lg bg-red-600 hover:bg-red-700 text-white shadow-sm shadow-red-500/20 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-60">
                                    <svg wire:loading.class="hidden" wire:target="confirmReleasePdf" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                    </svg>
                                    <svg wire:loading wire:target="confirmReleasePdf" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="confirmReleasePdf">
                                        {{ $laporan->isPdfFailed() ? 'Coba Release Lagi' : 'Release PDF' }}
                                    </span>
                                    <span wire:loading wire:target="confirmReleasePdf">Memproses...</span>
                                </button>
                            @endif
                            @endcan
                        @endif
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
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-center text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                        Harian
                    </span>
            </div>
        </div>

        {{-- Card Body --}}
        <div class="p-5 space-y-5">
            {{-- Meta laporan: judul + chip tanggal/periode, lalu audit --}}
            <div>
                <p class="text-base font-semibold text-gray-900 dark:text-white leading-snug">{{ $laporan->judul }}</p>
                <div class="mt-2.5 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-center text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700/60 dark:text-gray-300">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        {{ $laporan->tanggal_laporan->translatedFormat('l, d F Y') }}
                    </span>
                    @if($hariKe)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-center text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/25 dark:text-blue-300">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Hari ke-{{ $hariKe }}
                        </span>
                    @endif
                    @if($mingguKe)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-center text-xs font-medium bg-indigo-50 text-indigo-700 dark:bg-indigo-900/25 dark:text-indigo-300">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Minggu ke-{{ $mingguKe }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="pt-4 border-t border-gray-100 dark:border-gray-700/60 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold text-sm shrink-0">
                        {{ substr($laporan->user->name ?? '-', 0, 1) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dibuat Oleh</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $laporan->user->name ?? '-' }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $laporan->created_at->translatedFormat('d M Y, H:i') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-full bg-gradient-to-br from-emerald-500 to-emerald-600 flex items-center justify-center text-white font-semibold text-sm shrink-0">
                        {{ substr($laporan->updatedBy->name ?? '-', 0, 1) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Terakhir Diubah</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $laporan->updatedBy->name ?? '—' }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $laporan->updated_at->translatedFormat('d M Y, H:i') }}</p>
                    </div>
                </div>
            </div>



            @if($laporan->is_libur)
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-5 py-8 text-center dark:border-gray-700 dark:bg-gray-700/30 sm:px-8" role="status">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full {{ $laporan->status_hari->badgeClass() }}">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2Zm4-5 2 2 4-4" /></svg>
                    </div>
                    <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold {{ $laporan->status_hari->badgeClass() }}">{{ $laporan->status_hari->label() }}</span>
                    <h3 class="mt-3 text-lg font-semibold text-gray-900 dark:text-white">Keterangan Hari Libur</h3>
                    <p class="mx-auto mt-2 max-w-2xl whitespace-pre-line break-words text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $laporan->alasan_libur }}</p>
                    <p class="mt-4 text-xs leading-relaxed text-gray-500 dark:text-gray-400">Laporan ini ditandai sebagai hari libur. Rincian operasional tidak ditampilkan.</p>
                </div>
            @else
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

            {{-- Aktivitas Section — dikelompokkan per kategori (Work Group), kategori kosong tidak ditampilkan --}}
                <x-laporan.section-wrapper label="E. Aktivitas" :isEmpty="$laporan->aktivitas->count() === 0" :count="$laporan->aktivitas->count()">
                    @if(count($aktivitasGroups) > 0)
                        <x-laporan.aktivitas-kategori-section
                            :groups="$aktivitasGroups"
                            wireKeyPrefix="show-aktivitas"
                            persistKey="lh-kat:show:{{ $laporan->id }}"
                            readonly
                        />
                    @else
                        <x-laporan.empty-state icon="aktivitas" message="Data aktivitas belum diisi" />
                    @endif
                </x-laporan.section-wrapper>

            {{-- Lampiran Section — foto per uraian aktivitas --}}
            <x-laporan.section-wrapper label="F. Lampiran" :isEmpty="false">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">1. Dokumen Pendukung</h4>
                <x-laporan-mingguan.external-reports-show-section :laporan-external="$laporan->laporanExternal" />
                @if($laporan->laporanExternal->isEmpty())
                    <x-laporan.empty-state icon="aktivitas" message="Belum ada dokumen pendukung" />
                @endif
                <h4 class="mt-4 text-sm font-semibold text-gray-900 dark:text-white">2. Dokumentasi</h4>
                <x-laporan.lampiran-aktivitas-grid
                    :groups="$aktivitasGroups"
                    :existing="$lampiranByAktivitas"
                    readonly
                />
            </x-laporan.section-wrapper>

            {{-- NC Section — temuan per kategori (Work Group), diletakkan paling akhir --}}
                <x-laporan.section-wrapper label="Non-Conformity (NC)" :isEmpty="$laporan->ncs->count() === 0" :count="$laporan->ncs->count()">
                    @if($laporan->ncs->count() === 0)
                        <x-laporan.empty-state icon="nc" message="Tidak ada temuan Non-Conformity (NC) pada laporan ini" />
                    @else
                        <x-laporan.nc-kategori-section
                            :groups="$ncGroups"
                            :existing="$laporan->ncs->keyBy('id')"
                            :readonly="true"
                        />
                    @endif
                </x-laporan.section-wrapper>
            @endif
        </div>
    </div>

    {{-- Back button --}}
    <div class="flex items-center">
        <a href="{{ route('laporan-harian.index', $dataKapal) }}" wire:navigate
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

    {{-- Preview Foto Bukti NC --}}
    <x-lampiran-preview-modal
        :show="$showNcPreviewModal"
        :lampiran="$this->previewNcFoto"
        :laporan="$laporan"
        :imageUrl="$this->previewNcFotoImageUrl"
        downloadMethod="downloadNcFoto"
        closeMethod="closeNcPreviewModal"
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
        message="Dokumen yang sudah digenerate akan dihapus permanen."
        type="delete"
        :docName="$laporan->doc_name"
        confirmAction="deleteDoc"
        cancelAction="cancelDeleteDoc"
    />

    {{-- Release PDF Confirmation Modal --}}
    <x-document-confirm-modal
        :show="$showReleaseConfirm"
        showProperty="showReleaseConfirm"
        title="Release Dokumen ke PDF?"
        message="Dokumen Word tetap tersimpan dan akan dikonversi menjadi PDF rilis. Hanya PDF rilis lama, jika ada, yang akan diganti."
        type="release"
        :docName="$laporan->doc_name"
        confirmAction="releasePdf"
        cancelAction="cancelReleasePdf"
    />
</div>
