<div>
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
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 truncate">{{ $dataKapal->nama }}{{ $dataKapal->company ? ' · ' . $dataKapal->company->name : '' }} — Isi form di bawah untuk membuat laporan bulanan baru</p>
            </div>
        </div>
    </div>

    @if(!$hasKontrak)
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4 mb-6">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div>
                    <p class="text-sm font-medium text-amber-800 dark:text-amber-300">Kontrak belum diatur</p>
                    <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">Laporan bulanan membutuhkan tanggal mulai &amp; durasi kontrak untuk menentukan periode bulan. Atur kontrak di menu Master Data → Data Kapal terlebih dahulu.</p>
                </div>
            </div>
        </div>
    @endif

    <form wire:submit="save">
        {{-- Form Card --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Bulan ke- --}}
                    <div>
                        <x-input-label for="bulan_ke" value="Bulan Ke-" :required="true" />
                        <x-searchable-select
                            wire:model.live="bulan_ke"
                            :options="$bulanOptions"
                            placeholder="{{ $hasKontrak ? '— Pilih bulan —' : 'Kontrak belum diatur' }}"
                            searchPlaceholder="Cari bulan..."
                            :clearable="false"
                            :error="$errors->has('bulan_ke')"
                            class="text-sm"
                        />
                        @error('bulan_ke')
                            <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- Tanggal Laporan --}}
                    <div>
                        <x-input-label for="tanggal_laporan" value="Tanggal Laporan" :required="true" />
                        <x-text-input wire:model="tanggal_laporan" id="tanggal_laporan" type="date" required
                            class="w-full {{ $errors->has('tanggal_laporan') ? 'border-red-500 dark:border-red-400' : '' }}" />
                        @error('tanggal_laporan') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Periode (terkunci — otomatis dari kontrak) --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Periode Bulanan <span class="text-red-500">*</span>
                        </label>
                        <div class="w-full min-h-[42px] px-3 py-2 bg-gray-50 dark:bg-gray-700/50 border rounded-lg text-sm flex items-center gap-2 cursor-not-allowed {{ $errors->has('periode_mulai') || $errors->has('periode_selesai') ? 'border-red-500 dark:border-red-400' : 'border-gray-300 dark:border-gray-600' }}">
                            @if($periode_mulai && $periode_selesai)
                                <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    {{ \Illuminate\Support\Carbon::parse($periode_mulai)->translatedFormat('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($periode_selesai)->translatedFormat('d M Y') }}
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">(otomatis dari kontrak)</span>
                            @else
                                <span class="text-gray-500 dark:text-gray-400">Pilih bulan ke- terlebih dahulu — periode terisi otomatis dari kontrak</span>
                            @endif
                        </div>
                        <input type="hidden" wire:model.live="periode_mulai">
                        <input type="hidden" wire:model.live="periode_selesai">
                        @error('periode_mulai') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        @error('periode_selesai') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Ringkasan --}}
                <div class="mt-6">
                    <x-input-label for="ringkasan" value="Ringkasan" />
                    <textarea wire:model="ringkasan" id="ringkasan" rows="4"
                        placeholder="Masukkan ringkasan laporan bulanan (capaian, kendala, rencana bulan berikutnya)"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                    @error('ringkasan') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                {{-- Laporan External Section --}}
                <x-laporan-mingguan.external-reports-section
                    :laporanExternal="$laporanExternal"
                    :deletingExternalId="$deletingExternalId"
                />
            </div>
        </div>

        {{-- Footer Actions --}}
        <div class="mt-6 flex flex-col sm:flex-row items-center justify-end gap-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 px-5 py-4">
            <x-cancel-button wire:click="cancel" target="cancel"
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
</div>
