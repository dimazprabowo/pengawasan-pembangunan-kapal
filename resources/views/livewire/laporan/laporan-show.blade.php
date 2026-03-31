<div>
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

            {{-- Lampiran Section --}}
            @if($laporan->lampiran->count() > 0)
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
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
                                        {{-- Preview Button --}}
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
                                        
                                        {{-- Download Button --}}
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

            {{-- Isi Laporan --}}
            @if($laporan->isi)
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Isi Laporan</label>
                    <div class="prose prose-sm dark:prose-invert max-w-none text-gray-900 dark:text-gray-100 bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                        {!! nl2br(e($laporan->isi)) !!}
                    </div>
                </div>
            @endif

            {{-- Catatan --}}
            @if($laporan->catatan)
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Catatan</label>
                    <div class="text-sm text-gray-700 dark:text-gray-300 bg-amber-50 dark:bg-amber-900/10 rounded-lg p-4 border border-amber-200 dark:border-amber-800">
                        {!! nl2br(e($laporan->catatan)) !!}
                    </div>
                </div>
            @endif

            {{-- Weather Information for Harian --}}
            @if($laporan->tipe->value === 'harian' && ($laporan->suhu || $laporan->cuacaPagi || $laporan->kelembabanPagi || $laporan->cuacaSiang || $laporan->kelembabanSiang || $laporan->cuacaSore || $laporan->kelembabanSore))
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Informasi Cuaca</label>
                    
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
                            <div class="bg-orange-50 dark:bg-orange-900/10 rounded-lg p-4 border border-orange-200 dark:border-orange-800">
                                <h5 class="text-sm font-medium text-orange-800 dark:text-orange-400 mb-2 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                                    </svg>
                                    Pagi
                                </h5>
                                <div class="space-y-2 text-xs">
                                    @if($laporan->cuacaPagi)
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-600 dark:text-gray-400">Cuaca:</span>
                                            <span class="font-medium text-gray-900 dark:text-white">{{ $laporan->cuacaPagi->nama }}</span>
                                        </div>
                                    @endif
                                    @if($laporan->kelembabanPagi)
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-600 dark:text-gray-400">Kelembaban:</span>
                                            <span class="font-medium text-gray-900 dark:text-white">{{ $laporan->kelembabanPagi->nama }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- Siang --}}
                        @if($laporan->cuacaSiang || $laporan->kelembabanSiang)
                            <div class="bg-yellow-50 dark:bg-yellow-900/10 rounded-lg p-4 border border-yellow-200 dark:border-yellow-800">
                                <h5 class="text-sm font-medium text-yellow-800 dark:text-yellow-400 mb-2 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                                    </svg>
                                    Siang
                                </h5>
                                <div class="space-y-2 text-xs">
                                    @if($laporan->cuacaSiang)
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-600 dark:text-gray-400">Cuaca:</span>
                                            <span class="font-medium text-gray-900 dark:text-white">{{ $laporan->cuacaSiang->nama }}</span>
                                        </div>
                                    @endif
                                    @if($laporan->kelembabanSiang)
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-600 dark:text-gray-400">Kelembaban:</span>
                                            <span class="font-medium text-gray-900 dark:text-white">{{ $laporan->kelembabanSiang->nama }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- Sore --}}
                        @if($laporan->cuacaSore || $laporan->kelembabanSore)
                            <div class="bg-indigo-50 dark:bg-indigo-900/10 rounded-lg p-4 border border-indigo-200 dark:border-indigo-800">
                                <h5 class="text-sm font-medium text-indigo-800 dark:text-indigo-400 mb-2 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                                    </svg>
                                    Sore
                                </h5>
                                <div class="space-y-2 text-xs">
                                    @if($laporan->cuacaSore)
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-600 dark:text-gray-400">Cuaca:</span>
                                            <span class="font-medium text-gray-900 dark:text-white">{{ $laporan->cuacaSore->nama }}</span>
                                        </div>
                                    @endif
                                    @if($laporan->kelembabanSore)
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-600 dark:text-gray-400">Kelembaban:</span>
                                            <span class="font-medium text-gray-900 dark:text-white">{{ $laporan->kelembabanSore->nama }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
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
