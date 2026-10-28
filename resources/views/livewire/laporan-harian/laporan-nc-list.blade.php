<div>
    {{-- Toolbar: search + filter popover + export + upload --}}
    <div class="mb-4 flex flex-col lg:flex-row lg:items-center gap-3">
        <div class="flex-1">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari nomor / kendala / PIC..."
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
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
                <x-searchable-select wire:model.live="statusFilter" :options="[['value' => 'open', 'label' => 'Open'], ['value' => 'closed', 'label' => 'Closed']]" placeholder="Semua Status" searchPlaceholder="Cari status..." />
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
            @can('laporan_nc_update')
                @if(count($selectedNcIds) > 0)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">
                        {{ count($selectedNcIds) }} NC dipilih
                    </span>
                    <x-loading-button wire:click="confirmCloseSelected" target="confirmCloseSelected" variant="warning" size="md" loadingText="Memproses..." title="Tutup semua NC terpilih">
                        <x-slot:icon>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </x-slot:icon>
                        Tutup Terpilih
                    </x-loading-button>
                @endif
                <x-loading-button wire:click="openUploadModal" target="openUploadModal" variant="primary" size="md" loadingText="Memuat..." title="Upload file bukti NC">
                    <x-slot:icon>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    </x-slot:icon>
                    Upload File
                </x-loading-button>
            @endcan
        </div>
    </div>

    {{-- Tabel List NC (polling saat ada file yang masih diproses job) --}}
    @php
        $hasProcessing = $ncList->getCollection()->flatMap->files->contains->isFileProcessing();
    @endphp
    <div @if($hasProcessing) wire:poll.4s @endif class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                @php
                    $pageIds = $ncList->pluck('id')->all();
                    $allChecked = $pageIds !== [] && array_diff($pageIds, array_map('intval', $selectedNcIds)) === [];
                @endphp
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 w-10 text-center">
                            @can('laporan_nc_update')
                                <input type="checkbox" wire:click="toggleSelectAll" @checked($allChecked)
                                    class="rounded text-blue-600 focus:ring-blue-500 cursor-pointer" title="Pilih semua di halaman ini">
                            @endcan
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">NC</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Kendala / Tindakan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">PIC</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">File (B/A)</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @php $groupedNcs = $ncList->getCollection()->groupBy('laporan_harian_id'); @endphp
                    @forelse($groupedNcs as $laporanId => $ncs)
                        @php
                            $groupIds = $ncs->pluck('id')->implode(',');
                            $groupAllChecked = $ncs->pluck('id')->diff(array_map('intval', $selectedNcIds))->isEmpty();
                            $laporan = $ncs->first()->laporanHarian;
                            $openCount = $ncs->filter->isOpen()->count();
                        @endphp
                        <tr wire:key="grp-{{ $laporanId }}" class="bg-gray-50 dark:bg-gray-700/40">
                            <td colspan="7" class="px-4 py-2">
                                <div class="flex items-center gap-3">
                                    @can('laporan_nc_update')
                                        <input type="checkbox" wire:click="toggleNcGroup('{{ $groupIds }}')" @checked($groupAllChecked)
                                            class="rounded text-blue-600 focus:ring-blue-500 cursor-pointer" title="Pilih semua NC tanggal ini">
                                    @endcan
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">Laporan {{ $laporan?->tanggal_laporan?->translatedFormat('d M Y') ?? '-' }}</span>
                                    <span class="text-[11px] text-gray-400">{{ $ncs->count() }} NC · {{ $openCount }} open · {{ $ncs->count() - $openCount }} closed</span>
                                </div>
                            </td>
                        </tr>
                        @foreach($ncs as $nc)
                        @php
                            $before = $nc->files->filter(fn ($f) => $f->pivot->kind === 'before');
                            $after = $nc->files->filter(fn ($f) => $f->pivot->kind === 'after');
                        @endphp
                        <tr wire:key="nc-{{ $nc->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors {{ in_array($nc->id, array_map('intval', $selectedNcIds), true) ? 'bg-blue-50/50 dark:bg-blue-900/10' : '' }}">
                            <td class="px-4 py-3 text-center">
                                @can('laporan_nc_update')
                                    <input type="checkbox" wire:click="toggleNcSelect({{ $nc->id }})" @checked(in_array($nc->id, array_map('intval', $selectedNcIds)))
                                        class="rounded text-blue-600 focus:ring-blue-500 cursor-pointer" title="Pilih NC ini">
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
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1 max-w-[220px]">
                                    @foreach($before as $f)
                                        <span wire:key="ncf-b-{{ $f->id }}" class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400">
                                            @if($f->isFileProcessing())
                                                <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                            @elseif($f->isFileFailed())
                                                <svg class="w-3 h-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            @endif
                                            <button type="button" wire:click="previewFile({{ $f->id }})" class="hover:underline" title="Preview">B:{{ \Illuminate\Support\Str::limit($f->file_name, 14) }}</button>
                                            @can('laporan_nc_update')
                                                <button type="button" wire:click="confirmDetach({{ $nc->id }}, {{ $f->id }})" wire:loading.attr="disabled" wire:target="confirmDetach({{ $nc->id }}, {{ $f->id }})" class="text-red-400 hover:text-red-600 disabled:opacity-50" title="Lepas file">×</button>
                                            @endcan
                                        </span>
                                    @endforeach
                                    @foreach($after as $f)
                                        <span wire:key="ncf-a-{{ $f->id }}" class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400">
                                            @if($f->isFileProcessing())
                                                <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                            @elseif($f->isFileFailed())
                                                <svg class="w-3 h-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            @endif
                                            <button type="button" wire:click="previewFile({{ $f->id }})" class="hover:underline" title="Preview">A:{{ \Illuminate\Support\Str::limit($f->file_name, 14) }}</button>
                                            @can('laporan_nc_update')
                                                <button type="button" wire:click="confirmDetach({{ $nc->id }}, {{ $f->id }})" wire:loading.attr="disabled" wire:target="confirmDetach({{ $nc->id }}, {{ $f->id }})" class="text-red-400 hover:text-red-600 disabled:opacity-50" title="Lepas file">×</button>
                                            @endcan
                                        </span>
                                    @endforeach
                                    @if($before->isEmpty() && $after->isEmpty())
                                        <span class="text-xs text-gray-400">-</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-2">
                                    @can('laporan_nc_update')
                                        <button wire:click="toggleStatus({{ $nc->id }})" wire:loading.attr="disabled" wire:target="toggleStatus({{ $nc->id }})"
                                            class="{{ $nc->isOpen() ? 'text-emerald-600 hover:text-emerald-800 dark:text-emerald-400' : 'text-amber-600 hover:text-amber-800 dark:text-amber-400' }} disabled:opacity-50"
                                            title="{{ $nc->isOpen() ? 'Tutup NC (butuh foto After)' : 'Buka kembali NC' }}">
                                            <svg wire:loading.class="hidden" wire:target="toggleStatus({{ $nc->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                @if($nc->isOpen())
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                @else
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                @endif
                                            </svg>
                                            <svg wire:loading wire:target="toggleStatus({{ $nc->id }})" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        </button>
                                        <button wire:click="openUploadModal({{ $nc->id }})" wire:loading.attr="disabled" wire:target="openUploadModal({{ $nc->id }})"
                                            class="text-blue-600 hover:text-blue-800 dark:text-blue-400 disabled:opacity-50" title="Upload / link file ke NC ini">
                                            <svg wire:loading.class="hidden" wire:target="openUploadModal({{ $nc->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                            <svg wire:loading wire:target="openUploadModal({{ $nc->id }})" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center">
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

    {{-- Modal Upload & Link File --}}
    @if($showUploadModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80" wire:click="$set('showUploadModal', false)"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-2xl z-10 px-4 py-4 sm:p-6 space-y-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Upload File Bukti NC</h3>
            <div>
                <x-input-label value="Jenis Foto" :required="true" />
                <div class="mt-1 flex gap-3">
                    @foreach(['before' => 'Before (temuan)', 'after' => 'After (penyelesaian)'] as $val => $label)
                        <label class="inline-flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300">
                            <input type="radio" wire:model.live="uploadKind" value="{{ $val }}" class="text-blue-600 focus:ring-blue-500"> {{ $label }}
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('uploadKind')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="File Bukti (bisa lebih dari satu)" :required="true" />
                <label class="mt-1 flex flex-col items-center justify-center gap-2 w-full px-4 py-6 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl cursor-pointer hover:border-blue-400 dark:hover:border-blue-500 hover:bg-blue-50/40 dark:hover:bg-blue-900/10 transition-colors">
                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Klik untuk pilih file — {{ strtoupper(get_allowed_mimes('laporan_nc')) }}</span>
                    <span class="text-xs text-gray-400">Maks. {{ get_max_upload_size('laporan_nc') }} MB per file</span>
                    <input type="file" wire:model="uploadFiles" multiple accept=".{{ implode(',.', get_allowed_mimes_array('laporan_nc')) }}" class="hidden">
                </label>
                @if($uploadFiles)
                    <ul class="mt-2 space-y-2">
                        @foreach($uploadFiles as $i => $f)
                            @php $isObj = is_object($f); @endphp
                            <li wire:key="upf-{{ $isObj ? $f->getFilename() : $i }}" class="flex items-center gap-3 p-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
                                <div class="w-10 h-10 rounded-lg bg-red-50 dark:bg-red-900/20 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-gray-800 dark:text-gray-200 truncate">{{ $isObj ? $f->getClientOriginalName() : $f }}</p>
                                    <p class="text-[11px] text-gray-400">{{ $isObj ? number_format($f->getSize() / 1024, 1).' KB' : '' }}</p>
                                </div>
                                <button type="button" wire:click="removeUploadFile({{ $i }})"
                                    wire:loading.attr="disabled" wire:target="removeUploadFile({{ $i }})"
                                    class="p-1 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors disabled:opacity-50"
                                    title="Hapus file">
                                    <svg wire:loading.class="hidden" wire:target="removeUploadFile({{ $i }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    <svg wire:loading wire:target="removeUploadFile({{ $i }})" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <x-input-error :messages="$errors->get('uploadFiles')" class="mt-1" />
                <x-input-error :messages="$errors->get('uploadFiles.*')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="NC Tujuan (dari checklist di tabel)" :required="true" />
                @if($selectedNcInfos->isNotEmpty())
                    <ul class="mt-1 max-h-40 overflow-y-auto custom-scrollbar space-y-1.5">
                        @foreach($selectedNcInfos as $ncInfo)
                            <li wire:key="upnc-{{ $ncInfo->id }}" class="flex items-start gap-2.5 p-2 rounded-lg bg-blue-50/60 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-900/40">
                                <svg class="w-4 h-4 mt-0.5 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-blue-700 dark:text-blue-300">{{ $ncInfo->nomor ?? 'NC' }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-300 line-clamp-1">{{ $ncInfo->kendala }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-1.5 text-[11px] text-gray-400 dark:text-gray-500">{{ $selectedNcInfos->count() }} NC dipilih — file akan ditautkan ke semua NC ini.</p>
                @else
                    <div class="mt-1 py-5 px-4 text-center border border-dashed border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-800/60">
                        <svg class="w-7 h-7 mx-auto text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <p class="mt-2 text-xs font-medium text-gray-500 dark:text-gray-400">Belum ada NC dipilih</p>
                        <p class="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">Centang satu atau lebih NC pada kolom checklist di tabel terlebih dahulu.</p>
                    </div>
                @endif
                <x-input-error :messages="$errors->get('uploadNcIds')" class="mt-1" />
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <x-cancel-button wire:click="closeUploadModal" target="closeUploadModal" size="md">Batal</x-cancel-button>
                <x-loading-button wire:click="saveUploads" target="saveUploads" variant="primary" size="md" loadingText="Mengunggah...">Upload</x-loading-button>
            </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Konfirmasi buka kembali NC (event-driven) --}}
    <x-confirm-modal />

    {{-- Preview file --}}
    <x-lampiran-preview-modal :show="$showPreviewModal" :lampiran="$this->previewFile" :laporan="null" :imageUrl="$this->previewFileImageUrl" />
</div>
