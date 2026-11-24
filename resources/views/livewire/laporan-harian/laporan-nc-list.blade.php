<div @if($hasProcessing) wire:poll.4s @endif>
    {{-- Toolbar: search + filter popover + export + form penutupan --}}
    <div class="mb-4 flex flex-col lg:flex-row lg:items-center gap-3">
        <div class="flex-1">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari nomor / kendala / PIC..."
                class="w-full px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
        </div>

        <x-filter-popover :filters="['bulanFilter', 'mingguFilter', 'hariFilter', 'statusFilter']" :per-page="true">
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Bulan</label>
                <x-multi-searchable-select wire:model.live="bulanFilter" :options="$bulanOptions" placeholder="Semua Bulan" searchPlaceholder="Cari bulan..." />
            </div>
            <div wire:key="minggu-wrap-{{ implode('-', $bulanFilter) }}">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Minggu</label>
                {{-- key pada wrapper → subtree diganti saat bulan berubah → options select mengikuti bulan --}}
                <x-multi-searchable-select wire:model.live="mingguFilter" :options="$mingguOptions" placeholder="{{ $bulanFilter ? 'Minggu pada bulan terpilih' : 'Semua Minggu' }}" searchPlaceholder="Cari minggu..." />
            </div>
            <div wire:key="hari-wrap-{{ implode('-', $bulanFilter) }}-{{ implode('-', $mingguFilter) }}">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Hari</label>
                {{-- key ikut bulan+minggu → subtree diganti saat periode berubah → opsi tanggal mengikuti --}}
                <x-multi-searchable-select wire:model.live="hariFilter" :options="$hariOptions" placeholder="{{ ($bulanFilter || $mingguFilter) ? 'Hari pada periode terpilih' : 'Semua Hari' }}" searchPlaceholder="Cari tanggal..." />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Status</label>
                <x-searchable-select wire:model.live="statusFilter" :options="$statusOptions" placeholder="Semua Status" searchPlaceholder="Cari status..." />
            </div>
        </x-filter-popover>

        <div class="flex items-center gap-2 flex-wrap">
            @can('exportExcel', \App\Models\LaporanHarian::class)
                <x-loading-button wire:click="exportExcel" target="exportExcel" variant="success" size="md" loadingText="Exporting..." title="Export Excel">
                    <x-slot:icon>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </x-slot:icon>
                    Excel
                </x-loading-button>
            @endcan
            @can('exportPdf', \App\Models\LaporanHarian::class)
                <x-loading-button wire:click="exportPdf" target="exportPdf" variant="danger" size="md" loadingText="Exporting..." title="Export PDF">
                    <x-slot:icon>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </x-slot:icon>
                    PDF
                </x-loading-button>
            @endcan
        </div>
    </div>

    {{-- Bar seleksi: generate Form Penutupan dari NC Open terpilih (lintas hari) --}}
    @can('create', \App\Models\LaporanNcForm::class)
        @if(count($selectedNcIds) > 0)
            <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-4 py-3 rounded-lg border border-emerald-200 dark:border-emerald-800/60 bg-emerald-50 dark:bg-emerald-900/20">
                <div class="flex items-center gap-2 text-sm text-emerald-800 dark:text-emerald-300">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span><span class="font-semibold">{{ count($selectedNcIds) }} NC</span> dipilih — buat Form Penutupan, cetak &amp; tanda tangani, lalu unggah scan-nya untuk menutup NC.</span>
                </div>
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <x-cancel-button wire:click="clearSelection" target="clearSelection" size="md" label="Batal Pilih" class="w-full sm:w-auto" />
                    <x-loading-button wire:click="generatePenutupan" target="generatePenutupan" variant="success" size="md" loadingText="Membuat..." class="w-full sm:w-auto" title="Generate Form Penutupan">
                        <x-slot:icon>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </x-slot:icon>
                        Generate Form Penutupan
                    </x-loading-button>
                </div>
            </div>
        @endif
    @endcan

    {{-- Tabel List NC per laporan harian --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                @php
                    $selected = array_map('intval', $selectedNcIds);
                    $pageOpenIds = $ncList->getCollection()->filter(fn ($nc) => $nc->isOpen() && ! $nc->isInDraftPenutupan() && $nc->hasCompletedCloseFile())->pluck('id')->all();
                    $allChecked = $pageOpenIds !== [] && array_diff($pageOpenIds, $selected) === [];
                @endphp
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th rowspan="2" class="px-4 py-3 w-10 text-center align-middle">
                            @can('create', \App\Models\LaporanNcForm::class)
                                <input type="checkbox" wire:click="toggleSelectAll" @checked($allChecked) @disabled($pageOpenIds === [])
                                    wire:key="nc-select-all-{{ $allChecked ? 'on' : 'off' }}-{{ implode('-', $pageOpenIds) }}"
                                    class="rounded text-blue-600 focus:ring-blue-500 cursor-pointer disabled:opacity-40" title="Pilih semua NC Open yang punya file Close di halaman ini">
                            @endcan
                        </th>
                        <th rowspan="2" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 align-middle">NC</th>
                        <th rowspan="2" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 align-middle">Kendala / Tindakan</th>
                        <th rowspan="2" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 align-middle">PIC</th>
                        <th rowspan="2" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 align-middle">Status</th>
                        <th colspan="2" class="px-4 py-2 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-600">File</th>
                        <th colspan="2" class="px-4 py-2 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-600">Form Open</th>
                        <th colspan="2" class="px-4 py-2 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-600">Form Close</th>
                        <th rowspan="2" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 align-middle">Aksi</th>
                    </tr>
                    <tr>
                        <th class="px-4 py-2 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Open</th>
                        <th class="px-4 py-2 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Close</th>
                        <th class="px-3 py-2 text-center text-[11px] font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">Draft</th>
                        <th class="px-3 py-2 text-center text-[11px] font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">TTD</th>
                        <th class="px-3 py-2 text-center text-[11px] font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">Draft</th>
                        <th class="px-3 py-2 text-center text-[11px] font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">TTD</th>
                    </tr>
                </thead>
                    @forelse($ncList->getCollection()->groupBy('laporan_harian_id') as $laporanId => $ncs)
                        @php
                            $openIds = $ncs->filter(fn ($nc) => $nc->isOpen() && ! $nc->isInDraftPenutupan() && $nc->hasCompletedCloseFile())->pluck('id');
                            $groupAllChecked = $openIds->isNotEmpty() && $openIds->diff($selected)->isEmpty();
                            $laporan = $ncs->first()->laporanHarian;
                            $temuanForms = $temuanByLaporan->get($laporanId, collect());
                            $temuanDraft = $temuanForms->first(fn ($f) => $f->isDraft());
                        @endphp
                        {{-- Satu tbody per grup tanggal: rowspan sel Form Open tetap dalam tbody
                             yang sama + hover grup menyorot seluruh baris yang di-merge --}}
                        <tbody wire:key="tb-{{ $laporanId }}" class="group/date divide-y divide-gray-200 dark:divide-gray-700">
                        <tr wire:key="grp-{{ $laporanId }}" class="bg-gray-50 dark:bg-gray-700/40 border-t border-gray-200 dark:border-gray-700">
                            <td colspan="12" class="px-4 py-2">
                                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        @can('create', \App\Models\LaporanNcForm::class)
                                            <input type="checkbox" wire:click="toggleNcGroup('{{ $openIds->implode(',') }}')" @checked($groupAllChecked) @disabled($openIds->isEmpty())
                                                wire:key="nc-grp-cb-{{ $laporanId }}-{{ $groupAllChecked ? 'on' : 'off' }}"
                                                class="rounded text-blue-600 focus:ring-blue-500 cursor-pointer disabled:opacity-40" title="Pilih semua NC Open yang punya file Close tanggal ini">
                                        @endcan
                                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        <span class="text-xs font-semibold text-gray-700 dark:text-gray-200 whitespace-nowrap">Laporan {{ $laporan?->tanggal_laporan?->translatedFormat('d M Y') ?? '-' }}</span>
                                        <span class="text-[11px] text-gray-400 whitespace-nowrap">{{ $ncs->count() }} NC · {{ $openIds->count() }} open</span>
                                    </div>
                                    <div class="flex items-center gap-2 flex-wrap sm:ml-auto">
                                        @can('create', \App\Models\LaporanNcForm::class)
                                            <x-loading-button wire:click="generateTemuan({{ $laporanId }})" target="generateTemuan({{ $laporanId }})"
                                                variant="warning" size="xs" loadingText="Membuat..." class="w-44"
                                                title="Generate Form Temuan NC hari ini (docx terisi otomatis)">
                                                <x-slot:icon>
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                </x-slot:icon>
                                                {{ $temuanDraft ? 'Generate Ulang Temuan' : 'Generate Form Temuan' }}
                                            </x-loading-button>
                                        @endcan
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @foreach($ncs as $nc)
                            @php
                                $ncChecked = in_array($nc->id, $selected, true);
                                $penutupanDraft = $nc->forms->filter(fn ($f) => $f->isPenutupan() && $f->isDraft())->sortByDesc('id')->first();
                                $penutupanSigned = $nc->forms->filter(fn ($f) => $f->isPenutupan() && ! $f->isDraft())->sortByDesc('id')->first();
                            @endphp
                            <tr wire:key="nc-{{ $nc->id }}" class="group-hover/date:bg-gray-50 dark:group-hover/date:bg-gray-700/30 transition-colors {{ $ncChecked ? 'bg-blue-50/50 dark:bg-blue-900/10' : '' }}">
                                <td class="px-4 py-3 text-center">
                                    @can('create', \App\Models\LaporanNcForm::class)
                                        @if($nc->isOpen())
                                            @if($nc->isInDraftPenutupan())
                                                <input type="checkbox" disabled wire:key="nc-cb-{{ $nc->id }}-locked"
                                                    class="rounded text-blue-600 focus:ring-blue-500 opacity-40 cursor-not-allowed"
                                                    title="Sudah ada di draft penutupan {{ $penutupanDraft?->nomor }} — hapus draft itu dulu untuk memindahkan NC">
                                            @elseif(! $nc->hasCompletedCloseFile())
                                                <input type="checkbox" disabled wire:key="nc-cb-{{ $nc->id }}-noclose"
                                                    class="rounded text-blue-600 focus:ring-blue-500 opacity-40 cursor-not-allowed"
                                                    title="Belum ada file Close — unggah file Close dulu agar NC bisa masuk Form Penutupan">
                                            @else
                                                <input type="checkbox" wire:click="toggleNcSelect({{ $nc->id }})" @checked($ncChecked)
                                                    wire:key="nc-cb-{{ $nc->id }}-{{ $ncChecked ? 'on' : 'off' }}"
                                                    class="rounded text-blue-600 focus:ring-blue-500 cursor-pointer" title="Pilih NC untuk Form Penutupan">
                                            @endif
                                        @endif
                                    @endcan
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $nc->nomor ?? '-' }}</div>
                                    <div class="text-xs text-gray-400">{{ $nc->laporanHarian?->tanggal_laporan?->translatedFormat('d M Y') }} · {{ $nc->createdBy?->name }}</div>
                                    @if($nc->isClosed())
                                        <div class="text-xs text-emerald-600 dark:text-emerald-400">Closed {{ $nc->closed_at?->translatedFormat('d M Y') }}{{ $nc->closedBy ? ' · '.$nc->closedBy->name : '' }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 max-w-xs">
                                    <p class="line-clamp-2" title="{{ $nc->kendala }}">{{ $nc->kendala }}</p>
                                    @if($nc->tindakan)
                                        <p class="text-xs text-gray-400 mt-1 line-clamp-1" title="{{ $nc->tindakan }}">Tindakan: {{ $nc->tindakan }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $nc->pic ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $nc->status->badgeClass() }}">{{ $nc->status->label() }}</span>
                                </td>
                                @foreach(\App\Enums\NcFileKategori::cases() as $fileCategory)
                                <td wire:key="nc-files-{{ $nc->id }}-{{ $fileCategory->value }}" class="px-4 py-3 text-center min-w-48">
                                    @php $catFiles = $nc->foto->filter(fn ($file) => $file->kategori === $fileCategory); @endphp
                                    @if($catFiles->isNotEmpty())
                                        <button type="button" wire:click="openNcFiles({{ $nc->id }}, '{{ $fileCategory->value }}')"
                                            wire:loading.attr="disabled" wire:target="openNcFiles({{ $nc->id }}, '{{ $fileCategory->value }}')"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 text-xs font-medium text-gray-600 dark:text-gray-300 hover:border-blue-400 hover:text-blue-600 dark:hover:border-blue-500 dark:hover:text-blue-400 transition-colors disabled:opacity-50"
                                            title="Lihat {{ $catFiles->count() }} file {{ $fileCategory->label() }}">
                                            <svg wire:loading.class="hidden" wire:target="openNcFiles({{ $nc->id }}, '{{ $fileCategory->value }}')" class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V8a2 2 0 00-2-2h-8l-2-2z"/></svg>
                                            <svg wire:loading wire:target="openNcFiles({{ $nc->id }}, '{{ $fileCategory->value }}')" class="animate-spin w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                            {{ $catFiles->count() }} file
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400">Belum ada file.</span>
                                    @endif
                                    @can('update', $nc)
                                        <div class="mt-2 space-y-2" x-data="{ uploading: false, progress: 0,
                                            async uploadFiles(input) {
                                                this.uploading = true;
                                                try {
                                                    await window.lhUploadFiles($wire, input, 'ncUploads.{{ $nc->id }}.{{ $fileCategory->value }}.', e => this.progress = e.detail.progress);
                                                } catch (_) {} finally { this.uploading = false; }
                                            }
                                        }">
                                            <label class="flex w-full items-center justify-center gap-1 px-2 py-1.5 rounded-lg border border-dashed border-gray-300 dark:border-gray-600 text-xs text-gray-500 dark:text-gray-400 hover:border-blue-400 hover:text-blue-600 dark:hover:text-blue-400 cursor-pointer" :class="{ 'opacity-50 pointer-events-none': uploading }">
                                                <svg x-show="uploading" class="animate-spin w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                <svg x-show="!uploading" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                <span class="truncate" x-text="uploading ? 'Mengunggah ' + progress + '%' : 'Tambah {{ $fileCategory->label() }} (Foto/PDF)'"></span>
                                                <input type="file" multiple class="sr-only" accept=".{{ implode(',.', get_allowed_mimes_array('laporan_nc_file')) }}" x-on:change="uploadFiles($event.target)" :disabled="uploading" />
                                            </label>
                                            @foreach(data_get($ncUploads, $nc->id.'.'.$fileCategory->value, []) as $uploadKey => $entry)
                                                @if(is_object($entry['file'] ?? null))
                                                    <div wire:key="nc-upload-{{ $nc->id }}-{{ $fileCategory->value }}-{{ $uploadKey }}" class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300">
                                                        <span class="truncate" title="{{ $entry['file']->getClientOriginalName() }}">{{ $entry['file']->getClientOriginalName() }}</span>
                                                        @if(str_starts_with((string) $entry['file']->getMimeType(), 'image/'))
                                                            <button type="button" wire:click="openNcFileCropper({{ $nc->id }}, '{{ $fileCategory->value }}', '{{ $uploadKey }}')"
                                                                wire:loading.attr="disabled" wire:target="openNcFileCropper({{ $nc->id }}, '{{ $fileCategory->value }}', '{{ $uploadKey }}')"
                                                                class="shrink-0 inline-flex items-center gap-1 text-blue-600 dark:text-blue-400 disabled:opacity-50" title="Crop gambar">
                                                                Crop
                                                                <svg wire:loading wire:target="openNcFileCropper({{ $nc->id }}, '{{ $fileCategory->value }}', '{{ $uploadKey }}')" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                            </button>
                                                        @endif
                                                        <button type="button" wire:click="removeNcUpload({{ $nc->id }}, '{{ $fileCategory->value }}', '{{ $uploadKey }}')"
                                                            wire:loading.attr="disabled" wire:target="removeNcUpload({{ $nc->id }}, '{{ $fileCategory->value }}', '{{ $uploadKey }}')"
                                                            class="shrink-0 inline-flex items-center justify-center w-4 h-4 rounded text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50"
                                                            title="Hapus file dari antrian" aria-label="Hapus file dari antrian">
                                                            <svg wire:loading.class="hidden" wire:target="removeNcUpload({{ $nc->id }}, '{{ $fileCategory->value }}', '{{ $uploadKey }}')" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                            <svg wire:loading wire:target="removeNcUpload({{ $nc->id }}, '{{ $fileCategory->value }}', '{{ $uploadKey }}')" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                        </button>
                                                        <x-input-error :messages="$errors->get('ncUploads.'.$nc->id.'.'.$fileCategory->value.'.'.$uploadKey.'.file')" />
                                                    </div>
                                                @endif
                                            @endforeach
                                            <x-input-error :messages="collect($errors->get('ncUploads.'.$nc->id.'.'.$fileCategory->value.'.*'))->flatten()->all()" />
                                            @if(count(data_get($ncUploads, $nc->id.'.'.$fileCategory->value, [])))
                                                <x-loading-button wire:click="uploadNcFiles({{ $nc->id }}, '{{ $fileCategory->value }}')" target="uploadNcFiles({{ $nc->id }}, '{{ $fileCategory->value }}')"
                                                    size="xs" loadingText="Menyimpan..." class="w-28" x-bind:disabled="uploading">Simpan File</x-loading-button>
                                            @endif
                                        </div>
                                    @endcan
                                </td>
                                @endforeach
                                @if($loop->first)
                                    {{-- Form Temuan = satu per tanggal → sel digabung vertikal per grup --}}
                                    <td wire:key="grp-fo-draft-{{ $laporanId }}" rowspan="{{ $ncs->count() }}" class="px-3 py-3 text-center align-middle border-l border-gray-100 dark:border-gray-700/60 group-hover/date:bg-gray-50 dark:group-hover/date:bg-gray-700/30 transition-colors">
                                        @php $temuanDrafts = $temuanForms->filter(fn ($f) => $f->isDraft())->sortByDesc('id'); @endphp
                                        @if($temuanDrafts->isNotEmpty())
                                            <div class="flex flex-col items-center gap-2">
                                                @foreach($temuanDrafts as $tf)
                                                    <div wire:key="tf-d-{{ $tf->id }}" class="flex flex-col items-center gap-1">
                                                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded {{ $tf->status()->badgeClass() }}"
                                                            title="Draft {{ $tf->nomor }} — upload scan TTD setelah dicetak & ditandatangani">
                                                            @if($tf->isFileProcessing())
                                                                <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                            @endif
                                                            {{ $tf->nomor }}
                                                        </span>
                                                        @include('livewire.laporan-harian.partials.nc-form-actions', ['ncForm' => $tf, 'iconSize' => 'w-3.5 h-3.5'])
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td wire:key="grp-fo-ttd-{{ $laporanId }}" rowspan="{{ $ncs->count() }}" class="px-3 py-3 text-center align-middle border-l border-gray-100 dark:border-gray-700/60 group-hover/date:bg-gray-50 dark:group-hover/date:bg-gray-700/30 transition-colors">
                                        @php $temuanSigneds = $temuanForms->filter(fn ($f) => ! $f->isDraft())->sortByDesc('id'); @endphp
                                        @if($temuanSigneds->isNotEmpty())
                                            <div class="flex flex-col items-center gap-2">
                                                @foreach($temuanSigneds as $tf)
                                                    <div wire:key="tf-s-{{ $tf->id }}" class="flex flex-col items-center gap-1">
                                                        @if($tf->isFileCompleted())
                                                            @can('lampiranPreview', \App\Models\LaporanHarian::class)
                                                                <button type="button" wire:click="previewForm({{ $tf->id }})"
                                                                    wire:loading.attr="disabled" wire:target="previewForm({{ $tf->id }})"
                                                                    class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded {{ $tf->status()->badgeClass() }} hover:underline disabled:opacity-50"
                                                                    title="Preview scan {{ $tf->nomor }}">
                                                                    {{ $tf->nomor }}
                                                                    <svg wire:loading wire:target="previewForm({{ $tf->id }})" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                                </button>
                                                            @else
                                                                <span class="text-[10px] px-1.5 py-0.5 rounded {{ $tf->status()->badgeClass() }}" title="Form Temuan bertanda tangan">{{ $tf->nomor }}</span>
                                                            @endcan
                                                        @else
                                                            <span class="text-[10px] px-1.5 py-0.5 rounded {{ $tf->status()->badgeClass() }}" title="Scan {{ $tf->status()->label() }}">{{ $tf->nomor }}</span>
                                                        @endif
                                                        @include('livewire.laporan-harian.partials.nc-form-actions', ['ncForm' => $tf, 'iconSize' => 'w-3.5 h-3.5'])
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400">-</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="px-3 py-3 text-center">
                                    @if($penutupanDraft)
                                        <div class="flex flex-col items-center gap-1">
                                            <span class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded {{ $penutupanDraft->status()->badgeClass() }}"
                                                title="Draft {{ $penutupanDraft->nomor }} — upload scan TTD untuk menutup NC ini">
                                                @if($penutupanDraft->isFileProcessing())
                                                    <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                @endif
                                                {{ $penutupanDraft->nomor }}
                                            </span>
                                            @include('livewire.laporan-harian.partials.nc-form-actions', ['ncForm' => $penutupanDraft, 'iconSize' => 'w-3.5 h-3.5'])
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center">
                                    @if($penutupanSigned)
                                        @if($nc->isOpen())
                                            {{-- NC di-reopen: penutupan signed lama hanya histori, bukan status aktif --}}
                                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 dark:bg-gray-700/60 dark:text-gray-400 line-through"
                                                title="Pernah ditutup via {{ $penutupanSigned->nomor }} — dibuka kembali{{ $nc->reopened_at ? ' '.$nc->reopened_at->translatedFormat('d M Y') : '' }}{{ $nc->reopenedBy ? ' oleh '.$nc->reopenedBy->name : '' }}{{ $nc->reopen_note ? ': '.$nc->reopen_note : '' }}">
                                                {{ $penutupanSigned->nomor }}
                                            </span>
                                        @else
                                            <div class="flex flex-col items-center gap-1">
                                                @if($penutupanSigned->isFileCompleted())
                                                    @can('lampiranPreview', \App\Models\LaporanHarian::class)
                                                        <button type="button" wire:click="previewForm({{ $penutupanSigned->id }})"
                                                            wire:loading.attr="disabled" wire:target="previewForm({{ $penutupanSigned->id }})"
                                                            class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded {{ $penutupanSigned->status()->badgeClass() }} hover:underline disabled:opacity-50"
                                                            title="Preview scan {{ $penutupanSigned->nomor }}">
                                                            {{ $penutupanSigned->nomor }}
                                                            <svg wire:loading wire:target="previewForm({{ $penutupanSigned->id }})" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                        </button>
                                                    @else
                                                        <span class="text-[10px] px-1.5 py-0.5 rounded {{ $penutupanSigned->status()->badgeClass() }}" title="Form Penutupan bertanda tangan">{{ $penutupanSigned->nomor }}</span>
                                                    @endcan
                                                @else
                                                    <span class="text-[10px] px-1.5 py-0.5 rounded {{ $penutupanSigned->status()->badgeClass() }}" title="Scan {{ $penutupanSigned->status()->label() }}">{{ $penutupanSigned->nomor }}</span>
                                                @endif
                                                @include('livewire.laporan-harian.partials.nc-form-actions', ['ncForm' => $penutupanSigned, 'iconSize' => 'w-3.5 h-3.5'])
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-xs text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @can('update', $nc)
                                        @if($nc->isClosed())
                                            <button wire:click="requestReopen({{ $nc->id }})" wire:loading.attr="disabled" wire:target="requestReopen({{ $nc->id }})"
                                                class="text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300 disabled:opacity-50" title="Buka kembali NC">
                                                <svg wire:loading.class="hidden" wire:target="requestReopen({{ $nc->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                <svg wire:loading wire:target="requestReopen({{ $nc->id }})" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                            </button>
                                        @else
                                            <span class="text-xs text-gray-400" title="NC ditutup lewat Form Penutupan bertanda tangan">—</span>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    @empty
                        <tbody>
                        <tr>
                            <td colspan="12" class="py-12 text-center">
                                <svg class="h-12 w-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Belum ada NC untuk kapal ini.</p>
                            </td>
                        </tr>
                        </tbody>
                    @endforelse
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
            {{ $ncList->links() }}
        </div>
    </div>

    {{-- Modal upload scan Form NC bertanda tangan --}}
    @if($showUploadModal && $this->uploadForm)
        @php $uf = $this->uploadForm; @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto custom-scrollbar" x-data x-init="return () => { document.body.style.overflow = '' }">
            <div class="flex items-center justify-center min-h-screen px-4 py-6">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75" wire:click="closeUploadModal"></div>
                <form wire:submit="saveUpload" class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-2xl z-10">
                    <div class="px-4 py-4 sm:p-6 space-y-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Upload Scan {{ $uf->jenis->label() }} {{ $uf->nomor }}</h3>
                            @if($uf->isPenutupan() && $uf->isDraft())
                                <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-400">Setelah diunggah, {{ $uf->ncs->where('status', \App\Enums\NcStatus::Open)->count() }} NC Open di bawah ini otomatis ditutup.</p>
                            @elseif(! $uf->isDraft())
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Form sudah ditandatangani — file baru akan menggantikan scan sebelumnya.</p>
                            @endif
                        </div>

                        <ul class="max-h-40 overflow-y-auto custom-scrollbar space-y-1.5">
                            @foreach($uf->ncs as $ncInfo)
                                <li wire:key="upnc-{{ $ncInfo->id }}" class="flex items-start gap-2.5 p-2 rounded-lg bg-gray-50 dark:bg-gray-700/40 border border-gray-200 dark:border-gray-700">
                                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-200 whitespace-nowrap">{{ $ncInfo->nomor ?? 'NC' }}</span>
                                    <p class="text-xs text-gray-600 dark:text-gray-300 line-clamp-1 flex-1">{{ $ncInfo->kendala }}</p>
                                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full {{ $ncInfo->status->badgeClass() }}">{{ $ncInfo->status->label() }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <div>
                            <x-input-label for="signedFile" value="Scan Form Bertanda Tangan" :required="true" />
                            <label class="mt-1 flex flex-col items-center justify-center gap-2 w-full px-4 py-6 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:border-blue-400 dark:hover:border-blue-500 transition-colors"
                                wire:loading.class="opacity-60 pointer-events-none" wire:target="signedFile">
                                <svg wire:loading.class="hidden" wire:target="signedFile" class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                <svg wire:loading wire:target="signedFile" class="animate-spin w-8 h-8 text-blue-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                @if(is_object($signedFile))
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $signedFile->getClientOriginalName() }}</span>
                                    <span class="text-xs text-gray-400">{{ number_format($signedFile->getSize() / 1024, 0) }} KB — klik untuk ganti</span>
                                @else
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Klik untuk pilih file — {{ strtoupper(get_allowed_mimes('laporan_nc_form')) }}</span>
                                    <span class="text-xs text-gray-400">Maks. {{ number_format(get_max_upload_size('laporan_nc_form') / 1024, 0) }} MB</span>
                                @endif
                                <input id="signedFile" type="file" wire:model="signedFile" accept=".{{ implode(',.', get_allowed_mimes_array('laporan_nc_form')) }}" class="hidden">
                            </label>
                            <x-input-error :messages="$errors->get('signedFile')" class="mt-1" />
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-900 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2 rounded-b-lg">
                        <x-loading-button type="submit" target="saveUpload" variant="{{ $uf->isPenutupan() && $uf->isDraft() ? 'success' : 'primary' }}" size="lg" loadingText="Mengunggah..." class="w-full sm:w-auto">
                            {{ $uf->isPenutupan() && $uf->isDraft() ? 'Upload & Tutup NC' : 'Upload' }}
                        </x-loading-button>
                        <x-cancel-button wire:click="closeUploadModal" target="closeUploadModal" size="lg" class="mt-3 sm:mt-0 w-full sm:w-auto" />
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Konfirmasi buka kembali NC / hapus draft (event-driven) --}}
    {{-- Modal buka kembali NC: alasan wajib untuk jejak audit --}}
    @if($showReopenModal)
        <div class="fixed inset-0 z-50 overflow-y-auto custom-scrollbar" x-data x-init="return () => { document.body.style.overflow = '' }">
            <div class="flex items-center justify-center min-h-screen px-4 py-6">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75" wire:click="closeReopenModal"></div>
                <form wire:submit="confirmReopen" class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md z-10">
                    <div class="px-4 py-4 sm:p-6 space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Buka Kembali NC</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Status NC kembali Open dan perlu ditutup lagi lewat Form Penutupan baru. Alasan dicatat untuk jejak audit.</p>
                        <div>
                            <x-input-label for="reopen-note" value="Alasan" :required="true" />
                            <textarea id="reopen-note" wire:model="reopenNote" rows="3" placeholder="Mis. salah pilih NC saat generate Form Penutupan..."
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"></textarea>
                            <x-input-error :messages="$errors->get('reopenNote')" class="mt-1" />
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-900 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2 rounded-b-lg">
                        <x-loading-button type="submit" target="confirmReopen" variant="warning" size="lg" loadingText="Memproses..." class="w-full sm:w-auto">
                            Ya, Buka Kembali
                        </x-loading-button>
                        <x-cancel-button wire:click="closeReopenModal" target="closeReopenModal" variant="secondary" size="lg" class="mt-3 sm:mt-0 w-full sm:w-auto" />
                    </div>
                </form>
            </div>
        </div>
    @endif

    <x-delete-modal :show="$showDeleteFileModal" wire:model="showDeleteFileModal" title="Hapus File NC"
        message="File ini dan halaman hasil render akan dihapus permanen. Data NC tetap tersimpan. Lanjutkan?" confirmMethod="deleteNcFile" />

    {{-- Modal daftar file per NC + kategori (ikon folder di sel File) --}}
    @if($filesNcId)
        @php
            $filesNc = $ncList->getCollection()->firstWhere('id', $filesNcId);
            $filesKategoriEnum = \App\Enums\NcFileKategori::tryFrom((string) $filesKategori);
            $modalFiles = $filesNc && $filesKategoriEnum
                ? $filesNc->foto->filter(fn ($file) => $file->kategori === $filesKategoriEnum)
                : collect();
        @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto custom-scrollbar"
            x-data="{ previousOverflow: document.body.style.overflow, init() { document.body.style.overflow = 'hidden'; }, destroy() { document.body.style.overflow = this.previousOverflow; } }"
            x-on:keydown.escape.window="$wire.closeNcFiles()">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75" wire:click="closeNcFiles"></div>
                <div class="inline-block w-full align-bottom bg-white dark:bg-gray-800 rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700 sm:px-5">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                            File {{ $filesKategoriEnum?->label() ?? '' }} — {{ $filesNc?->nomor ?? '-' }}
                        </h3>
                        <button type="button" wire:click="closeNcFiles" wire:loading.attr="disabled" wire:target="closeNcFiles"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors disabled:opacity-50" title="Tutup" aria-label="Tutup">
                            <svg wire:loading.class="hidden" wire:target="closeNcFiles" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            <svg wire:loading wire:target="closeNcFiles" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        </button>
                    </div>
                    <div class="px-4 py-4 sm:px-5 max-h-[60vh] overflow-y-auto custom-scrollbar">
                        <div class="flex flex-wrap justify-center gap-3">
                        @forelse($modalFiles as $f)
                            <div wire:key="nc-modal-file-{{ $f->id }}" class="flex flex-col items-center gap-1 max-w-28">
                                <div class="relative">
                                @if($filesNc)
                                    @can('update', $filesNc)
                                        <button type="button" wire:click="confirmDeleteFile({{ $f->id }})" wire:loading.attr="disabled" wire:target="confirmDeleteFile({{ $f->id }})"
                                            class="absolute -top-1.5 -right-1.5 z-10 inline-flex items-center justify-center w-5 h-5 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 text-red-600 hover:text-white hover:bg-red-600 hover:border-red-600 dark:text-red-400 dark:hover:bg-red-600 dark:hover:border-red-600 shadow-sm transition-colors disabled:opacity-50"
                                            title="Hapus file" aria-label="Hapus file">
                                            <svg wire:loading.class="hidden" wire:target="confirmDeleteFile({{ $f->id }})" class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 6l12 12M18 6L6 18"/></svg>
                                            <svg wire:loading wire:target="confirmDeleteFile({{ $f->id }})" class="animate-spin w-2.5 h-2.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        </button>
                                    @endcan
                                @endif
                                @if($f->isFileProcessing())
                                    <span class="inline-flex w-16 h-16 items-center justify-center rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50" title="Foto sedang diproses">
                                        <svg class="animate-spin w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                                    </span>
                                @elseif($f->isFileFailed())
                                    <span class="inline-flex w-16 h-16 items-center justify-center rounded-lg border border-red-200 dark:border-red-800/60 bg-red-50 dark:bg-red-900/20" title="Gagal memproses foto{{ $f->file_error ? ': '.$f->file_error : '' }}">
                                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </span>
                                @elseif($f->hasFile() && $f->isFileCompleted())
                                    @can('lampiranPreview', \App\Models\LaporanHarian::class)
                                        <button type="button" wire:click="previewFoto({{ $f->id }})"
                                            wire:loading.attr="disabled" wire:target="previewFoto({{ $f->id }})"
                                            class="relative inline-flex w-16 h-16 items-center justify-center rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 hover:border-blue-400 dark:hover:border-blue-500 transition-colors disabled:opacity-50"
                                            title="Preview {{ $f->file_name }}">
                                            @if($f->isImage())
                                                <img src="{{ route('laporan-nc-foto.file', $f) }}" alt="{{ $f->file_name }}" loading="lazy"
                                                    wire:loading.class="hidden" wire:target="previewFoto({{ $f->id }})"
                                                    class="w-full h-full object-cover">
                                            @else
                                                <svg wire:loading.class="hidden" wire:target="previewFoto({{ $f->id }})" class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            @endif
                                            <svg wire:loading wire:target="previewFoto({{ $f->id }})" class="animate-spin w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                                        </button>
                                    @else
                                        <span class="inline-flex w-16 h-16 items-center justify-center rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50" title="Foto bukti tersedia">
                                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 00-2 2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </span>
                                    @endcan
                                @else
                                    <span class="inline-flex w-16 h-16 items-center justify-center rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50" title="Foto belum tersedia">
                                        <svg class="w-6 h-6 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 00-2 2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </span>
                                @endif
                                </div>
                                <span class="text-xs text-gray-500 dark:text-gray-400 truncate w-full" title="{{ $f->file_name }}">{{ $f->file_name }}</span>
                            </div>
                        @empty
                            <span class="py-8 text-xs text-gray-400">Belum ada file.</span>
                        @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
    <x-image-crop-modal :show="$showCropperModal" :imageUrl="$croppingImageUrl" :cropData="$cropData" />
    <x-confirm-modal />

    {{-- Preview foto bukti / scan form --}}
    <x-lampiran-preview-modal :show="$showPreviewModal" :lampiran="$this->previewItem" :laporan="null"
        :imageUrl="$this->previewImageUrl" downloadMethod="downloadPreviewFile" />
</div>
