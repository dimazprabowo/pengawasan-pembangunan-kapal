<div x-data="{
        draftState: 'idle',
        draftSavedAt: '',
        draftKey: 'lh-draft-v2:{{ auth()->id() }}:create:{{ $dataKapal->id }}',
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
            let raw = localStorage.getItem(draftKey);
            if (raw && $wire.autoSave) {
                try {
                    let d = JSON.parse(raw);
                    if (Array.isArray(d.items) && d.items.length) { $wire.set('items', d.items); dirty = true; }
                    if (d.lampiran) { $wire.set('lampiran', d.lampiran); dirty = true; }
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
                        let lampiranClean = ($wire.get('lampiran') || []).map(function (row) {
                            let out = {};
                            Object.keys(row || {}).forEach(function (k) { out[k] = { file: null, cropData: null, is_cropped: false }; });
                            return out;
                        });
                        localStorage.setItem(draftKey, JSON.stringify({ ts: Date.now(), items: $wire.get('items'), lampiran: lampiranClean }));
                        draftSavedAt = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                        draftState = 'saved';
                    } catch (e) { draftState = 'idle'; }
                }, 1200);
            };
            $wire.$watch('items', save);
            $wire.$watch('lampiran', save);
            $wire.$watch('autoSave', function (v) { if (! v) { localStorage.removeItem(draftKey); draftState = 'idle'; } });
        })();
    ">
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
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Tambah Laporan Harian</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 truncate">
                    {{ $dataKapal->nama }}{{ $dataKapal->company ? ' · ' . $dataKapal->company->name : '' }} — setiap tab adalah satu hari; tanggal otomatis mengikuti hari sebelumnya
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
        {{-- Tabs + Panels (switch murni Alpine; panel tetap di DOM agar wire:model tidak stale) --}}
        <div x-data="{ activeTab: $wire.entangle('activeTab').live }"
             x-init="
                (function () {
                    let s = parseInt(localStorage.getItem('lh-tab:{{ auth()->id() }}:create:{{ $dataKapal->id }}'));
                    if (! isNaN(s) && s !== {{ $activeTab }}) { $wire.set('activeTab', s); }
                    $watch('activeTab', function (v) { localStorage.setItem('lh-tab:{{ auth()->id() }}:create:{{ $dataKapal->id }}', v); });
                })();
             "
             x-on:laporan-item-added.window="$nextTick(() => { $el.querySelector('[data-tab=\'' + $event.detail.index + '\']')?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' }); })"
             x-on:laporan-item-removed.window="$nextTick(() => { $el.querySelector('[data-tab=\'' + activeTab + '\']')?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' }); })">
            {{-- Tab Bar (day stepper cards) --}}
            <div class="flex items-stretch gap-2 overflow-x-auto custom-scrollbar pb-1 mb-4">
                @foreach($items as $index => $item)
                    @php
                        $tglLabel = 'Tanggal belum diisi';
                        $hariLabel = '';
                        try {
                            if (! empty($item['tanggal_laporan'])) {
                                $tgl = \Carbon\Carbon::parse($item['tanggal_laporan'])->locale('id');
                                $tglLabel = $tgl->isoFormat('D MMM Y');
                                $hariLabel = $tgl->isoFormat('dddd');
                            }
                        } catch (\Throwable $e) {}
                    @endphp
                    <div wire:key="tab-{{ $index }}" data-tab="{{ $index }}"
                        class="flex items-center rounded-xl border-2 transition-all duration-150 {{ ($errors->has("items.{$index}.*") || $errors->has("lampiran.{$index}.*")) ? 'ring-1 ring-red-300 dark:ring-red-700' : '' }}"
                        :class="activeTab === {{ $index }}
                            ? 'border-blue-500 bg-blue-50/70 dark:bg-blue-900/25 shadow-sm'
                            : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 hover:border-blue-300 dark:hover:border-blue-600 hover:bg-blue-50/40 dark:hover:bg-blue-900/10'">
                        <button type="button" @click="activeTab = {{ $index }}; $nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' }))"
                            class="flex items-center gap-2.5 pl-3 pr-3 py-2 whitespace-nowrap text-left">
                            <span class="flex items-center justify-center w-7 h-7 rounded-lg text-xs font-bold transition-colors"
                                :class="activeTab === {{ $index }} ? 'bg-blue-600 text-white shadow-sm' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300'">
                                {{ $index + 1 }}
                            </span>
                            <span class="flex flex-col leading-tight">
                                <span class="text-sm font-semibold" :class="activeTab === {{ $index }} ? 'text-blue-700 dark:text-blue-300' : 'text-gray-700 dark:text-gray-300'">{{ $tglLabel }}</span>
                                <span class="text-[11px]" :class="activeTab === {{ $index }} ? 'text-blue-500 dark:text-blue-400' : 'text-gray-400 dark:text-gray-500'">{{ $hariLabel ?: 'Hari ke-' . ($index + 1) }}</span>
                            </span>
                            @if($errors->has("items.{$index}.*") || $errors->has("lampiran.{$index}.*"))
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 self-start mt-1" title="Ada kolom belum valid di laporan ini"></span>
                            @endif
                        </button>
                        @if(count($items) > 1)
                            <button type="button" wire:click="confirmRemoveItem({{ $index }})"
                                wire:loading.attr="disabled"
                                wire:target="confirmRemoveItem({{ $index }})"
                                class="pr-2.5 text-gray-400 hover:text-red-500 dark:hover:text-red-400 transition-colors disabled:opacity-50"
                                title="Hapus laporan ini">
                                <svg wire:loading.class="hidden" wire:target="confirmRemoveItem({{ $index }})" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                <svg wire:loading wire:target="confirmRemoveItem({{ $index }})" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </button>
                        @endif
                    </div>
                @endforeach
                <button type="button" wire:click="addItem"
                    wire:loading.attr="disabled"
                    wire:target="addItem"
                    class="flex items-center gap-2 px-3.5 py-2 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 text-xs font-medium text-gray-500 dark:text-gray-400 hover:border-blue-400 hover:text-blue-500 dark:hover:border-blue-500 dark:hover:text-blue-400 transition-colors whitespace-nowrap disabled:opacity-50"
                    title="Tambah laporan — otomatis mengisi tanggal kosong terdekat">
                    <span class="flex items-center justify-center w-6 h-6 rounded-lg border border-dashed border-current">
                        <svg wire:loading.class="hidden" wire:target="addItem" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <svg wire:loading wire:target="addItem" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                    <span class="flex flex-col leading-tight text-left">
                        <span class="font-semibold">Tambah Hari</span>
                        <span class="text-[10px] opacity-75">-1 hari dari tanggal terakhir</span>
                    </span>
                </button>
            </div>

            {{-- Panels --}}
            <div class="mb-6">
            @foreach($items as $index => $item)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700"
                     x-show="activeTab === {{ $index }}"
                     x-cloak
                     wire:key="item-{{ $index }}">

                    {{-- Card Body --}}
                    <div class="p-5">
                        @php
                            $nonEmptyRows = fn ($rows) => collect($rows ?? [])->filter(fn ($r) => is_array($r) && array_filter($r))->count();
                            $sectionMeta = [
                                'cuaca' => ['label' => 'Cuaca', 'icon' => 'M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999A5.002 5.002 0 103 15z', 'count' => 0, 'error' => $errors->has("items.{$index}.suhu") || $errors->has("items.{$index}.cuaca_*") || $errors->has("items.{$index}.kelembaban_*")],
                                'personel' => ['label' => 'Personel', 'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z', 'count' => $nonEmptyRows($item['personel'] ?? []), 'error' => $errors->has("items.{$index}.personel.*")],
                                'peralatan' => ['label' => 'Peralatan', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z', 'count' => $nonEmptyRows($item['peralatan'] ?? []), 'error' => $errors->has("items.{$index}.peralatan.*")],
                                'consumable' => ['label' => 'Consumable', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', 'count' => $nonEmptyRows($item['consumable'] ?? []), 'error' => $errors->has("items.{$index}.consumable.*")],
                                'aktivitas' => ['label' => 'Aktivitas', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-8h.01M9 16h.01', 'count' => $nonEmptyRows($item['aktivitas'] ?? []), 'error' => $errors->has("items.{$index}.aktivitas.*")],
                                'lampiran' => ['label' => 'Lampiran', 'icon' => 'M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13', 'count' => collect($lampiran[$index] ?? [])->filter(fn ($l) => ! empty($l['file']))->count(), 'error' => $errors->has("lampiran.{$index}.*")],
                                'nc' => ['label' => 'NC', 'icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z', 'count' => $nonEmptyRows($item['nc'] ?? []), 'error' => $errors->has("items.{$index}.nc.*")],
                            ];
                            $defaultSection = 'cuaca';
                            foreach ($sectionMeta as $secKey => $meta) {
                                if ($meta['error']) { $defaultSection = $secKey; break; }
                            }
                            $sectKey = 'lh-sect:'.auth()->id().':create:'.$dataKapal->id.':'.($item['tanggal_laporan'] ?? $index);
                            $sectionInit = $errors->any() ? "'{$defaultSection}'" : "(localStorage.getItem('{$sectKey}') || '{$defaultSection}')";
                        @endphp
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4"
                            x-data="{ section: {{ $sectionInit }} }"
                            x-init="$watch('section', function (v) { localStorage.setItem('{{ $sectKey }}', v); })">
                            {{-- Tanggal digenerate otomatis (tampil di header tab hari) --}}
                            <div class="md:col-span-2">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ \App\Models\LaporanHarian::generateJudul($item['tanggal_laporan'] ?? null, $this->dataKapal) }}
                                </p>
                                @error("items.{$index}.tanggal_laporan") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
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
                                        :wirePrefix="'items.' . $index"
                                        :cuacaList="$cuacaList->map(fn($c) => ['value' => $c->id, 'label' => $c->nama])->toArray()"
                                        :kelembabanList="$kelembabanList->map(fn($k) => ['value' => $k->id, 'label' => $k->nama . ($k->nilai ? ' (' . $k->nilai . ')' : '')])->toArray()"
                                    />
                                </div>

                                {{-- B. Personel --}}
                                <div class="md:col-span-2 mt-4" x-show="section === 'personel'" x-cloak>
                                    <x-laporan.personel-table
                                        :rows="$item['personel'] ?? []"
                                        rowsModel="items.{{ $index }}.personel"
                                        addMethod="addPersonel({{ $index }})"
                                        deletePrefix="confirmRemovePersonel({{ $index }}, "
                                        wireKeyPrefix="personel-{{ $index }}"
                                    />
                                </div>

                                {{-- C. Peralatan --}}
                                <div class="md:col-span-2 mt-4" x-show="section === 'peralatan'" x-cloak>
                                    <x-laporan.peralatan-table
                                        :rows="$item['peralatan'] ?? []"
                                        rowsModel="items.{{ $index }}.peralatan"
                                        addMethod="addPeralatan({{ $index }})"
                                        deletePrefix="confirmRemovePeralatan({{ $index }}, "
                                        wireKeyPrefix="peralatan-{{ $index }}"
                                    />
                                </div>

                                {{-- D. Consumable dan Material --}}
                                <div class="md:col-span-2 mt-4" x-show="section === 'consumable'" x-cloak>
                                    <x-laporan.consumable-table
                                        :rows="$item['consumable'] ?? []"
                                        rowsModel="items.{{ $index }}.consumable"
                                        addMethod="addConsumable({{ $index }})"
                                        deletePrefix="confirmRemoveConsumable({{ $index }}, "
                                        wireKeyPrefix="consumable-{{ $index }}"
                                    />
                                </div>

                                {{-- D2. Non-Conformity (NC) --}}
                                <div class="md:col-span-2 mt-4" x-show="section === 'nc'" x-cloak wire:key="panel-nc-{{ $index }}">
                                    <x-laporan.nc-kategori-section
                                        :groups="$ncGroups[$index] ?? []"
                                        wirePrefix="items.{{ $index }}.nc"
                                        argsPrefix="{{ $index }}, "
                                        addPrefix="addNc({{ $index }}, "
                                        wireKeyPrefix="nc-{{ $index }}"
                                        persistKey="lh-nc-kat:create-{{ $index }}"
                                    />
                                </div>

                                {{-- E. Uraian Aktivitas --}}
                                <div class="md:col-span-2 mt-4" x-show="section === 'aktivitas'" x-cloak>
                                    <x-laporan.aktivitas-kategori-section
                                        :groups="$kategoriGroups[$index]"
                                        rowsModel="items.{{ $index }}.aktivitas"
                                        addPrefix="addAktivitas({{ $index }}, "
                                        deletePrefix="confirmRemoveAktivitas({{ $index }}, "
                                        wireKeyPrefix="aktivitas-{{ $index }}"
                                        persistKey="lh-kat:create:{{ $data_kapal_id }}-{{ $index }}"
                                    />
                                </div>

                            {{-- F. Lampiran — kartu foto digenerate dari uraian aktivitas --}}
                            <div class="md:col-span-2 mt-4" x-show="section === 'lampiran'" x-cloak>
                                <x-laporan.lampiran-aktivitas-grid
                                    :groups="$kategoriGroups[$index]"
                                    fotoModel="lampiran.{{ $index }}"
                                    :fotos="$lampiran[$index] ?? []"
                                    argsPrefix="{{ $index }}, "
                                />
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
            </div>
        </div>

        {{-- Footer Actions --}}
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 px-5 py-4">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Total: <span class="font-semibold text-gray-900 dark:text-white">{{ count($items) }}</span> laporan akan ditambahkan
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <x-cancel-button x-on:click="requestCancel()" target="cancel"
                    wire:loading.attr="disabled" wire:target="cancel" class="w-full sm:w-auto" />
                <x-loading-button type="submit" target="save" variant="primary" size="lg"
                    loadingText="Menyimpan..." class="w-full sm:w-auto">
                    Simpan Semua
                </x-loading-button>
            </div>
        </div>
    </form>

    {{-- Delete Card Confirmation Modal --}}
    <x-delete-modal
        :show="$showDeleteCardModal"
        wire:model="showDeleteCardModal"
        title="Hapus Laporan"
        message="Apakah Anda yakin ingin menghapus card laporan"
        :itemName="$deletingCardIndex !== null ? '#' . ($deletingCardIndex + 1) : ''"
        confirmMethod="removeItem"
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
            ? 'Aktivitas ini memiliki foto lampiran yang juga akan terhapus. Lanjutkan menghapus?'
            : 'Apakah Anda yakin ingin menghapus data aktivitas ini?'"
        confirmMethod="removeAktivitasConfirmed"
    />

    {{-- Delete NC Confirmation Modal --}}
    <x-delete-modal
        :show="$showDeleteNcModal"
        wire:model="showDeleteNcModal"
        title="Hapus NC"
        message="Apakah Anda yakin ingin menghapus data NC ini?"
        confirmMethod="removeNcConfirmed"
    />

    {{-- Image Cropper Modal --}}
    <x-image-crop-modal
        :show="$showCropperModal"
        :imageUrl="$croppingImageUrl"
        :cropData="$cropData"
        :previewTarget="($croppingItemIndex !== null && $croppingRowKey) ? 'lampiran.'.$croppingItemIndex.'.'.$croppingRowKey.'.preview' : null"
    />

    {{-- Konfirmasi buang perubahan saat Batal --}}
    <x-confirm-modal />
</div>
