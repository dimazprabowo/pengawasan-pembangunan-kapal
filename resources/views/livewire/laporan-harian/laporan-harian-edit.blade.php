<div x-data="{
        draftState: 'idle',
        draftSavedAt: '',
        draftKey: 'lh-draft-v2:{{ auth()->id() }}:edit:{{ $laporan->id }}',
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
    x-on:lh-clear-draft.window="localStorage.removeItem(draftKey)"
    x-init="
        (function () {
            let props = ['tanggal_laporan', 'suhu', 'cuaca_pagi_id', 'kelembaban_pagi_id', 'cuaca_siang_id', 'kelembaban_siang_id', 'cuaca_sore_id', 'kelembaban_sore_id', 'personel', 'peralatan', 'consumable', 'aktivitas'];
            let raw = localStorage.getItem(draftKey);
            if (raw && $wire.autoSave) {
                try {
                    let d = JSON.parse(raw);
                    if (d.ts && d.ts < {{ $laporan->updated_at->getTimestamp() * 1000 }}) {
                        localStorage.removeItem(draftKey);
                    } else {
                        props.forEach(function (p) { if (d[p] !== undefined) { $wire.set(p, d[p]); dirty = true; } });
                        if (d.foto) { $wire.set('foto', d.foto); }
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
                        // File tidak bisa disimpan di draft — hanya struktur slot foto per row_key
                        payload.foto = {};
                        Object.keys($wire.get('foto') || {}).forEach(function (k) { payload.foto[k] = { file: null, cropData: null, is_cropped: false }; });
                        localStorage.setItem(draftKey, JSON.stringify(payload));
                        draftSavedAt = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                        draftState = 'saved';
                    } catch (e) { draftState = 'idle'; }
                }, 1200);
            };
            props.forEach(function (p) { $wire.$watch(p, save); });

            $wire.$watch('autoSave', function (v) { if (! v) { localStorage.removeItem(draftKey); draftState = 'idle'; } });
        })();
    "
    @if($this->hasProcessingLampiran())
        wire:poll.4s="refreshLampiranStatus"
    @endif
