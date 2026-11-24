<div x-data="{
        draftState: 'idle',
        draftSavedAt: '',
        draftKey: 'lb-draft:{{ auth()->id() }}:create:{{ $dataKapal->id }}',
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
    x-on:lb-clear-draft.window="localStorage.removeItem(draftKey)"
    x-init="
        (function () {
            let props = ['tanggal_laporan', 'bulan_ke', 'periode_mulai', 'periode_selesai', 'ringkasan'];
            let raw = localStorage.getItem(draftKey);
            if (raw && $wire.autoSave) {
                try {
                    let d = JSON.parse(raw);
                    props.forEach(function (p) { if (d[p] !== undefined) { $wire.set(p, d[p]); dirty = true; } });
                    if (d.laporanExternal) { $wire.set('laporanExternal', d.laporanExternal); dirty = true; }
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
            <a href="{{ route('laporan-bulanan.index', $dataKapal) }}" wire:navigate
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
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Tambah Laporan Bulanan</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 truncate">{{ $dataKapal->nama }}{{ $dataKapal->owner ? ' · ' . $dataKapal->owner->name : '' }} — Isi form di bawah untuk membuat laporan bulanan baru</p>
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

    @if(!$hasPelaksanaan)
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4 mb-6">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div>
                    <p class="text-sm font-medium text-amber-800 dark:text-amber-300">Kontrak belum diatur</p>
                    <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">Laporan bulanan membutuhkan tanggal mulai &amp; waktu pelaksanaan untuk menentukan periode bulan. Atur pelaksanaan di menu Master Data → Data Proyek terlebih dahulu.</p>
                </div>
            </div>
        </div>
    @endif

    <form wire:submit="save" x-on:submit="localStorage.removeItem(draftKey)">
        {{-- Form Card --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Tanggal Laporan --}}
                    <div>
                        <x-input-label for="tanggal_laporan" value="Tanggal Laporan" :required="true" />
                        <x-tanggal-picker wire:model="tanggal_laporan" id="tanggal_laporan"
                            placeholder="Pilih tanggal laporan"
                            display-format="j F Y" required
                            class="{{ $errors->has('tanggal_laporan') ? 'border-red-500 dark:border-red-400' : '' }}" />
                        @error('tanggal_laporan') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Bulan ke- --}}
                    <div>
                        <x-input-label for="bulan_ke" value="Bulan Ke-" :required="true" />
                        <x-searchable-select
                            wire:model.live="bulan_ke"
                            :options="$bulanOptions"
                            placeholder="{{ $hasPelaksanaan ? '— Pilih bulan —' : 'Kontrak belum diatur' }}"
                            searchPlaceholder="Cari bulan..."
                            :clearable="false"
                            :error="$errors->has('bulan_ke')"
                            class="text-sm"
                        />
                        @error('bulan_ke')
                            <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                        @enderror
                        @if(count($bulanOptions) === 0)
                            <span class="text-xs text-gray-400 dark:text-gray-500 mt-1 block">
                                @if($hasPelaksanaan) Semua bulan sudah terlaporkan @else Belum ada bulan — pelaksanaan belum diatur @endif
                            </span>
                        @endif
                    </div>

                    {{-- Periode (informasi — otomatis dari kontrak) --}}
                    <div class="md:col-span-2">
                        <div wire:loading.class.remove="hidden" wire:target="bulan_ke" class="w-full px-3.5 py-2.5 bg-blue-50/60 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-800/40 rounded-lg hidden flex items-center gap-2.5 text-sm">
                            <svg class="animate-spin w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-gray-500 dark:text-gray-400">Memuat periode...</span>
                        </div>
                        <div wire:loading.remove wire:target="bulan_ke"
                             class="w-full px-3.5 py-2.5 bg-blue-50/60 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-800/40 rounded-lg flex flex-wrap items-center gap-x-2.5 gap-y-1">
                            <svg class="w-4 h-4 text-blue-500 dark:text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            @if($periode_mulai && $periode_selesai)
                                <span class="text-sm text-gray-600 dark:text-gray-300">Periode Bulanan:</span>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ \Illuminate\Support\Carbon::parse($periode_mulai)->translatedFormat('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($periode_selesai)->translatedFormat('d M Y') }}
                                </span>
                                <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                                    {{ \Illuminate\Support\Carbon::parse($periode_mulai)->diffInDays(\Illuminate\Support\Carbon::parse($periode_selesai)) + 1 }} hari
                                </span>
                                <span class="text-xs text-gray-400 dark:text-gray-500">(otomatis)</span>
                            @else
                                <span class="text-sm text-gray-500 dark:text-gray-400">Pilih bulan ke- untuk menampilkan periode</span>
                            @endif
                        </div>
                        <input type="hidden" wire:model.live="periode_mulai" required>
                        <input type="hidden" wire:model.live="periode_selesai" required>
                        @error('periode_mulai') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        @error('periode_selesai') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Executive Summary --}}
                <div class="mt-6">
                    <x-input-label for="ringkasan" value="Executive Summary" :required="true" />
                    <textarea wire:model="ringkasan" id="ringkasan" rows="4" required
                        placeholder="Masukkan executive summary (capaian, kendala, rencana bulan berikutnya)"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                    @error('ringkasan') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                @include('livewire.laporan-bulanan.partials.repeaters')

                {{-- Laporan External Section --}}
                <x-laporan-mingguan.external-reports-section
                    :laporanExternal="$laporanExternal"
                    :deletingExternalId="$deletingExternalId"
                />
            </div>
        </div>

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

    {{-- Delete External Report Confirmation Modal --}}
    <x-delete-modal
        :show="$showDeleteExternalModal"
        wire:model="showDeleteExternalModal"
        title="Hapus Laporan External"
        message="Apakah Anda yakin ingin menghapus laporan external ini?"
        confirmMethod="deleteExternalReport"
    />

    <x-confirm-modal />
</div>
