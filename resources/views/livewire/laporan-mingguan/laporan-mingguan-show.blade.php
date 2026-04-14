<div>
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

            {{-- Diupdate --}}
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Diupdate Pada</label>
                <p class="text-sm text-gray-900 dark:text-white">{{ $laporan->updated_at->translatedFormat('d F Y H:i') }}</p>
            </div>
        </div>
    </div>

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
    </div>
</div>