>
    {{-- Header --}}
    <div class="mb-6">
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
            <div class="min-w-0">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Laporan Harian</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 truncate">{{ $dataKapal->nama }}{{ $dataKapal->company ? ' · ' . $dataKapal->company->name : '' }} — Perbarui data laporan di bawah ini</p>
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
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
            {{-- Card Header --}}
            <div class="px-5 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Detail Laporan</h3>
                    <span class="text-xs text-gray-400 dark:text-gray-500">
                        Dibuat {{ $laporan->created_at->translatedFormat('d M Y H:i') }}
                    </span>
                </div>
            </div>

            {{-- Card Body --}}
            <div class="p-5">
                @php
                    $nonEmptyRows = fn ($rows) => collect($rows ?? [])->filter(fn ($r) => is_array($r) && array_filter($r))->count();
                    $sectionMeta = [
                        'cuaca' => ['label' => 'Cuaca', 'icon' => 'M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999A5.002 5.002 0 103 15z', 'count' => 0, 'error' => $errors->has('suhu') || $errors->has('cuaca_*') || $errors->has('kelembaban_*')],
                        'personel' => ['label' => 'Personel', 'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z', 'count' => $nonEmptyRows($personel), 'error' => $errors->has('personel.*')],
                        'peralatan' => ['label' => 'Peralatan', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z', 'count' => $nonEmptyRows($peralatan), 'error' => $errors->has('peralatan.*')],
                        'consumable' => ['label' => 'Consumable', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', 'count' => $nonEmptyRows($consumable), 'error' => $errors->has('consumable.*')],
                        'aktivitas' => ['label' => 'Aktivitas', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-8h.01M9 16h.01', 'count' => $nonEmptyRows($aktivitas), 'error' => $errors->has('aktivitas.*')],
                        'lampiran' => ['label' => 'Lampiran', 'icon' => 'M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13', 'count' => $lampiranByAktivitas->count() + collect($foto)->filter(fn ($f) => ! empty($f['file']))->count(), 'error' => $errors->has('foto.*')],
                    ];
                    $defaultSection = 'cuaca';
                    foreach ($sectionMeta as $secKey => $meta) {
                        if ($meta['error']) { $defaultSection = $secKey; break; }
                    }
                    $sectKey = 'lh-sect:'.auth()->id().':edit:'.$laporan->id;
                    $sectionInit = $errors->any() ? "'{$defaultSection}'" : "(localStorage.getItem('{$sectKey}') || '{$defaultSection}')";
                @endphp
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4"
                    x-data="{ section: {{ $sectionInit }} }"
                    x-init="$watch('section', function (v) { localStorage.setItem('{{ $sectKey }}', v); })">
                    {{-- Tanggal sudah digenerate saat laporan dibuat — tampilkan sebagai info, bukan input --}}
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ \App\Models\LaporanHarian::generateJudul($tanggal_laporan) }}
                        </p>
                        @error('tanggal_laporan') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Section Sub-Tabs --}}
                    <div class="md:col-span-2 mt-2">
                        <div class="flex gap-1 overflow-x-auto custom-scrollbar bg-gray-100 dark:bg-gray-900/60 rounded-xl p-1">
                            @foreach($sectionMeta as $secKey => $meta)
                                <button type="button" @click="section = '{{ $secKey }}'; $nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' }))"
                                    :class="section === '{{ $secKey }}' ? 'bg-white dark:bg-gray-800 shadow-sm text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap transition-all">
                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $meta['icon'] }}"/></svg>
                                    <span>{{ $meta['label'] }}</span>
                                    @if($meta['count'] > 0)
                                        <span class="min-w-[18px] px-1 rounded-full bg-gray-200 dark:bg-gray-700 text-[10px] leading-4 text-center">{{ $meta['count'] }}</span>
                                    @endif
                                    @if($meta['error'])
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500" title="Ada kolom belum valid di section ini"></span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>

                        {{-- A. Kondisi Cuaca --}}
                        <div class="md:col-span-2 mt-4" x-show="section === 'cuaca'" x-cloak>
                            <x-laporan.cuaca-section
                                wirePrefix=""
                                :cuacaList="$cuacaList->map(fn($c) => ['value' => $c->id, 'label' => $c->nama])->toArray()"
                                :kelembabanList="$kelembabanList->map(fn($k) => ['value' => $k->id, 'label' => $k->nama . ($k->nilai ? ' (' . $k->nilai . ')' : '')])->toArray()"
                            />
                        </div>

                        {{-- B. Personel --}}
                        <div class="md:col-span-2 mt-4" x-show="section === 'personel'" x-cloak>
                            <x-laporan.personel-table
                                :rows="$personel"
                                rowsModel="personel"
                                addMethod="addPersonel"
                                deletePrefix="confirmRemovePersonel("
                                wireKeyPrefix="personel"
                            />
                        </div>

                        {{-- C. Peralatan --}}
                        <div class="md:col-span-2 mt-4" x-show="section === 'peralatan'" x-cloak>
                            <x-laporan.peralatan-table
                                :rows="$peralatan"
                                rowsModel="peralatan"
                                addMethod="addPeralatan"
                                deletePrefix="confirmRemovePeralatan("
                                wireKeyPrefix="peralatan"
                            />
                        </div>

                        {{-- D. Consumable dan Material --}}
                        <div class="md:col-span-2 mt-4" x-show="section === 'consumable'" x-cloak>
                            <x-laporan.consumable-table
                                :rows="$consumable"
                                rowsModel="consumable"
                                addMethod="addConsumable"
                                deletePrefix="confirmRemoveConsumable("
                                wireKeyPrefix="consumable"
                            />
                        </div>

                        {{-- E. Uraian Aktivitas --}}
                        <div class="md:col-span-2 mt-4" x-show="section === 'aktivitas'" x-cloak>
                            <x-laporan.aktivitas-kategori-section
                                :groups="$kategoriGroups"
                                rowsModel="aktivitas"
                                addPrefix="addAktivitas("
                                deletePrefix="confirmRemoveAktivitas("
                                wireKeyPrefix="aktivitas"
                                persistKey="lh-kat:edit:{{ $laporan->id }}"
                            />
                        </div>

                    {{-- F. Lampiran — kartu foto digenerate dari uraian aktivitas --}}
                    <div class="md:col-span-2 mt-4" x-show="section === 'lampiran'" x-cloak>
                        <x-laporan.lampiran-aktivitas-grid
                            :groups="$kategoriGroups"
                            fotoModel="foto"
                            :fotos="$foto"
                            :existing="$lampiranByAktivitas"
                        />
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer Actions --}}
        <div class="flex flex-col sm:flex-row items-center justify-end gap-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 px-5 py-4">
            <x-cancel-button x-on:click="requestCancel()" target="cancel"
                wire:loading.attr="disabled" wire:target="cancel" class="w-full sm:w-auto" />
            <x-loading-button type="submit" target="save" variant="primary" size="lg"
                loadingText="Menyimpan..." class="w-full sm:w-auto">
                Update Laporan
            </x-loading-button>
        </div>
    </form>

    {{-- Delete Lampiran Confirmation Modal --}}
    <x-delete-modal
        :show="$showDeleteLampiranModal"
        wire:model="showDeleteLampiranModal"
        title="Hapus Foto Lampiran"
        message="Foto tersimpan akan dihapus permanen. Uraian aktivitasnya tetap ada. Lanjutkan?"
        confirmMethod="deleteLampiran"
    />

    {{-- Delete Personel Confirmation Modal --}}
    <x-delete-modal
        :show="$showDeletePersonelModal"
        wire:model="showDeletePersonelModal"
        title="Hapus Personel"
        message="Apakah Anda yakin ingin menghapus data personel ini?"
        confirmMethod="removePersonelConfirmed"
    />

    {{-- Delete Peralatan Confirmation Modal --}}
    <x-delete-modal
        :show="$showDeletePeralatanModal"
        wire:model="showDeletePeralatanModal"
        title="Hapus Peralatan"
        message="Apakah Anda yakin ingin menghapus data peralatan ini?"
        confirmMethod="removePeralatanConfirmed"
    />

    {{-- Delete Consumable Confirmation Modal --}}
    <x-delete-modal
        :show="$showDeleteConsumableModal"
        wire:model="showDeleteConsumableModal"
        title="Hapus Consumable"
        message="Apakah Anda yakin ingin menghapus data consumable ini?"
        confirmMethod="removeConsumableConfirmed"
    />

    {{-- Delete Aktivitas Confirmation Modal --}}
    <x-delete-modal
        :show="$showDeleteAktivitasModal"
        wire:model="showDeleteAktivitasModal"
        title="Hapus Aktivitas"
        :message="$this->deletingAktivitasHasFoto
            ? 'Aktivitas ini memiliki foto lampiran yang juga akan terhapus saat laporan disimpan. Lanjutkan menghapus?'
            : 'Apakah Anda yakin ingin menghapus data aktivitas ini?'"
        confirmMethod="removeAktivitasConfirmed"
    />

    {{-- Image Cropper Modal --}}
    <x-image-crop-modal 
        :show="$showCropperModal" 
        :imageUrl="$croppingImageUrl" 
        :cropData="$cropData"
        :previewTarget="$croppingRowKey ? 'foto.'.$croppingRowKey.'.preview' : null"
    />

    {{-- Lampiran Preview Modal --}}
    <x-lampiran-preview-modal
        :show="$showPreviewModal"
        :lampiran="$this->previewLampiran"
        :laporan="$laporan"
        :imageUrl="$this->previewLampiranImageUrl"
    />

    {{-- Konfirmasi buang perubahan saat Batal --}}
    <x-confirm-modal />
</div>
