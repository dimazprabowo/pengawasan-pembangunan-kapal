<div>
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center gap-2">
            <a href="{{ route('laporan-mingguan.index') }}" wire:navigate
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
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Laporan Mingguan</h2>
        </div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Perbarui data laporan di bawah ini</p>
    </div>

    <form wire:submit="save" x-data="{ watchJenisKapal: false }" x-init="$watch('$wire.jenis_kapal_id', (val) => { if(val) setTimeout(() => window.initFlatpickrEdit && window.initFlatpickrEdit(), 150) })">
        {{-- Jenis Kapal Selection --}}
        <div class="mb-6">
            <x-laporan.jenis-kapal-selector
                wireModel="jenis_kapal_id"
                :jenisKapalList="$jenisKapalList"
                variant="form"
                placeholder="Pilih jenis kapal"
                :error="$errors->has('jenis_kapal_id') ? $errors->first('jenis_kapal_id') : null"
                :selectedValue="$jenis_kapal_id"
            />
        </div>

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

                    {{-- Judul --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Judul <span class="text-red-500">*</span>
                        </label>
                        <input wire:model="judul" type="text" required
                            placeholder="Masukkan judul laporan"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        @error('judul') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Periode --}}
                    <div class="md:col-span-2" x-data="{ hasError: $errors.has('periode_mulai') || $errors.has('periode_selesai') }"
                         x-init="$watch('$errors', () => hasError = $errors.has('periode_mulai') || $errors.has('periode_selesai'))">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Periode Mingguan <span class="text-red-500">*</span>
                        </label>
                        @if(!$jenis_kapal_id)
                            <div class="w-full h-[42px] px-3 py-2 bg-gray-100 dark:bg-gray-600/50 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-500 dark:text-gray-400 text-sm flex items-center cursor-not-allowed">
                                Pilih jenis kapal terlebih dahulu
                            </div>
                        @else
                            <input id="period-range-picker-edit" type="text" placeholder="Pilih rentang tanggal periode"
                                :class="hasError ? 'border-red-500 dark:border-red-400' : 'border-gray-300 dark:border-gray-600'"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white cursor-pointer">
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

                {{-- Laporan Harian Summary --}}
                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Laporan Harian dalam Periode <span class="text-red-500">*</span>
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
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                                <div class="flex items-center gap-2 mb-3">
                                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                        {{ count($availableLaporanHarian) }} laporan harian otomatis dipilih
                                    </span>
                                </div>
                                <div class="space-y-2">
                                    @foreach($availableLaporanHarian as $laporan)
                                        <div class="flex items-center gap-3 text-sm">
                                            <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                                            <div class="flex-1">
                                                <div class="font-medium text-gray-900 dark:text-white">{{ $laporan['judul'] }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $laporan['tanggal'] }}</div>
                                            </div>
                                        </div>
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
                            @if(!$periode_mulai || !$periode_selesai || count($availableLaporanHarian) === 0) disabled @endif
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
                            @if($periode_mulai && $periode_selesai)
                                Klik tombol "Pilih Lampiran" untuk memilih lampiran dari laporan harian dalam periode ini.
                            @else
                                Silakan pilih periode mingguan terlebih dahulu untuk melihat lampiran.
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Footer Actions --}}
        <div class="mt-6 flex flex-col sm:flex-row items-center justify-end gap-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 px-5 py-4">
            <a href="{{ route('laporan-mingguan.index') }}" wire:navigate
                x-data="{ loading: false }" x-on:click="loading = true"
                x-bind:class="loading ? 'opacity-75 pointer-events-none' : ''"
                class="inline-flex items-center justify-center gap-1.5 font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 focus:ring-blue-500 px-4 py-2 text-sm w-full sm:w-auto">
                <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-show="!loading">Batal</span>
                <span x-show="loading" x-cloak>Memuat...</span>
            </a>
            <x-loading-button type="submit" target="save" variant="primary" size="lg"
                loadingText="Menyimpan..." class="w-full sm:w-auto">
                Update Laporan
            </x-loading-button>
        </div>
    </form>

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

<script>
let pickerInstanceEdit = null;

window.initFlatpickrEdit = function() {
    const element = document.getElementById('period-range-picker-edit');
    if (!element || !window.flatpickr) {
        setTimeout(window.initFlatpickrEdit, 100);
        return;
    }
    
    // Destroy existing picker if any
    if (pickerInstanceEdit) {
        pickerInstanceEdit.destroy();
    }
    
    pickerInstanceEdit = flatpickr(element, {
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
    if (pickerInstanceEdit) {
        pickerInstanceEdit.setDate(['{{ $periode_mulai }}', '{{ $periode_selesai }}']);
    }
    @endif
};

// Support both DOMContentLoaded and Livewire navigation
document.addEventListener('DOMContentLoaded', window.initFlatpickrEdit);
document.addEventListener('livewire:navigated', window.initFlatpickrEdit);
</script>
</div>
