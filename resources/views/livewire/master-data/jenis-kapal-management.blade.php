<div>
    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="w-full">
            <x-searchable-select
                wire:model.live="companyFilter"
                :options="collect($companies)->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->toArray()"
                placeholder="Filter Perusahaan"
                searchPlaceholder="Cari perusahaan..."
            />
        </div>

        <div class="w-full">
            <x-searchable-select
                wire:model.live="galanganFilter"
                :options="collect($galangans)->map(fn($g) => ['value' => $g->id, 'label' => $g->nama])->toArray()"
                placeholder="Filter Galangan"
                searchPlaceholder="Cari galangan..."
            />
        </div>

        <div class="w-full">
            <x-searchable-select
                wire:model.live="statusFilter"
                :options="collect($statuses)->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->toArray()"
                placeholder="Filter Status"
                searchPlaceholder="Cari status..."
            />
        </div>
    </div>

    <div class="mb-6 flex flex-col lg:flex-row lg:items-center gap-3">
        <div class="flex-1">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari jenis kapal..."
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
        </div>

        <div class="w-full lg:w-32">
            <x-searchable-select
                wire:model.live="perPage"
                :options="[
                    ['value' => '10', 'label' => '10'],
                    ['value' => '25', 'label' => '25'],
                    ['value' => '50', 'label' => '50'],
                    ['value' => '100', 'label' => '100']
                ]"
                placeholder="10"
                searchPlaceholder="Pilih jumlah..."
                :clearable="false"
            />
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            @can('jenis_kapal_download_template')
                <x-loading-button wire:click="openDownloadTemplateModal" target="openDownloadTemplateModal" variant="secondary" size="md" loadingText="Memuat..." title="Download Template">
                    <x-slot:icon>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </x-slot:icon>
                    Download Template
                </x-loading-button>
            @endcan
            @can('jenis_kapal_export_excel')
                <x-loading-button wire:click="exportExcel" target="exportExcel" variant="success" size="md" loadingText="Exporting..." title="Export Excel">
                    <x-slot:icon>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </x-slot:icon>
                    Excel
                </x-loading-button>
            @endcan
            @can('jenis_kapal_export_pdf')
                <x-loading-button wire:click="exportPdf" target="exportPdf" variant="danger" size="md" loadingText="Exporting..." title="Export PDF">
                    <x-slot:icon>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </x-slot:icon>
                    PDF
                </x-loading-button>
            @endcan
            @can('jenis_kapal_create')
                <x-loading-button wire:click="create" target="create" variant="primary" size="md" loadingText="Memuat..." class="flex-1 lg:flex-none">
                    <x-slot:icon>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </x-slot:icon>
                    Tambah Jenis Kapal
                </x-loading-button>
            @endcan
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Jenis Kapal</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Perusahaan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Galangan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Template</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Laporan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($jenisKapalList as $jenisKapal)
                        <tr class="hover:bg-blue-50 dark:hover:bg-blue-900/10 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold text-xs">
                                            {{ strtoupper(substr($jenisKapal->nama, 0, 2)) }}
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $jenisKapal->nama }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($jenisKapal->company)
                                    <div class="text-sm text-gray-900 dark:text-white">{{ $jenisKapal->company->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $jenisKapal->company->code }}</div>
                                @else
                                    <span class="text-sm text-gray-400 dark:text-gray-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($jenisKapal->galangan)
                                    <div class="text-sm text-gray-900 dark:text-white">{{ $jenisKapal->galangan->nama }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $jenisKapal->galangan->kode }}</div>
                                @else
                                    <span class="text-sm text-gray-400 dark:text-gray-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="space-y-1">
                                    {{-- Harian --}}
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-gray-500 dark:text-gray-400 w-16">Harian:</span>
                                        @if($jenisKapal->hasTemplate('harian'))
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400">
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                                Ada
                                            </span>
                                            @can('jenis_kapal_upload_template')
                                                <button wire:click="downloadTemplate({{ $jenisKapal->id }}, 'harian')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="downloadTemplate({{ $jenisKapal->id }}, 'harian')"
                                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 disabled:opacity-50"
                                                    title="Download Template Harian">
                                                    <svg wire:loading.class="hidden" wire:target="downloadTemplate({{ $jenisKapal->id }}, 'harian')" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                    <svg wire:loading wire:target="downloadTemplate({{ $jenisKapal->id }}, 'harian')" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </button>
                                                <button wire:click="confirmDeleteTemplate({{ $jenisKapal->id }}, 'harian')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="confirmDeleteTemplate({{ $jenisKapal->id }}, 'harian')"
                                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50"
                                                    title="Hapus Template Harian">
                                                    <svg wire:loading.class="hidden" wire:target="confirmDeleteTemplate({{ $jenisKapal->id }}, 'harian')" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                    <svg wire:loading wire:target="confirmDeleteTemplate({{ $jenisKapal->id }}, 'harian')" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </button>
                                            @endcan
                                        @else
                                            @can('jenis_kapal_upload_template')
                                                <button wire:click="openTemplateUploadModal({{ $jenisKapal->id }}, 'harian')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="openTemplateUploadModal({{ $jenisKapal->id }}, 'harian')"
                                                    class="text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400 disabled:opacity-50"
                                                    title="Upload Template Harian">
                                                    <svg wire:loading.class="hidden" wire:target="openTemplateUploadModal({{ $jenisKapal->id }}, 'harian')" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                                    </svg>
                                                    <svg wire:loading wire:target="openTemplateUploadModal({{ $jenisKapal->id }}, 'harian')" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </button>
                                            @endcan
                                            <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                                        @endif
                                    </div>
                                    {{-- Mingguan --}}
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-gray-500 dark:text-gray-400 w-16">Mingguan:</span>
                                        @if($jenisKapal->hasTemplate('mingguan'))
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400">
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                                Ada
                                            </span>
                                            @can('jenis_kapal_upload_template')
                                                <button wire:click="downloadTemplate({{ $jenisKapal->id }}, 'mingguan')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="downloadTemplate({{ $jenisKapal->id }}, 'mingguan')"
                                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 disabled:opacity-50"
                                                    title="Download Template Mingguan">
                                                    <svg wire:loading.class="hidden" wire:target="downloadTemplate({{ $jenisKapal->id }}, 'mingguan')" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                    <svg wire:loading wire:target="downloadTemplate({{ $jenisKapal->id }}, 'mingguan')" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </button>
                                                <button wire:click="confirmDeleteTemplate({{ $jenisKapal->id }}, 'mingguan')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="confirmDeleteTemplate({{ $jenisKapal->id }}, 'mingguan')"
                                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50"
                                                    title="Hapus Template Mingguan">
                                                    <svg wire:loading.class="hidden" wire:target="confirmDeleteTemplate({{ $jenisKapal->id }}, 'mingguan')" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                    <svg wire:loading wire:target="confirmDeleteTemplate({{ $jenisKapal->id }}, 'mingguan')" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </button>
                                            @endcan
                                        @else
                                            @can('jenis_kapal_upload_template')
                                                <button wire:click="openTemplateUploadModal({{ $jenisKapal->id }}, 'mingguan')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="openTemplateUploadModal({{ $jenisKapal->id }}, 'mingguan')"
                                                    class="text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400 disabled:opacity-50"
                                                    title="Upload Template Mingguan">
                                                    <svg wire:loading.class="hidden" wire:target="openTemplateUploadModal({{ $jenisKapal->id }}, 'mingguan')" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                                    </svg>
                                                    <svg wire:loading wire:target="openTemplateUploadModal({{ $jenisKapal->id }}, 'mingguan')" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </button>
                                            @endcan
                                            <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                                        @endif
                                    </div>
                                    {{-- Bulanan --}}
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-gray-500 dark:text-gray-400 w-16">Bulanan:</span>
                                        @if($jenisKapal->hasTemplate('bulanan'))
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400">
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                                Ada
                                            </span>
                                            @can('jenis_kapal_upload_template')
                                                <button wire:click="downloadTemplate({{ $jenisKapal->id }}, 'bulanan')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="downloadTemplate({{ $jenisKapal->id }}, 'bulanan')"
                                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 disabled:opacity-50"
                                                    title="Download Template Bulanan">
                                                    <svg wire:loading.class="hidden" wire:target="downloadTemplate({{ $jenisKapal->id }}, 'bulanan')" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                    <svg wire:loading wire:target="downloadTemplate({{ $jenisKapal->id }}, 'bulanan')" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </button>
                                                <button wire:click="confirmDeleteTemplate({{ $jenisKapal->id }}, 'bulanan')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="confirmDeleteTemplate({{ $jenisKapal->id }}, 'bulanan')"
                                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50"
                                                    title="Hapus Template Bulanan">
                                                    <svg wire:loading.class="hidden" wire:target="confirmDeleteTemplate({{ $jenisKapal->id }}, 'bulanan')" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                    <svg wire:loading wire:target="confirmDeleteTemplate({{ $jenisKapal->id }}, 'bulanan')" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </button>
                                            @endcan
                                        @else
                                            @can('jenis_kapal_upload_template')
                                                <button wire:click="openTemplateUploadModal({{ $jenisKapal->id }}, 'bulanan')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="openTemplateUploadModal({{ $jenisKapal->id }}, 'bulanan')"
                                                    class="text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400 disabled:opacity-50"
                                                    title="Upload Template Bulanan">
                                                    <svg wire:loading.class="hidden" wire:target="openTemplateUploadModal({{ $jenisKapal->id }}, 'bulanan')" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                                    </svg>
                                                    <svg wire:loading wire:target="openTemplateUploadModal({{ $jenisKapal->id }}, 'bulanan')" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </button>
                                            @endcan
                                            <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400">
                                    {{ $jenisKapal->laporan_count }} laporan
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @can('jenis_kapal_update')
                                    <button wire:click="toggleStatus({{ $jenisKapal->id }})"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2
                                            {{ $jenisKapal->status->value === 'active' ? 'bg-blue-600' : 'bg-gray-200 dark:bg-gray-700' }}">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform
                                            {{ $jenisKapal->status->value === 'active' ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </button>
                                @else
                                    @php
                                        $statusColors = [
                                            'active' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
                                            'inactive' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400',
                                        ];
                                    @endphp
                                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $statusColors[$jenisKapal->status->value] ?? $statusColors['inactive'] }}">
                                        {{ $jenisKapal->status->label() }}
                                    </span>
                                @endcan
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    @can('jenis_kapal_update')
                                        <button wire:click="edit({{ $jenisKapal->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="edit({{ $jenisKapal->id }})"
                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 disabled:opacity-50"
                                            title="Edit">
                                            <svg wire:loading.class="hidden" wire:target="edit({{ $jenisKapal->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            <svg wire:loading wire:target="edit({{ $jenisKapal->id }})" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </button>
                                    @endcan
                                    @can('jenis_kapal_delete')
                                        <button wire:click="confirmDelete({{ $jenisKapal->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="confirmDelete({{ $jenisKapal->id }})"
                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50"
                                            title="Hapus">
                                            <svg wire:loading.class="hidden" wire:target="confirmDelete({{ $jenisKapal->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            <svg wire:loading wire:target="confirmDelete({{ $jenisKapal->id }})" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Tidak ada jenis kapal ditemukan</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $jenisKapalList->links() }}
        </div>
    </div>

    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75" @click="$wire.closeModal()"></div>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <form wire:submit="save">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                {{ $editMode ? 'Edit Jenis Kapal' : 'Tambah Jenis Kapal' }}
                            </h3>

                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Perusahaan</label>
                                    <x-searchable-select
                                        wire:model.live="company_id"
                                        :options="collect($companies)->map(fn($c) => ['value' => $c->id, 'label' => $c->name . ' (' . $c->code . ')'])->toArray()"
                                        placeholder="Pilih Perusahaan"
                                        searchPlaceholder="Cari perusahaan..."
                                    />
                                    @error('company_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Galangan</label>
                                    <x-searchable-select
                                        wire:model.live="galangan_id"
                                        :options="collect($galangans)->map(fn($g) => ['value' => $g->id, 'label' => $g->nama . ' (' . $g->kode . ')'])->toArray()"
                                        placeholder="Pilih Galangan"
                                        searchPlaceholder="Cari galangan..."
                                    />
                                    @error('galangan_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jenis Kapal <span class="text-red-500">*</span></label>
                                    <input wire:model="nama" type="text" required
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    @error('nama') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Deskripsi</label>
                                    <textarea wire:model="deskripsi" rows="4"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                                    @error('deskripsi') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status <span class="text-red-500">*</span></label>
                                    <x-searchable-select
                                        wire:model.live="status"
                                        :options="collect($statuses)->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->toArray()"
                                        placeholder="Pilih Status"
                                        searchPlaceholder="Cari status..."
                                        :error="$errors->has('status')"
                                    />
                                    @error('status') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-900 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                            <x-loading-button type="submit" target="save" variant="primary" size="lg"
                                loadingText="Menyimpan..." class="w-full sm:w-auto">
                                {{ $editMode ? 'Update' : 'Simpan' }}
                            </x-loading-button>
                            <x-loading-button type="button" @click="$wire.closeModal()" variant="secondary" size="lg"
                                class="mt-3 sm:mt-0 w-full sm:w-auto">
                                Batal
                            </x-loading-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <x-delete-modal 
        :show="$showDeleteModal"
        wire:model="showDeleteModal"
        title="Hapus Jenis Kapal"
        message="Apakah Anda yakin ingin menghapus jenis kapal"
        :itemName="$deletingJenisKapalNama"
        confirmMethod="delete"
    />

    <x-download-template-modal 
        :show="$showDownloadTemplateModal"
        wire:model="showDownloadTemplateModal"
    />

    <x-upload-template-modal 
        :show="$showTemplateUploadModal"
        wire:model="showTemplateUploadModal"
        :tipe="$uploadingTemplateTipe"
    />

    <x-delete-modal 
        :show="$showDeleteTemplateModal"
        wire:model="showDeleteTemplateModal"
        title="Hapus Template"
        message="Apakah Anda yakin ingin menghapus template laporan {{ $deletingTemplateTipe }} untuk jenis kapal ini?"
        confirmMethod="deleteTemplate"
    />
</div>
