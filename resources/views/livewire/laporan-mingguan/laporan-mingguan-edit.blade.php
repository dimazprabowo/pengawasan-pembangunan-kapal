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

    <form wire:submit="save">
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
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Jenis Kapal --}}
                    <div class="md:col-span-2">
                        <x-laporan.jenis-kapal-selector
                            wireModel="jenis_kapal_id"
                            :jenisKapalList="$jenisKapalList"
                            variant="form"
                            placeholder="Pilih jenis kapal"
                            :error="$errors->has('jenis_kapal_id') ? $errors->first('jenis_kapal_id') : null"
                            :selectedValue="$jenis_kapal_id"
                        />
                    </div>

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
                            placeholder="Masukkan judul laporan mingguan"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        @error('judul') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer Actions --}}
        <div class="flex flex-col sm:flex-row items-center justify-end gap-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 px-5 py-4">
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
</div>
