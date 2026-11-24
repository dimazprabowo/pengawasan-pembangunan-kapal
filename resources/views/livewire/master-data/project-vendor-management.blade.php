<div class="space-y-4"
    x-data="{ dirtyKeys: {}, get dirty() { return Object.keys(this.dirtyKeys).length > 0 || Object.values($wire.drafts).some(row => row.dirty); } }"
    x-on:input="const row = $event.target.closest('[data-draft-key]'); if (row) dirtyKeys[row.dataset.draftKey] = true;"
    x-on:vendor-row-saved="delete dirtyKeys[$event.detail.key]"
    x-on:click.window.capture="if (dirty) { const button = $event.target.closest('button'); const link = $event.target.closest('a[href]'); if (link || button?.getAttribute('wire:click')?.startsWith('setTab(')) { $event.preventDefault(); $event.stopImmediatePropagation(); $wire.warnUnsaved(); } }"
    x-on:livewire:navigate.window="if (dirty) { $event.preventDefault(); $wire.warnUnsaved(); }"
    x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = ''; }">
    <section class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-5 border-b border-gray-200 dark:border-gray-700 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Vendor</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Edit langsung pada tabel, lalu simpan per baris.</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Data ini tampil di Laporan Bulanan — sub-bab 2.3 "Daftar Sub-Contractor, Vendor" (dikelompokkan per lingkup pekerjaan, kumulatif per bulan).</p>
            </div>
            <svg wire:loading class="animate-spin h-5 w-5 shrink-0 mt-1 text-blue-600 dark:text-blue-400" role="status" aria-label="Memproses data" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>
        </div>
        <div class="p-4 flex flex-col lg:flex-row gap-3">
            <div class="flex-1">
                <x-input-label for="vendor-search" value="Cari vendor" class="sr-only" />
                <x-text-input id="vendor-search" wire:model.live.debounce.300ms="search" placeholder="Cari vendor..." class="w-full" />
            </div>
            <x-filter-popover :filters="[]" :per-page="true"></x-filter-popover>
            @canany(['vendor_lingkups_view'])
                <div class="relative" x-data="{ open: false, dropUp: false, checkPosition() { const r = this.$refs.trigger.getBoundingClientRect(); this.dropUp = window.innerHeight - r.bottom < 160 && r.top > window.innerHeight - r.bottom; } }" x-on:vendor-catalog-opened.window="open = false" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false" x-on:scroll.window.capture="if (open) checkPosition()" x-on:resize.window="if (open) checkPosition()">
                    <x-loading-button x-ref="trigger" x-on:click="checkPosition(); open = !open" variant="secondary" class="w-full lg:w-36 h-10 shrink-0" aria-haspopup="menu" x-bind:aria-expanded="open"><x-slot:icon><x-icon name="cog" class="w-4 h-4" /></x-slot>
