<div x-data="{
        draftState: 'idle',
        draftSavedAt: '',
        draftKey: 'dk-draft:{{ auth()->id() }}:{{ $editMode ? 'edit:'.$dataKapal->id : 'create' }}',
        dirty: false,
        flashField(el) {
            if (! this.$wire.autoSave || ! el || ! el.matches('input, textarea, select')) return;
            el.classList.remove('autosave-glow');
            void el.offsetWidth; // restart animasi
            el.classList.add('autosave-glow');
        },
        requestCancel() {
            if (! this.dirty) {
                this.$wire.cancel();
                return;
            }
            this.$dispatch('confirm-modal', {
                title: 'Buang Perubahan?',
                message: 'Ada perubahan yang belum disimpan. Jika dibatalkan, semua isian form akan dibuang.',
                confirmText: 'Ya, Buang',
                cancelText: 'Kembali',
                type: 'warning',
                action: 'cancel',
            });
        }
    }"
    x-on:input="flashField($event.target)"
    x-on:change="flashField($event.target)"
    x-on:dk-clear-draft.window="localStorage.removeItem(draftKey)"
    x-init="
        (function () {
            let props = ['company_id', 'galangan_id', 'nama', 'deskripsi', 'spesifikasi', 'status'];
            let staleBefore = {{ ($editMode && $dataKapal?->updated_at) ? $dataKapal->updated_at->getTimestamp() * 1000 : 0 }};
            let raw = localStorage.getItem(draftKey);
            if (raw && $wire.autoSave) {
                try {
                    let d = JSON.parse(raw);
                    if (d.ts && d.ts < staleBefore) {
                        // Draft lebih tua dari updated_at record — buang
                        localStorage.removeItem(draftKey);
                    } else {
                        props.forEach(function (p) { if (d[p] !== undefined) { $wire.set(p, d[p]); dirty = true; } });
                    }
                } catch (e) {}
            }
            let t;
            let save = function () {
                clearTimeout(t);
                dirty = true;
                if (! $wire.autoSave) { draftState = 'idle'; return; }
                draftState = 'saving';
                t = setTimeout(function () {
                    try {
                        let payload = { ts: Date.now() };
                        props.forEach(function (p) { payload[p] = $wire.get(p); });
                        localStorage.setItem(draftKey, JSON.stringify(payload));
                        draftSavedAt = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                        draftState = 'saved';
                    } catch (e) { draftState = 'idle'; }
                }, 1200);
            };
            props.forEach(function (p) { $wire.$watch(p, save); });
            $wire.$watch('autoSave', function (v) { if (! v) { localStorage.removeItem(draftKey); draftState = 'idle'; } });
        })();
    ">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center gap-2">
            <a href="{{ route('master-data.data-kapal') }}" wire:navigate
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
            <div class="min-w-0">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $editMode ? 'Edit Kapal' : 'Tambah Kapal' }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $editMode ? 'Perbarui data kapal '.$nama : 'Isi form di bawah untuk menambahkan kapal baru' }}
                </p>
            </div>
            {{-- Auto Save toggle + draft indicator (pojok kanan, indikator di bawah toggle) --}}
            <div class="ml-auto flex flex-col items-end gap-1.5 flex-shrink-0">
                <div class="flex items-center gap-2" title="Aktifkan/nonaktifkan penyimpanan draft otomatis di browser">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Auto Save</span>
                    <x-toggle-switch wire:click="toggleAutoSave" :active="$autoSave" target="toggleAutoSave" activeColor="emerald" />
                </div>
                <div class="h-6 flex items-center justify-end">
                <span x-show="draftState === 'saving'" x-cloak
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 text-xs font-medium">
                    <svg class="animate-spin w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Menyimpan draf...
                </span>
                <span x-show="draftState === 'saved'" x-cloak
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 text-xs font-medium"
                    title="Draf form tersimpan otomatis di browser ini">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Draf tersimpan <span x-text="draftSavedAt"></span>
                </span>
                </div>
            </div>
        </div>
    </div>

    <form wire:submit="save" x-on:submit="localStorage.removeItem(draftKey)">
        {{-- Form Card --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Nama Kapal --}}
                    <div>
                        <x-input-label for="nama" value="Nama Kapal" :required="true" />
                        <x-text-input wire:model="nama" id="nama" type="text" required
                            placeholder="Contoh: KMP Ferry Bahari Jaya"
                            class="w-full {{ $errors->has('nama') ? 'border-red-500 dark:border-red-400' : '' }}" />
                        @error('nama') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Status --}}
                    <div>
                        <x-input-label for="status" value="Status" :required="true" />
                        <x-searchable-select
                            wire:model.live="status"
                            :options="collect($statuses)->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->toArray()"
                            placeholder="Pilih Status"
                            searchPlaceholder="Cari status..."
                            :clearable="false"
                            :error="$errors->has('status')"
                        />
                        @error('status') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    {{-- Perusahaan --}}
                    <div>
                        <x-input-label for="company_id" value="Perusahaan" />
                        <x-searchable-select
                            wire:model.live="company_id"
                            :options="collect($companies)->map(fn($c) => ['value' => $c->id, 'label' => $c->name . ' (' . $c->code . ')'])->toArray()"
                            placeholder="Pilih Perusahaan"
                            searchPlaceholder="Cari perusahaan..."
                            :error="$errors->has('company_id')"
                        />
                        @error('company_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    {{-- Galangan --}}
                    <div>
                        <x-input-label for="galangan_id" value="Galangan" />
                        <x-searchable-select
                            wire:model.live="galangan_id"
                            :options="collect($galangans)->map(fn($g) => ['value' => $g->id, 'label' => $g->nama . ' (' . $g->kode . ')'])->toArray()"
                            placeholder="Pilih Galangan"
                            searchPlaceholder="Cari galangan..."
                            :error="$errors->has('galangan_id')"
                        />
                        @error('galangan_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    {{-- Deskripsi --}}
                    <div class="md:col-span-2">
                        <x-input-label for="deskripsi" value="Deskripsi" />
                        <textarea wire:model="deskripsi" id="deskripsi" rows="4"
                            placeholder="Deskripsi singkat kapal (opsional)"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 dark:bg-gray-700 dark:text-white {{ $errors->has('deskripsi') ? 'border-red-500 dark:border-red-400' : '' }}"></textarea>
                        @error('deskripsi') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Spesifikasi Kapal (dinamis) --}}
                    <div class="md:col-span-2">
                        <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between gap-3 mb-3">
                                <div class="min-w-0">
                                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Spesifikasi Kapal</h4>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Item bebas, contoh: Year of Build, Material, Speed Design</p>
                                </div>
                                <button type="button"
                                    wire:click="addSpesifikasi"
                                    wire:loading.attr="disabled"
                                    wire:target="addSpesifikasi"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/30 rounded-lg transition-colors disabled:opacity-50 flex-shrink-0">
                                    <svg wire:loading.class="hidden" wire:target="addSpesifikasi" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    <svg wire:loading wire:target="addSpesifikasi" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>Tambah Item</span>
                                </button>
                            </div>

                            <div class="overflow-x-auto custom-scrollbar rounded-lg border border-gray-200 dark:border-gray-700">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-900">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-10">No</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-2/5">Item</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Keterangan</th>
                                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @forelse($spesifikasi as $index => $row)
                                            <tr wire:key="spk-{{ $row['row_key'] ?? $index }}-{{ $index }}">
                                                <td class="px-3 py-2 text-sm text-center text-gray-500 dark:text-gray-400 align-top">{{ $index + 1 }}</td>
                                                <td class="px-3 py-2 align-top">
                                                    <input type="text" wire:model="spesifikasi.{{ $index }}.item"
                                                        class="w-full px-2 py-1.5 text-sm border rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white {{ $errors->has("spesifikasi.{$index}.item") ? 'border-red-500 dark:border-red-400' : 'border-gray-300 dark:border-gray-600' }}"
                                                        placeholder="Contoh: Year of Build">
                                                    @error("spesifikasi.{$index}.item") <span class="text-red-500 text-xs mt-0.5 block">{{ $message }}</span> @enderror
                                                </td>
                                                <td class="px-3 py-2 align-top">
                                                    <input type="text" wire:model="spesifikasi.{{ $index }}.keterangan"
                                                        class="w-full px-2 py-1.5 text-sm border rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white {{ $errors->has("spesifikasi.{$index}.keterangan") ? 'border-red-500 dark:border-red-400' : 'border-gray-300 dark:border-gray-600' }}"
                                                        placeholder="Contoh: 2015">
                                                    @error("spesifikasi.{$index}.keterangan") <span class="text-red-500 text-xs mt-0.5 block">{{ $message }}</span> @enderror
                                                </td>
                                                <td class="px-3 py-2 text-center align-middle">
                                                    <button type="button"
                                                        wire:click="confirmRemoveSpesifikasi({{ $index }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="confirmRemoveSpesifikasi({{ $index }})"
                                                        class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50" title="Hapus item">
                                                        <svg wire:loading.class="hidden" wire:target="confirmRemoveSpesifikasi({{ $index }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                        </svg>
                                                        <svg wire:loading wire:target="confirmRemoveSpesifikasi({{ $index }})" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="px-3 py-6 text-center text-sm text-gray-400 dark:text-gray-500">Belum ada item spesifikasi. Klik "Tambah Item" untuk menambahkan.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700 rounded-b-lg flex flex-col sm:flex-row-reverse gap-2">
                <x-loading-button type="submit" target="save" variant="primary" size="lg"
                    loadingText="Menyimpan..." class="w-full sm:w-auto">
                    {{ $editMode ? 'Update' : 'Simpan' }}
                </x-loading-button>
                <x-cancel-button x-on:click="requestCancel()" target="cancel" class="w-full sm:w-auto" />
            </div>
        </div>
    </form>

    {{-- Delete Item Spesifikasi Confirmation Modal --}}
    <x-delete-modal
        :show="$showDeleteSpesifikasiModal"
        wire:model="showDeleteSpesifikasiModal"
        title="Hapus Item Spesifikasi"
        message="Apakah Anda yakin ingin menghapus item spesifikasi ini?"
        confirmMethod="removeSpesifikasiConfirmed"
    />

    {{-- Konfirmasi buang perubahan saat Batal --}}
    <x-confirm-modal />
</div>
