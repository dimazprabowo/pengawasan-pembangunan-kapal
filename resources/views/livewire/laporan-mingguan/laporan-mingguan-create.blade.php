<div x-data="{
        draftState: 'idle',
        draftSavedAt: '',
        draftKey: 'lm-draft:{{ auth()->id() }}:create:{{ $dataKapal->id }}',
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
    x-on:lm-clear-draft.window="localStorage.removeItem(draftKey)"
    @if($this->hasProcessingExternalFiles())
        wire:poll.4s="refreshExternalFileStatus"
    @endif
    x-init="
        (function () {
            let props = ['tanggal_laporan', 'periode_mulai', 'periode_selesai', 'ringkasan', 'minggu_ke', 'progressPerGroup', 'laporan_harian_ids', 'lampiran_ids'];
            let raw = localStorage.getItem(draftKey);
            if (raw && $wire.autoSave) {
                try {
                    let d = JSON.parse(raw);
                    props.forEach(function (p) { if (d[p] !== undefined) { $wire.set(p, d[p]); dirty = true; } });
                    if (d.laporanExternal) { $wire.set('laporanExternal', d.laporanExternal); dirty = true; }
                    if (d.periode_mulai && d.periode_selesai) {
                        let tries = 0;
                        let syncPicker = function () {
                            let el = document.getElementById('period-range-picker');
                            if (el && el._flatpickr) { el._flatpickr.setDate([d.periode_mulai, d.periode_selesai], false); }
                            else if (tries++ < 50) { setTimeout(syncPicker, 100); }
                        };
                        syncPicker();
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
                        payload.laporanExternal = ($wire.get('laporanExternal') || []).map(function (l) { let c = Object.assign({}, l); c.file = null; return c; });
                        localStorage.setItem(draftKey, JSON.stringify(payload));
                        draftSavedAt = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                        draftState = 'saved';
                    } catch (e) { draftState = 'idle'; }
                }, 1200);
            };
            props.forEach(function (p) { $wire.$watch(p, save); });
            $wire.$watch('laporanExternal', save);
            $wire.$watch('autoSave', function (v) { if (! v) { localStorage.removeItem(draftKey); draftState = 'idle'; } });
        })();
    ">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center gap-2">
            <a href="{{ route('laporan-mingguan.index', $dataKapal) }}" wire:navigate
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
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Tambah Laporan Mingguan</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 truncate">{{ $dataKapal->nama }}{{ $dataKapal->company ? ' · ' . $dataKapal->company->name : '' }} — Isi form di bawah untuk membuat laporan mingguan baru</p>
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
                    {{-- Tanggal Laporan --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Tanggal Laporan <span class="text-red-500">*</span>
                        </label>
                        <x-tanggal-picker wire:model="tanggal_laporan" id="tanggal_laporan"
                            placeholder="Pilih tanggal laporan"
                            display-format="j F Y" required />
                        @error('tanggal_laporan') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Minggu Ke- --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Minggu Ke- <span class="text-red-500">*</span>
                        </label>
                        <x-searchable-select
                            wire:model.live="minggu_ke"
                            :options="$mingguOptions"
                            placeholder="— Pilih minggu —"
                            searchPlaceholder="Cari minggu..."
                            :clearable="false"
                            :error="$errors->has('minggu_ke')"
                            class="text-sm"
                        />
                        @error('minggu_ke')
                            <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                        @enderror
                        @if(count($mingguOptions) === 0)
                            <span class="text-xs text-gray-400 dark:text-gray-500 mt-1 block">
                                @if($hasKontrak) Semua minggu sudah terlaporkan @else Belum ada minggu — kontrak belum diatur @endif
                            </span>
                        @endif
                    </div>

                    {{-- Periode (informasi) --}}
                    <div class="md:col-span-2">
                        <div wire:loading.class.remove="hidden" wire:target="minggu_ke" class="w-full px-3.5 py-2.5 bg-blue-50/60 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-800/40 rounded-lg hidden flex items-center gap-2.5 text-sm">
                            <svg class="animate-spin w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-gray-500 dark:text-gray-400">Memuat periode...</span>
                        </div>
                        <div wire:loading.remove wire:target="minggu_ke"
                             class="w-full px-3.5 py-2.5 bg-blue-50/60 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-800/40 rounded-lg flex flex-wrap items-center gap-x-2.5 gap-y-1">
                            <svg class="w-4 h-4 text-blue-500 dark:text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            @if($periode_mulai && $periode_selesai)
                                <span class="text-sm text-gray-600 dark:text-gray-300">Periode Mingguan:</span>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ \Illuminate\Support\Carbon::parse($periode_mulai)->translatedFormat('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($periode_selesai)->translatedFormat('d M Y') }}
                                </span>
                                <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                                    {{ \Illuminate\Support\Carbon::parse($periode_mulai)->diffInDays(\Illuminate\Support\Carbon::parse($periode_selesai)) + 1 }} hari
                                </span>
                                <span class="text-xs text-gray-400 dark:text-gray-500">(otomatis)</span>
                            @else
                                <span class="text-sm text-gray-500 dark:text-gray-400">Pilih minggu ke- untuk menampilkan periode</span>
                            @endif
                        </div>
                        @if(!$hasKontrak)
                            <input id="period-range-picker" type="text" placeholder="Pilih rentang tanggal periode"
                                class="w-full mt-2 px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white cursor-pointer {{ $errors->has('periode_mulai') || $errors->has('periode_selesai') ? 'border-red-500 dark:border-red-400' : 'border-gray-300 dark:border-gray-600' }}"
                                wire:ignore>
                        @endif
                        <input type="hidden" wire:model.live="periode_mulai" required>
                        <input type="hidden" wire:model.live="periode_selesai" required>
                        @error('periode_mulai') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        @error('periode_selesai') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        @error('laporan_harian_ids') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Ringkasan --}}
                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Ringkasan <span class="text-red-500">*</span>
                    </label>
                    <textarea wire:model="ringkasan" rows="3" required
                        placeholder="Mendeskripsikan Kegiatan Major pada Minggu Tersebut"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                    @error('ringkasan') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                {{-- Lampiran Harian — picker bertab per laporan harian --}}
                <div class="mt-6" x-data="{ lampiranTab: {{ collect($lampiranHarianList)->groupBy('laporan_harian_id')->keys()->first() ?? 'null' }} }">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Lampiran Harian Tersedia
                            <span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded-full @if(count($lampiran_ids) > 0) bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 @endif">
                                {{ count($lampiran_ids) }}/{{ count($lampiranHarianList) }} dipilih
                            </span>
                        </label>
                    </div>

                    @php
                        $lampiranGroups = collect($lampiranHarianList)->groupBy('laporan_harian_id');
                    @endphp

                    @if($lampiranGroups->isNotEmpty())
                        <div class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                            {{-- Tab bar per laporan harian --}}
                            <div class="flex items-center gap-1 px-3 pt-3 pb-0 overflow-x-auto overflow-y-hidden custom-scrollbar border-b border-gray-200 dark:border-gray-600">
                                @foreach($lampiranGroups as $harianId => $items)
                                    @php
                                        $selCount = collect($items)->whereIn('id', $lampiran_ids)->count();
                                        $tabLabel = $items->first()['laporan_tanggal'] ?? 'Laporan';
                                    @endphp
                                    <button type="button" wire:key="lmp-tab-{{ $harianId }}"
                                        x-on:click="lampiranTab = {{ $harianId }}"
                                        class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-t-lg border-b-2 transition-colors -mb-px"
                                        :class="lampiranTab === {{ $harianId }}
                                            ? 'border-blue-500 text-blue-600 dark:text-blue-400 bg-blue-50/50 dark:bg-blue-900/20'
                                            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50'">
                                        {{ $tabLabel }}
                                        <span class="px-1.5 py-0.5 text-[10px] font-semibold rounded-full {{ $selCount > 0 ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-600 dark:text-gray-400' }}">
                                            {{ $selCount }}/{{ count($items) }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>

                            {{-- Panel lampiran per tab --}}
                            <div class="p-3">
                                @foreach($lampiranGroups as $harianId => $items)
                                    <div wire:key="lmp-panel-{{ $harianId }}" x-show="lampiranTab === {{ $harianId }}" class="space-y-3">
                                        @foreach(collect($items)->groupBy('kategori') as $kat => $katItems)
                                            <div wire:key="lmp-kat-{{ $harianId }}-{{ md5($kat) }}">
                                                <div class="flex items-center gap-2 mb-1.5 px-0.5">
                                                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                                    </svg>
                                                    <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $kat }}</span>
                                                    <span class="text-[10px] text-gray-400 dark:text-gray-500">({{ count($katItems) }})</span>
                                                </div>
                                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2">
                                                    @foreach($katItems as $lampiran)
                                                        <div wire:key="lmp-item-{{ $lampiran['id'] }}"
                                                            wire:click="toggleLampiran({{ $lampiran['id'] }})"
                                                            class="group relative rounded-lg border-2 overflow-hidden cursor-pointer transition-all {{ $lampiran['is_selected'] ? 'border-emerald-500 ring-2 ring-emerald-500/30' : 'border-gray-200 dark:border-gray-600 hover:border-blue-400 dark:hover:border-blue-500' }}">
                                                            <div class="aspect-[4/3] bg-gray-100 dark:bg-gray-700">
                                                                @if($lampiran['thumb_url'])
                                                                    <img src="{{ $lampiran['thumb_url'] }}" alt="{{ $lampiran['file_name'] }}" loading="lazy" class="w-full h-full object-cover">
                                                                @else
                                                                    <div class="w-full h-full flex items-center justify-center">
                                                                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                                        </svg>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                            <div class="p-2 bg-white dark:bg-gray-800">
                                                                <p class="text-xs font-medium text-gray-900 dark:text-white line-clamp-2 leading-snug" title="{{ $lampiran['keterangan'] ?: $lampiran['file_name'] }}">{{ $lampiran['keterangan'] ?: $lampiran['file_name'] }}</p>
                                                                <p class="mt-0.5 text-[10px] text-gray-400 dark:text-gray-500 truncate">{{ $lampiran['file_name'] }} · {{ $lampiran['file_size_formatted'] }}</p>
                                                            </div>
                                                            @if($lampiran['is_selected'])
                                                                <div wire:loading.class="hidden" wire:target="toggleLampiran({{ $lampiran['id'] }})"
                                                                    class="absolute top-1.5 right-1.5 w-6 h-6 bg-emerald-500 rounded-full flex items-center justify-center shadow-lg">
                                                                    <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                                                    </svg>
                                                                </div>
                                                            @endif
                                                            <div wire:loading.class.remove="hidden" wire:target="toggleLampiran({{ $lampiran['id'] }})"
                                                                class="absolute top-1.5 right-1.5 w-6 h-6 bg-white/90 dark:bg-gray-800/90 rounded-full hidden flex items-center justify-center shadow-lg">
                                                                <svg class="animate-spin w-3.5 h-3.5 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                </svg>
                                                            </div>
                                                            @if($lampiran['is_image'])
                                                                <button type="button" x-on:click.stop
                                                                    wire:click="previewLampiranHarian({{ $lampiran['id'] }})"
                                                                    class="absolute top-1.5 left-1.5 w-6 h-6 rounded-full bg-black/50 text-white flex items-center justify-center opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity" title="Preview">
                                                                    <svg wire:loading.class="hidden" wire:target="previewLampiranHarian({{ $lampiran['id'] }})" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                                    </svg>
                                                                    <svg wire:loading wire:target="previewLampiranHarian({{ $lampiran['id'] }})" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                    </svg>
                                                                </button>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg p-4 text-sm text-gray-500 dark:text-gray-400 text-center">
                            @if($periode_mulai && $periode_selesai)
                                Tidak ada lampiran pada laporan harian di periode ini.
                            @else
                                Pilih minggu ke- untuk melihat lampiran laporan harian.
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Laporan External Section --}}
                <x-laporan-mingguan.external-reports-section
                    :laporanExternal="$laporanExternal"
                    :deletingExternalId="$deletingExternalId"
                />
            </div>
        </div>

        {{-- Kurva S Progress --}}
        @if($data_kapal_id)
        <div class="mt-6">
            <x-laporan-mingguan.kurva-s-form
                :mingguOptions="$mingguOptions"
                :hasKurvaS="$hasKurvaS"
                :workGroupsForInput="$workGroupsForInput"
                :minggu_ke="$minggu_ke"
                :progressPerGroup="$progressPerGroup"
                :progressHistory="$progressHistory"
            />
        </div>

        {{-- Riwayat Progress per Work Group --}}
        @if($hasKurvaS && count($workGroupsForInput) > 0)
        <x-laporan-mingguan.riwayat-progress-card
            :workGroups="$workGroupsForInput"
            :progressHistory="$progressHistory"
            :fullProgressHistory="$this->fullProgressHistory"
            class="my-6"
        />
        @endif

        {{-- Kurva S Chart --}}
        <x-kurva-s-chart-card
            :chartData="$kurvaSChartData"
            :dataKapalNama="$dataKapalNama ?? null"
            :mingguKe="$minggu_ke"
            :showStats="true"
            :totalRencana="$totalRencana"
            :totalAktual="$totalAktual"
            height="280px"
        />
        @endif

        {{-- Footer Actions --}}
        <div class="mt-6 flex flex-col sm:flex-row items-center justify-end gap-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 px-5 py-4">
            <x-cancel-button x-on:click="requestCancel()" target="cancel"
                wire:loading.attr="disabled" wire:target="cancel" class="w-full sm:w-auto" />
            <x-loading-button type="submit" target="save" variant="primary" size="lg"
                loadingText="Menyimpan..." class="w-full sm:w-auto">
                Simpan
            </x-loading-button>
        </div>
    </form>

    {{-- Konfirmasi buang perubahan saat Batal --}}
    <x-confirm-modal />

    {{-- Lampiran Harian Modal --}}

    {{-- Lampiran Preview Modal --}}
    <x-lampiran-preview-modal
        :show="$showPreviewModal"
        :lampiran="$this->previewLampiran"
        :laporan="null"
        :imageUrl="$this->previewLampiranImageUrl"
    />

    {{-- Delete External Report Confirmation Modal --}}
    <x-delete-modal
        :show="$showDeleteExternalModal"
        wire:model="showDeleteExternalModal"
        title="Hapus Laporan External"
        message="Apakah Anda yakin ingin menghapus laporan external ini?"
        confirmMethod="deleteExternalReport"
    />

    {{-- Konfirmasi Pemindahan Hari dari Mingguan Lain --}}
    <x-laporan-mingguan.harian-reassign-modal
        :show="$showReassignModal"
        wire:model="showReassignModal"
        :groups="$harianConflicts"
        confirmMethod="saveWithReassign"
    />

