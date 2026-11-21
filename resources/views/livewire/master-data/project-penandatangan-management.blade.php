<div class="space-y-4"
    x-data="{ dirtyKeys: {}, get dirty() { return Object.keys(this.dirtyKeys).length > 0 || Object.values($wire.drafts).some(row => row.dirty); } }"
    x-on:input="const row = $event.target.closest('[data-draft-key]'); if (row) dirtyKeys[row.dataset.draftKey] = true;"
    x-on:ttd-row-saved="delete dirtyKeys[$event.detail.key]"
    x-on:click.window.capture="if (dirty) { const button = $event.target.closest('button'); const link = $event.target.closest('a[href]'); if (link || button?.getAttribute('wire:click')?.startsWith('setTab(')) { $event.preventDefault(); $event.stopImmediatePropagation(); $wire.warnUnsaved(); } }"
    x-on:livewire:navigate.window="if (dirty) { $event.preventDefault(); $wire.warnUnsaved(); }"
    x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = ''; }">
    <section class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-5 border-b border-gray-200 dark:border-gray-700 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Penandatangan</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Edit langsung pada tabel, lalu simpan per baris.</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Data ini tampil di Laporan Bulanan — halaman "LEMBAR PENGESAHAN". Urutan baris = urutan tanda tangan; label kosong diisi otomatis sesuai urutan.</p>
            </div>
            <svg wire:loading class="animate-spin h-5 w-5 shrink-0 mt-1 text-blue-600 dark:text-blue-400" role="status" aria-label="Memproses data" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>
        </div>
        <div class="p-4 flex flex-col lg:flex-row gap-3">
            <div class="flex-1">
                <x-input-label for="penandatangan-search" value="Cari penandatangan" class="sr-only" />
                <x-text-input id="penandatangan-search" wire:model.live.debounce.300ms="search" placeholder="Cari penandatangan..." class="w-full" />
            </div>
            <x-filter-popover :filters="[]" :per-page="true"></x-filter-popover>
            @can('proyek_data_update')
                <x-loading-button wire:click="addRow" target="addRow" loadingText="Menambah..." class="w-full lg:w-44 h-10 shrink-0">Tambah Penandatangan</x-loading-button>
            @endcan
        </div>
        <div class="relative overflow-x-auto custom-scrollbar">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-center text-xs uppercase text-gray-500 dark:text-gray-400 w-10">No</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500 dark:text-gray-400 w-36">Label</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500 dark:text-gray-400">Nama</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500 dark:text-gray-400">Jabatan</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500 dark:text-gray-400">Instansi</th>
                        <th class="px-4 py-3 text-xs uppercase text-gray-500 dark:text-gray-400">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @php $newIndex = 0; @endphp
                    @forelse($rows as $key => $row)
                        @php
                            $isNew = str_starts_with($key, 'new-');
                            $rowNumber = $isNew ? count($positions) + (++$newIndex) : ($positions[substr($key, 4)] ?? '-');
                        @endphp
                        <tr wire:key="ttd-{{ $key }}" data-draft-key="{{ $key }}" data-unsaved="{{ ($row['dirty'] ?? false) ? 'true' : 'false' }}"
                            x-bind:data-unsaved="Boolean(dirtyKeys[@js($key)] || $wire.drafts[@js($key)]?.dirty)"
                            class="align-middle transition-colors data-[unsaved=true]:bg-yellow-50 dark:data-[unsaved=true]:bg-yellow-900/20 data-[unsaved=false]:hover:bg-gray-50 dark:data-[unsaved=false]:hover:bg-gray-700/30">
                            <td class="px-4 py-3 text-sm text-center align-middle text-gray-500 dark:text-gray-400">{{ $rowNumber }}</td>
                            <td class="p-3 min-w-[150px]">
                                <x-input-label for="label-{{ $key }}" value="Label" class="sr-only" />
                                <x-text-input id="label-{{ $key }}" wire:model.blur="drafts.{{ $key }}.label" placeholder="Dipersiapkan" class="w-full" :disabled="!auth()->user()->can('proyek_data_update')" />
                                <x-input-error :messages="$errors->get('drafts.'.$key.'.label')" />
                            </td>
                            <td class="p-3 min-w-[200px]">
                                <x-input-label for="nama-{{ $key }}" value="Nama" :required="true" class="sr-only" />
                                <x-text-input id="nama-{{ $key }}" wire:model.blur="drafts.{{ $key }}.nama" placeholder="Nama lengkap" class="w-full" :disabled="!auth()->user()->can('proyek_data_update')" />
                                <x-input-error :messages="$errors->get('drafts.'.$key.'.nama')" />
                            </td>
                            <td class="p-3 min-w-[180px]">
                                <x-input-label for="jabatan-{{ $key }}" value="Jabatan" class="sr-only" />
                                <x-text-input id="jabatan-{{ $key }}" wire:model.blur="drafts.{{ $key }}.jabatan" placeholder="Mis. Project Manager" class="w-full" :disabled="!auth()->user()->can('proyek_data_update')" />
                                <x-input-error :messages="$errors->get('drafts.'.$key.'.jabatan')" />
                            </td>
                            <td class="p-3 min-w-[180px]">
                                <x-input-label for="instansi-{{ $key }}" value="Instansi" class="sr-only" />
                                <x-text-input id="instansi-{{ $key }}" wire:model.blur="drafts.{{ $key }}.instansi" placeholder="Mis. PT BKI / Owner" class="w-full" :disabled="!auth()->user()->can('proyek_data_update')" />
                                <x-input-error :messages="$errors->get('drafts.'.$key.'.instansi')" />
                            </td>
                            <td class="p-3 w-px whitespace-nowrap align-middle">
                                <div class="flex items-center justify-center gap-1 min-h-10">
                                    @can('proyek_data_update')
                                        <button type="button" wire:click="saveRow('{{ $key }}')" wire:target="saveRow('{{ $key }}')" wire:loading.attr="disabled" title="Simpan baris" aria-label="Simpan baris" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 disabled:opacity-50 focus:ring-2 focus:ring-blue-500">
                                            <svg wire:loading.class="hidden" wire:target="saveRow('{{ $key }}')" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                            <svg wire:loading wire:target="saveRow('{{ $key }}')" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>
                                        </button>
                                        <button type="button" wire:click="cancelRow('{{ $key }}')" wire:target="cancelRow('{{ $key }}')" wire:loading.attr="disabled" title="Batalkan perubahan baris" aria-label="Batalkan perubahan baris" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white disabled:opacity-50 focus:ring-2 focus:ring-blue-500">
                                            <svg wire:loading.class="hidden" wire:target="cancelRow('{{ $key }}')" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                            <svg wire:loading wire:target="cancelRow('{{ $key }}')" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>
                                        </button>
                                    @endcan
                                    @if(!$isNew)
                                        @can('proyek_data_update')
                                            <button type="button" wire:click="confirmDelete('{{ substr($key, 4) }}')" wire:target="confirmDelete('{{ substr($key, 4) }}')" wire:loading.attr="disabled" title="Hapus penandatangan ini" aria-label="Hapus penandatangan ini" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50 focus:ring-2 focus:ring-blue-500">
                                                <svg wire:loading.class="hidden" wire:target="confirmDelete('{{ substr($key, 4) }}')" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m-9-9h14M9 7V4h6v3" /></svg>
                                                <svg wire:loading wire:target="confirmDelete('{{ substr($key, 4) }}')" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>
                                            </button>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-12 text-center text-gray-500 dark:text-gray-400"><x-icon name="document-text" class="w-12 h-12 mx-auto mb-2" />{{ $search !== '' ? 'Tidak ada penandatangan yang cocok dengan pencarian.' : 'Belum ada penandatangan. Gunakan Tambah Penandatangan untuk mulai.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-200 dark:border-gray-700">{{ $items->links() }}</div>
    </section>

    <x-delete-modal :show="$showDeleteModal" wire:model="showDeleteModal" title="Hapus Penandatangan" message="Apakah Anda yakin ingin menghapus penandatangan ini?" confirmMethod="delete" />
</div>
