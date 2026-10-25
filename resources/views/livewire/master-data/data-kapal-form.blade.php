<div>
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
        </div>
    </div>

    <form wire:submit="save">
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
                </div>
            </div>

            {{-- Actions --}}
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700 rounded-b-lg flex flex-col sm:flex-row-reverse gap-2">
                <x-loading-button type="submit" target="save" variant="primary" size="lg"
                    loadingText="Menyimpan..." class="w-full sm:w-auto">
                    {{ $editMode ? 'Update' : 'Simpan' }}
                </x-loading-button>
                <x-cancel-button wire:click="cancel" target="cancel" class="w-full sm:w-auto" />
            </div>
        </div>
    </form>
</div>
