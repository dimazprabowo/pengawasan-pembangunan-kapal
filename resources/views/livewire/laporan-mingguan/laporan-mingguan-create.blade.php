<div x-data="{
        draftState: 'idle',
        draftSavedAt: '',
        draftKey: 'lm-draft:{{ auth()->id() }}:create:{{ $jenisKapal->id }}',
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
            <a href="{{ route('laporan-mingguan.index', $jenisKapal) }}" wire:navigate
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
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 truncate">{{ $jenisKapal->nama }}{{ $jenisKapal->company ? ' · ' . $jenisKapal->company->name : '' }} — Isi form di bawah untuk membuat laporan mingguan baru</p>
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
                        <input wire:model="tanggal_laporan" type="date" required
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        @error('tanggal_laporan') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Periode --}}
                    <div class="md:col-span-2" x-data="{ hasError: @this.$errors.has('periode_mulai') || @this.$errors.has('periode_selesai') }"
                         x-init="$watch('$wire.errors', () => hasError = @this.$errors.has('periode_mulai') || @this.$errors.has('periode_selesai'))">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Periode Mingguan <span class="text-red-500">*</span>
                        </label>
                        @if(!$jenis_kapal_id)
                            <div class="w-full h-[42px] px-3 py-2 bg-gray-100 dark:bg-gray-600/50 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-500 dark:text-gray-400 text-sm flex items-center cursor-not-allowed">
                                Pilih jenis kapal terlebih dahulu
                            </div>
                        @else
                            <input id="period-range-picker" type="text" placeholder="Pilih rentang tanggal periode"
                                :class="hasError ? 'border-red-500 dark:border-red-400' : 'border-gray-300 dark:border-gray-600'"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white cursor-pointer"
                                wire:ignore>
                        @endif
                        <input type="hidden" wire:model.live="periode_mulai" required>
                        <input type="hidden" wire:model.live="periode_selesai" required>
                        @error('periode_mulai') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        @error('periode_selesai') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Ringkasan --}}
                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Ringkasan
                    </label>
                    <textarea wire:model="ringkasan" rows="3"
                        placeholder="Masukkan ringkasan laporan mingguan"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                    @error('ringkasan') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                {{-- Laporan Harian Selection --}}
                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Laporan Harian dalam Periode <span class="text-red-500">*</span>
                        <span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded-full @if(count($laporan_harian_ids) > 0) bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 @endif">
                            {{ count($laporan_harian_ids) }}/{{ count($availableLaporanHarian) }} dipilih
                        </span>
                    </label>

                    <div wire:loading wire:target="periode_mulai,periode_selesai" class="w-full bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                        <div class="flex flex-col items-center justify-center gap-2">
                            <svg class="animate-spin w-6 h-6 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Memuat laporan harian...</span>
                        </div>
                    </div>

                    <div wire:loading.remove wire:target="periode_mulai,periode_selesai">
                        @if(count($availableLaporanHarian) > 0)
                            <div class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                                <div class="space-y-2 max-h-60 overflow-y-auto">
                                    @foreach($availableLaporanHarian as $laporan)
                                        <label wire:key="harian-option-{{ $laporan['id'] }}" class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors">
                                            <input type="checkbox"
                                                value="{{ $laporan['id'] }}"
                                                wire:model.live="laporan_harian_ids"
                                                class="w-4 h-4 text-blue-600 rounded border-gray-300 dark:border-gray-600 focus:ring-blue-500 dark:bg-gray-700">
                                            <div class="flex-1">
                                                <div class="font-medium text-gray-900 dark:text-white text-sm">{{ $laporan['judul'] }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $laporan['tanggal'] }}</div>
                                            </div>
                                            @if(!empty($laporan['claimed_label']))
                                                <span class="flex-shrink-0 px-2 py-0.5 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300"
                                                    title="Hari ini sudah tercatat di {{ $laporan['claimed_label'] }} — akan dipindahkan saat disimpan">
                                                    {{ $laporan['claimed_label'] }}
                                                </span>
                                            @endif
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg p-4 text-sm text-gray-500 dark:text-gray-400 text-center">
                                @if($periode_mulai && $periode_selesai)
                                    Tidak ada laporan harian dalam periode yang dipilih.
                                @else
                                    Silakan pilih periode mingguan untuk melihat laporan harian yang tersedia.
                                @endif
                            </div>
                            @error('laporan_harian_ids') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        @endif
                    </div>
                </div>

                {{-- Lampiran Harian Section --}}
                <div class="mt-6">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Lampiran Harian Terpilih
                            <span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded-full @if(count($lampiran_ids) > 0) bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 @endif">
                                {{ count($lampiran_ids) }}/{{ count($lampiranHarianList) }} dipilih
                            </span>
                        </label>
                        <button type="button" wire:click="openLampiranModal"
                            wire:loading.attr="disabled"
                            wire:target="openLampiranModal"
                            @if(count($laporan_harian_ids) === 0) disabled @endif
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg wire:loading.class="hidden" wire:target="openLampiranModal" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg wire:loading wire:target="openLampiranModal" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span wire:loading.remove wire:target="openLampiranModal">Pilih Lampiran</span>
                            <span wire:loading wire:target="openLampiranModal">Memuat...</span>
                        </button>
                    </div>

                    @if(count($lampiran_ids) > 0)
                        <div class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                            <div class="space-y-2">
                                @php
                                    $selectedLampiran = collect($lampiranHarianList)->whereIn('id', $lampiran_ids)->values();
                                @endphp
                                @foreach($selectedLampiran as $lampiran)
                                    <div class="flex items-center gap-3 p-2 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                        <div class="flex items-center justify-center w-5 h-5 bg-green-500 rounded-full flex-shrink-0">
                                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $lampiran['file_name'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $lampiran['file_size_formatted'] }} • {{ $lampiran['laporan_tanggal'] }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg p-4 text-sm text-gray-500 dark:text-gray-400 text-center">
                            @if(count($laporan_harian_ids) > 0)
                                Klik tombol "Pilih Lampiran" untuk memilih lampiran dari laporan harian yang dipilih.
                            @else
                                Silakan pilih laporan harian terlebih dahulu untuk melihat lampiran.
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
        @if($jenis_kapal_id)
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
            :jenisKapalNama="$jenisKapalNama ?? null"
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
    <x-laporan-mingguan.lampiran-harian-modal
        :show="$showLampiranModal"
        :lampiranList="$lampiranHarianList"
        :loading="$loadingLampiran"
    />

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
</div>