Kelola</x-loading-button>
                    <div x-show="open" x-cloak :class="dropUp ? 'bottom-full mb-2' : 'top-full mt-2'" class="absolute right-0 z-30 w-48 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2 shadow-lg" role="menu">
                        @foreach($catalogKinds as $kind)
                            @can($kind->permission('view'))
                                <x-loading-button wire:click="manageCatalog('{{ $kind->value }}')" target="manageCatalog('{{ $kind->value }}')" variant="secondary" class="w-full justify-start my-1" role="menuitem">{{ $kind->label() }}</x-loading-button>
                            @endcan
                        @endforeach
                    </div>
                </div>
            @endcanany
            @can('proyek_data_create')
                <x-loading-button wire:click="addRow" target="addRow" loadingText="Menambah..." class="w-full lg:w-36 h-10 shrink-0">Tambah Baris</x-loading-button>
            @endcan
        </div>
        @if(empty($lingkupOptions))
            <div class="mx-4 mb-4 rounded-lg border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-900/20 px-3 py-2 text-xs text-yellow-700 dark:text-yellow-300">
                Daftar Lingkup Pekerjaan masih kosong — tambahkan melalui menu Kelola agar baris vendor bisa disimpan.
            </div>
        @endif
        @if(empty($bulanOptions))
            <div class="mx-4 mb-4 rounded-lg border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-900/20 px-3 py-2 text-xs text-yellow-700 dark:text-yellow-300">
                Opsi "Bulan ke-" belum tersedia — generate plan Kurva-S pada Data Proyek ini terlebih dahulu agar baris vendor bisa disimpan.
            </div>
        @endif
        <div class="relative overflow-x-auto custom-scrollbar">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <x-sortable-header field="nama" :sort-field="$sortField" :sort-dir="$sortDir" :compact="true">Nama Vendor</x-sortable-header>
                        <x-sortable-header field="lingkup_pekerjaan" :sort-field="$sortField" :sort-dir="$sortDir" :compact="true">Lingkup Pekerjaan</x-sortable-header>
                        <x-sortable-header field="bulan_ke" :sort-field="$sortField" :sort-dir="$sortDir" :compact="true">Bulan ke-</x-sortable-header>
                        <x-sortable-header field="kontak" :sort-field="$sortField" :sort-dir="$sortDir" :compact="true">Kontak</x-sortable-header>
                        <x-sortable-header field="keterangan" :sort-field="$sortField" :sort-dir="$sortDir" :compact="true">Keterangan</x-sortable-header>
                        <th class="px-4 py-3 text-xs uppercase text-gray-500 dark:text-gray-400">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($rows as $key => $row)
                        <tr wire:key="vendor-{{ $key }}" data-draft-key="{{ $key }}" data-unsaved="{{ ($row['dirty'] ?? false) ? 'true' : 'false' }}"
                            x-bind:data-unsaved="Boolean(dirtyKeys[@js($key)] || $wire.drafts[@js($key)]?.dirty)"
                            class="align-middle transition-colors data-[unsaved=true]:bg-yellow-50 dark:data-[unsaved=true]:bg-yellow-900/20 data-[unsaved=false]:hover:bg-gray-50 dark:data-[unsaved=false]:hover:bg-gray-700/30">
                            <td class="p-3 min-w-[210px]">
                                <x-input-label for="nama-{{ $key }}" value="Nama Vendor" :required="true" class="sr-only" />
                                <x-text-input id="nama-{{ $key }}" wire:model.blur="drafts.{{ $key }}.nama" placeholder="Masukkan nama vendor" class="w-full" :disabled="!auth()->user()->can(str_starts_with($key, 'new-') ? 'proyek_data_create' : 'proyek_data_update')" />
                                <x-input-error :messages="$errors->get('drafts.'.$key.'.nama')" />
                            </td>
                            <td class="p-3 min-w-[200px]">
                                <x-input-label for="lingkup-{{ $key }}" value="Lingkup Pekerjaan" :required="true" class="sr-only" />
                                <x-searchable-select id="lingkup-{{ $key }}" wire:model.live="drafts.{{ $key }}.lingkup_id" :options="$lingkupOptions" placeholder="Pilih lingkup pekerjaan" :clearable="false" :floating="true" :disabled="!auth()->user()->can(str_starts_with($key, 'new-') ? 'proyek_data_create' : 'proyek_data_update')" />
                                <x-input-error :messages="$errors->get('drafts.'.$key.'.lingkup_id')" />
                            </td>
                            <td class="p-3 min-w-[180px]">
                                <x-input-label for="bulan-{{ $key }}" value="Bulan ke-" :required="true" class="sr-only" />
                                <x-searchable-select id="bulan-{{ $key }}" wire:model.live="drafts.{{ $key }}.bulan_ke" :options="$bulanOptions" placeholder="Pilih bulan" :clearable="false" :floating="true" :disabled="!auth()->user()->can(str_starts_with($key, 'new-') ? 'proyek_data_create' : 'proyek_data_update')" />
                                <x-input-error :messages="$errors->get('drafts.'.$key.'.bulan_ke')" />
                            </td>
                            <td class="p-3 min-w-[160px]">
                                <x-input-label for="kontak-{{ $key }}" value="Kontak" class="sr-only" />
                                <x-text-input id="kontak-{{ $key }}" wire:model.blur="drafts.{{ $key }}.kontak" placeholder="Mis. 0812xxxx / email" class="w-full" :disabled="!auth()->user()->can(str_starts_with($key, 'new-') ? 'proyek_data_create' : 'proyek_data_update')" />
                                <x-input-error :messages="$errors->get('drafts.'.$key.'.kontak')" />
                            </td>
                            <td class="p-3 min-w-[200px]">
                                <x-input-label for="keterangan-{{ $key }}" value="Keterangan" class="sr-only" />
                                <x-text-input id="keterangan-{{ $key }}" wire:model.blur="drafts.{{ $key }}.keterangan" placeholder="Keterangan tambahan (opsional)" class="w-full" :disabled="!auth()->user()->can(str_starts_with($key, 'new-') ? 'proyek_data_create' : 'proyek_data_update')" />
                                <x-input-error :messages="$errors->get('drafts.'.$key.'.keterangan')" />
                            </td>
                            <td class="p-3 w-px whitespace-nowrap align-middle">
                                <div class="flex items-center justify-center gap-1 min-h-10">
                                    @can(str_starts_with($key, 'new-') ? 'proyek_data_create' : 'proyek_data_update')
                                        <button type="button" wire:click="saveRow('{{ $key }}')" wire:target="saveRow('{{ $key }}')" wire:loading.attr="disabled" title="Simpan baris" aria-label="Simpan baris" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 disabled:opacity-50 focus:ring-2 focus:ring-blue-500">
                                            <svg wire:loading.class="hidden" wire:target="saveRow('{{ $key }}')" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                            <svg wire:loading wire:target="saveRow('{{ $key }}')" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>
                                        </button>
                                        <button type="button" wire:click="cancelRow('{{ $key }}')" wire:target="cancelRow('{{ $key }}')" wire:loading.attr="disabled" title="Batalkan perubahan baris" aria-label="Batalkan perubahan baris" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white disabled:opacity-50 focus:ring-2 focus:ring-blue-500">
                                            <svg wire:loading.class="hidden" wire:target="cancelRow('{{ $key }}')" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                            <svg wire:loading wire:target="cancelRow('{{ $key }}')" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>
                                        </button>
                                    @endcan
                                    @if(!str_starts_with($key, 'new-'))
                                        @can('proyek_data_delete')
                                            <button type="button" wire:click="confirmDelete({{ substr($key, 4) }})" wire:target="confirmDelete({{ substr($key, 4) }})" wire:loading.attr="disabled" title="Hapus vendor ini" aria-label="Hapus vendor ini" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50 focus:ring-2 focus:ring-blue-500">
                                                <svg wire:loading.class="hidden" wire:target="confirmDelete({{ substr($key, 4) }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m-9-9h14M9 7V4h6v3" /></svg>
                                                <svg wire:loading wire:target="confirmDelete({{ substr($key, 4) }})" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>
                                            </button>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-12 text-center text-gray-500 dark:text-gray-400"><x-icon name="document-text" class="w-12 h-12 mx-auto mb-2" />Belum ada data vendor. Gunakan Tambah Baris untuk mulai.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-200 dark:border-gray-700">{{ $items->links() }}</div>
    </section>

    @if($showCatalog && auth()->user()->can($catalogKind->permission('view')))
        <div class="fixed inset-0 z-40 overflow-y-auto custom-scrollbar" x-data="{ previousOverflow: document.body.style.overflow, init() { document.body.style.overflow = 'hidden'; }, destroy() { document.body.style.overflow = this.previousOverflow; } }" x-on:keydown.escape.window="$wire.closeCatalog()" role="dialog" aria-modal="true" aria-labelledby="vendor-catalog-title">
            <div class="fixed inset-0 bg-gray-900/60" x-on:click="$wire.closeCatalog()"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl mx-4 my-8 sm:mx-auto sm:max-w-xl p-5">
                <div class="flex items-center justify-between gap-3 mb-2">
                    <h3 id="vendor-catalog-title" class="text-lg font-semibold text-gray-900 dark:text-white">Kelola {{ $catalogKind->label() }}</h3>
                    @can($catalogKind->permission('create'))
                        <x-loading-button wire:click="newCatalog" target="newCatalog" loadingText="Menyiapkan..." class="w-32"><x-slot:icon><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg></x-slot>