@if(!$hasKontrak)
<script>
let pickerInstance = null;

window.initFlatpickr = function() {
    const element = document.getElementById('period-range-picker');
    if (!element || !window.flatpickr) {
        setTimeout(window.initFlatpickr, 100);
        return;
    }
    
    // Destroy existing picker if any
    if (pickerInstance) {
        pickerInstance.destroy();
        pickerInstance = null;
    }
    
    pickerInstance = flatpickr(element, {
        mode: 'range',
        dateFormat: 'Y-m-d',
        minDate: null,
        maxDate: 'today',
        locale: { firstDayOfWeek: 1 },
        onChange: function(selectedDates, dateStr, instance) {
            if (selectedDates.length === 2) {
                @this.set('periode_mulai', instance.formatDate(selectedDates[0], 'Y-m-d'));
                @this.set('periode_selesai', instance.formatDate(selectedDates[1], 'Y-m-d'));
            } else if (selectedDates.length === 0) {
                @this.set('periode_mulai', '');
                @this.set('periode_selesai', '');
            }
        }
    });
    
    @if($periode_mulai && $periode_selesai)
    if (pickerInstance) {
        pickerInstance.setDate(['{{ $periode_mulai }}', '{{ $periode_selesai }}']);
    }
    @endif
};

// Support both DOMContentLoaded and Livewire navigation
document.addEventListener('DOMContentLoaded', window.initFlatpickr);
document.addEventListener('livewire:navigated', window.initFlatpickr);
</script>
@endif
</div>
