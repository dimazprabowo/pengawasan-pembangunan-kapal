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
                    $pageOpenIds = $ncList->getCollection()->filter->isOpen()->pluck('id')->all();
                    $allChecked = $pageOpenIds !== [] && array_diff($pageOpenIds, $selected) === [];
                @endphp
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th rowspan="2" class="px-4 py-3 w-10 text-center align-middle">
                            @can('create', \App\Models\LaporanNcForm::class)
                                <input type="checkbox" wire:click="toggleSelectAll" @checked($allChecked) @disabled($pageOpenIds === [])
                                    wire:key="nc-select-all-{{ $allChecked ? 'on' : 'off' }}-{{ implode('-', $pageOpenIds) }}"
                                    class="rounded text-blue-600 focus:ring-blue-500 cursor-pointer disabled:opacity-40" title="Pilih semua NC Open di halaman ini">
                            @endcan
                        </th>
                        <th rowspan="2" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 align-middle">NC</th>
                        <th rowspan="2" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 align-middle">Kendala / Tindakan</th>
                        <th rowspan="2" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 align-middle">PIC</th>
                        <th rowspan="2" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 align-middle">Status</th>
                        <th rowspan="2" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 align-middle">Foto</th>
                        <th colspan="2" class="px-4 py-2 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-600">Form</th>
                        <th rowspan="2" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 align-middle">Aksi</th>
                    </tr>
                    <tr>
                        <th class="px-4 py-2 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Open</th>
                        <th class="px-4 py-2 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Close</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($ncList->getCollection()->groupBy('laporan_harian_id') as $laporanId => $ncs)
                        @php
                            $openIds = $ncs->filter(fn ($nc) => $nc->isOpen() && ! $nc->isInDraftPenutupan())->pluck('id');
                            $groupAllChecked = $openIds->isNotEmpty() && $openIds->diff($selected)->isEmpty();
                            $laporan = $ncs->first()->laporanHarian;
                            $temuanForms = $temuanByLaporan->get($laporanId, collect());
                            $temuanDraft = $temuanForms->first(fn ($f) => $f->isDraft());
                        @endphp
                        <tr wire:key="grp-{{ $laporanId }}" class="bg-gray-50 dark:bg-gray-700/40">
                            <td colspan="9" class="px-4 py-2">
                                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        @can('create', \App\Models\LaporanNcForm::class)
                                            <input type="checkbox" wire:click="toggleNcGroup('{{ $openIds->implode(',') }}')" @checked($groupAllChecked) @disabled($openIds->isEmpty())
                                                wire:key="nc-grp-cb-{{ $laporanId }}-{{ $groupAllChecked ? 'on' : 'off' }}"
                                                class="rounded text-blue-600 focus:ring-blue-500 cursor-pointer disabled:opacity-40" title="Pilih semua NC Open tanggal ini">
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
                                $temuan = $nc->forms->filter(fn ($f) => $f->isTemuan())->sortByDesc('id')->first();
                                $penutupan = $nc->forms->filter(fn ($f) => $f->isPenutupan())->sortByDesc('id')->first();
                            @endphp
                            <tr wire:key="nc-{{ $nc->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors {{ $ncChecked ? 'bg-blue-50/50 dark:bg-blue-900/10' : '' }}">
                                <td class="px-4 py-3 text-center">
                                    @can('create', \App\Models\LaporanNcForm::class)
                                        @if($nc->isOpen())
                                            @if($nc->isInDraftPenutupan())
                                                <input type="checkbox" disabled wire:key="nc-cb-{{ $nc->id }}-locked"
                                                    class="rounded text-blue-600 focus:ring-blue-500 opacity-40 cursor-not-allowed"
                                                    title="Sudah ada di draft penutupan {{ $penutupan?->nomor }} — hapus draft itu dulu untuk memindahkan NC">
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
                                <td class="px-4 py-3 text-center">
                                    @if($nc->foto)
                                        @php $f = $nc->foto; @endphp
                                        @if($f->isFileProcessing())
                                            <span class="inline-flex w-11 h-11 items-center justify-center rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50" title="Foto sedang diproses">
                                                <svg class="animate-spin w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                                            </span>
                                        @elseif($f->isFileFailed())
                                            <span class="inline-flex w-11 h-11 items-center justify-center rounded-lg border border-red-200 dark:border-red-800/60 bg-red-50 dark:bg-red-900/20" title="Gagal memproses foto{{ $f->file_error ? ': '.$f->file_error : '' }}">
                                                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            </span>
                                        @elseif($f->hasFile() && $f->isFileCompleted())
                                            @can('lampiranPreview', \App\Models\LaporanHarian::class)
                                                <button type="button" wire:click="previewFoto({{ $f->id }})"
                                                    wire:loading.attr="disabled" wire:target="previewFoto({{ $f->id }})"
                                                    class="relative inline-flex w-11 h-11 items-center justify-center rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 hover:border-blue-400 dark:hover:border-blue-500 transition-colors disabled:opacity-50"
                                                    title="Preview {{ $f->file_name }}">
                                                    @if($f->isImage())
                                                        <img src="{{ route('laporan-nc-foto.file', $f) }}" alt="{{ $f->file_name }}" loading="lazy"
                                                            wire:loading.class="hidden" wire:target="previewFoto({{ $f->id }})"
                                                            class="w-full h-full object-cover">
                                                    @else
                                                        <svg wire:loading.class="hidden" wire:target="previewFoto({{ $f->id }})" class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                    @endif
                                                    <svg wire:loading wire:target="previewFoto({{ $f->id }})" class="animate-spin w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                                                </button>
                                            @else
                                                <span class="inline-flex w-11 h-11 items-center justify-center rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50" title="Foto bukti tersedia">
                                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 00-2 2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                </span>
                                            @endcan
                                        @else
                                            <span class="inline-flex w-11 h-11 items-center justify-center rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50" title="Foto belum tersedia">
                                                <svg class="w-5 h-5 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 00-2 2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-xs text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($temuan)
                                        @if($temuan->isFileCompleted())
                                            @can('lampiranPreview', \App\Models\LaporanHarian::class)
                                                <button type="button" wire:click="previewForm({{ $temuan->id }})"
                                                    wire:loading.attr="disabled" wire:target="previewForm({{ $temuan->id }})"
                                                    class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded {{ $temuan->status()->badgeClass() }} hover:underline disabled:opacity-50"
                                                    title="Preview scan {{ $temuan->nomor }}">
                                                    {{ $temuan->nomor }}
                                                    <svg wire:loading wire:target="previewForm({{ $temuan->id }})" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                </button>
                                            @else
                                                <span class="text-[10px] px-1.5 py-0.5 rounded {{ $temuan->status()->badgeClass() }}" title="Form Temuan bertanda tangan">{{ $temuan->nomor }}</span>
                                            @endcan
                                        @else
                                            <span class="text-[10px] px-1.5 py-0.5 rounded {{ $temuan->status()->badgeClass() }}" title="{{ $temuan->isDraft() ? 'Draft Form Temuan (menunggu TTD)' : 'Scan '.$temuan->status()->label() }}">
                                                {{ $temuan->nomor }}{{ $temuan->isDraft() ? ' (draft)' : '' }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-xs text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($penutupan)
                                        @if($nc->isOpen() && ! $penutupan->isDraft())
                                            {{-- NC di-reopen: penutupan signed lama hanya histori, bukan status aktif --}}
                                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 dark:bg-gray-700/60 dark:text-gray-400 line-through"
                                                title="Pernah ditutup via {{ $penutupan->nomor }} — dibuka kembali{{ $nc->reopened_at ? ' '.$nc->reopened_at->translatedFormat('d M Y') : '' }}{{ $nc->reopenedBy ? ' oleh '.$nc->reopenedBy->name : '' }}{{ $nc->reopen_note ? ': '.$nc->reopen_note : '' }}">
                                                {{ $penutupan->nomor }}
                                            </span>
                                        @elseif($penutupan->isFileCompleted())
                                            @can('lampiranPreview', \App\Models\LaporanHarian::class)
                                                <button type="button" wire:click="previewForm({{ $penutupan->id }})"
                                                    wire:loading.attr="disabled" wire:target="previewForm({{ $penutupan->id }})"
                                                    class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded {{ $penutupan->status()->badgeClass() }} hover:underline disabled:opacity-50"
                                                    title="Preview scan {{ $penutupan->nomor }}">
                                                    {{ $penutupan->nomor }}
                                                    <svg wire:loading wire:target="previewForm({{ $penutupan->id }})" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                </button>
                                            @else
                                                <span class="text-[10px] px-1.5 py-0.5 rounded {{ $penutupan->status()->badgeClass() }}" title="Form Penutupan bertanda tangan">{{ $penutupan->nomor }}</span>
                                            @endcan
                                        @else
                                            <span class="text-[10px] px-1.5 py-0.5 rounded {{ $penutupan->status()->badgeClass() }}" title="{{ $penutupan->isDraft() ? 'Draft Form Penutupan (menunggu TTD)' : 'Scan '.$penutupan->status()->label() }}">
                                                {{ $penutupan->nomor }}{{ $penutupan->isDraft() ? ' (draft)' : '' }}
                                            </span>
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
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center">
                                <svg class="h-12 w-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Belum ada NC untuk kapal ini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
            {{ $ncList->links() }}
        </div>
    </div>

    {{-- Panel Form NC: draft → cetak & TTD → unggah scan --}}
    {{-- Tanpa overflow-hidden: popover filter yang flip ke atas jangan terpotong tepi card --}}
    <div class="mt-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 rounded-t-lg">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Form NC (Non Conformance Report)</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Unduh draft, cetak &amp; tanda tangani, lalu unggah scan-nya. Scan Form Penutupan otomatis menutup semua NC di dalamnya.</p>
        </div>
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row gap-3 sm:items-center">
            <div class="flex-1">
                <input wire:model.live.debounce.300ms="formSearch" type="text" placeholder="Cari nomor form..."
                    class="w-full px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
            </div>
            <x-filter-popover :filters="['formJenisFilter', 'formStatusFilter']" :per-page="true" per-page-model="formPerPage" reset-method="resetFormFilters">
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Jenis</label>
                    <x-searchable-select wire:model.live="formJenisFilter" :options="$formJenisOptions" placeholder="Semua Jenis" searchPlaceholder="Cari jenis..." />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Status</label>
                    <x-searchable-select wire:model.live="formStatusFilter" :options="$formStatusOptions" placeholder="Semua Status" searchPlaceholder="Cari status..." />
                </div>
            </x-filter-popover>
        </div>
        <div class="overflow-x-auto custom-scrollbar rounded-b-lg">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <x-sortable-header field="nomor" :sort-field="$formSortField" :sort-dir="$formSortDir" method="formSortBy" :compact="true">Nomor</x-sortable-header>
                        <x-sortable-header field="jenis" :sort-field="$formSortField" :sort-dir="$formSortDir" method="formSortBy" :compact="true">Jenis</x-sortable-header>
                        <x-sortable-header field="isi" :sort-field="$formSortField" :sort-dir="$formSortDir" method="formSortBy" :compact="true">Isi</x-sortable-header>
                        <x-sortable-header field="status" :sort-field="$formSortField" :sort-dir="$formSortDir" method="formSortBy" :compact="true">Status</x-sortable-header>
                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($formList as $form)
                        <tr wire:key="form-{{ $form->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-4 py-3 text-sm">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $form->nomor }}</div>
                                <div class="text-xs text-gray-400">{{ $form->tanggal?->translatedFormat('d M Y') }} · {{ $form->createdBy?->name }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $form->jenis->badgeClass() }}">{{ $form->jenis->label() }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                {{ $form->ncs_count }} NC
                                @if($form->laporanHarian)
                                    <div class="text-xs text-gray-400">Laporan {{ $form->laporanHarian->tanggal_laporan?->translatedFormat('d M Y') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full {{ $form->status()->badgeClass() }}">
                                    @if($form->isFileProcessing())
                                        <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    @endif
                                    {{ $form->status()->label() }}
                                </span>
                                @if($form->signed_at)
                                    <div class="text-[10px] text-gray-400 mt-0.5">TTD diunggah {{ $form->signed_at->translatedFormat('d M Y') }}{{ $form->signedBy ? ' · '.$form->signedBy->name : '' }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-3">
                                    @can('create', \App\Models\LaporanNcForm::class)
                                        <button wire:click="downloadForm({{ $form->id }})" wire:loading.attr="disabled" wire:target="downloadForm({{ $form->id }})"
                                            class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 disabled:opacity-50" title="Unduh dokumen Word (draft untuk dicetak)">
                                            <svg wire:loading.class="hidden" wire:target="downloadForm({{ $form->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            <svg wire:loading wire:target="downloadForm({{ $form->id }})" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        </button>
                                    @endcan
                                    @can('upload', $form)
                                        <button wire:click="openUploadModal({{ $form->id }})" wire:loading.attr="disabled" wire:target="openUploadModal({{ $form->id }})"
                                            class="{{ $form->isDraft() ? 'text-emerald-600 hover:text-emerald-800 dark:text-emerald-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400' }} disabled:opacity-50"
                                            title="{{ $form->isDraft() ? ($form->isPenutupan() ? 'Upload scan TTD & tutup NC' : 'Upload scan TTD') : 'Ganti scan TTD' }}">
                                            <svg wire:loading.class="hidden" wire:target="openUploadModal({{ $form->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                            <svg wire:loading wire:target="openUploadModal({{ $form->id }})" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        </button>
                                    @endcan
                                    @if($form->isFileCompleted())
                                        @can('lampiranPreview', \App\Models\LaporanHarian::class)
                                            <button wire:click="previewForm({{ $form->id }})" wire:loading.attr="disabled" wire:target="previewForm({{ $form->id }})"
                                                class="text-emerald-600 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300 disabled:opacity-50" title="Preview scan bertanda tangan">
                                                <svg wire:loading.class="hidden" wire:target="previewForm({{ $form->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                <svg wire:loading wire:target="previewForm({{ $form->id }})" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                            </button>
                                        @endcan
                                    @endif
                                    {{-- isDraft dicek eksplisit: Gate::before super admin mem-bypass cek state di Policy --}}
                                    @if($form->isDraft())
                                    @can('delete', $form)
                                        <button wire:click="confirmDeleteForm({{ $form->id }})" wire:loading.attr="disabled" wire:target="confirmDeleteForm({{ $form->id }})"
                                            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50" title="Hapus draft">
                                            <svg wire:loading.class="hidden" wire:target="confirmDeleteForm({{ $form->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            <svg wire:loading wire:target="confirmDeleteForm({{ $form->id }})" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        </button>
                                    @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center">
                                <svg class="h-12 w-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Belum ada Form NC. Generate Form Temuan dari baris tanggal laporan, atau centang NC Open untuk Form Penutupan.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($formList->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $formList->links() }}
            </div>
        @endif
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

    <x-confirm-modal />

    {{-- Preview foto bukti / scan form --}}
    <x-lampiran-preview-modal :show="$showPreviewModal" :lampiran="$this->previewItem" :laporan="null"
        :imageUrl="$this->previewImageUrl" downloadMethod="downloadPreviewFile" />
</div>