Tambah</x-loading-button>
                    @endcan
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Daftar ini digunakan bersama oleh seluruh kapal.</p>
                <div class="max-h-64 overflow-y-auto custom-scrollbar divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($catalogItems as $catalogItem)
                        <div wire:key="catalog-{{ $catalogEntity }}-{{ $catalogItem->id }}" class="flex items-center justify-between gap-3 py-3 text-gray-900 dark:text-white">
                            <span class="min-w-0 break-words">{{ $catalogItem->label() }}</span>
                            <div class="flex items-center gap-2 shrink-0">
                                @can($catalogKind->permission('update'))
                                    <button type="button" wire:click="editCatalog({{ $catalogItem->id }})" wire:target="editCatalog({{ $catalogItem->id }})" wire:loading.attr="disabled" title="Edit {{ mb_strtolower($catalogKind->label()) }}" aria-label="Edit {{ mb_strtolower($catalogKind->label()) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 disabled:opacity-50 focus:ring-2 focus:ring-blue-500">
                                        <svg wire:loading.class="hidden" wire:target="editCatalog({{ $catalogItem->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 4l4 4M4 20l4-1L20 7a2.828 2.828 0 00-4-4L4 15v5z" /></svg>
                                        <svg wire:loading wire:target="editCatalog({{ $catalogItem->id }})" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>
                                    </button>
                                @endcan
                                @can($catalogKind->permission('delete'))
                                    <button type="button" wire:click="confirmDelete({{ $catalogItem->id }}, '{{ $catalogEntity }}')" wire:target="confirmDelete({{ $catalogItem->id }}, '{{ $catalogEntity }}')" wire:loading.attr="disabled" title="Hapus {{ mb_strtolower($catalogKind->label()) }}" aria-label="Hapus {{ mb_strtolower($catalogKind->label()) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50 focus:ring-2 focus:ring-blue-500">
                                        <svg wire:loading.class="hidden" wire:target="confirmDelete({{ $catalogItem->id }}, '{{ $catalogEntity }}')" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m-9-9h14M9 7V4h6v3" /></svg>
                                        <svg wire:loading wire:target="confirmDelete({{ $catalogItem->id }}, '{{ $catalogEntity }}')" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>
                                    </button>
                                @endcan
                            </div>
                        </div>
                    @empty
                        <div class="py-12 text-center text-gray-500 dark:text-gray-400"><x-icon name="document-text" class="w-12 h-12 mx-auto mb-2" />Belum ada {{ mb_strtolower($catalogKind->label()) }}.</div>
                    @endforelse
                </div>
                <div class="mt-3">{{ $catalogItems->links() }}</div>
                <form wire:submit="saveCatalog" wire:key="catalog-form-{{ $catalogEntity }}-{{ $editingCatalogId ?? 'new' }}" class="mt-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                    @can($catalogKind->permission($editingCatalogId ? 'update' : 'create'))
                        <x-input-label for="catalog-name" :value="($editingCatalogId ? 'Edit ' : 'Tambah ').$catalogKind->label()" :required="true" />
                        <div class="mt-1">
                            <x-text-input id="catalog-name" wire:model.live.debounce.300ms="catalogName" :placeholder="'Masukkan nama '.mb_strtolower($catalogKind->label())" class="w-full" x-on:vendor-catalog-focus.window="$nextTick(() => $el.focus())" />
                            <x-input-error :messages="$errors->get('catalogName')" />
                        </div>
                    @endcan
                    <div class="mt-4 flex items-center justify-end gap-3">
                        <x-cancel-button wire:click="closeCatalog" target="closeCatalog" size="md" loadingText="" class="flex-1 sm:flex-none sm:w-32 h-10" />
                        @can($catalogKind->permission($editingCatalogId ? 'update' : 'create'))
                            <x-loading-button type="submit" target="saveCatalog" size="md" class="flex-1 sm:flex-none sm:w-32 h-10" title="Simpan" aria-label="Simpan">
                                <x-slot:icon><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg></x-slot>
                                Simpan
                            </x-loading-button>
                        @endcan
                    </div>
                </form>
            </div>
        </div>
    @endif
    <x-delete-modal :show="$showDeleteModal" wire:model="showDeleteModal" title="Hapus Data Vendor" message="Hapus data ini?" confirmMethod="delete" />
</div>
