<div>
    @php
        $tabs = [
            'drawing' => 'Drawing Register',
            'vendor' => 'Vendor',
            'material' => 'Material',
            'pengujian' => 'Pengujian',
            'rapat' => 'Rapat',
        ];
        $entityLabel = $tabs[$tab] ?? 'Data';
    @endphp

    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center gap-2">
            <a href="{{ route('master-data.data-kapal') }}" wire:navigate
                class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div class="min-w-0">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Data Proyek</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 truncate">
                    {{ $dataKapal->nama }}{{ $dataKapal->company ? ' · '.$dataKapal->company->name : '' }} — register drawing, vendor, material, pengujian &amp; rapat untuk laporan bulanan
                </p>
            </div>
        </div>
    </div>

    {{-- Tab Navigation --}}
    <div class="mb-4 flex flex-wrap gap-1 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-1">
        @foreach($tabs as $key => $label)
            <button type="button" wire:click="setTab('{{ $key }}')"
                class="flex-1 min-w-[120px] px-4 py-2 text-sm font-medium rounded-md transition-colors
                    {{ $tab === $key
                        ? 'bg-blue-600 text-white shadow-sm'
                        : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Toolbar --}}
    <div class="mb-4 flex flex-col lg:flex-row lg:items-center gap-3">
        <div class="flex-1">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari {{ strtolower($entityLabel) }}..."
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white text-sm">
        </div>

        <x-filter-popover :filters="$activeFilters" :per-page="true">
            @if($tab === 'drawing')
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Status Produksi</label>
                    <x-searchable-select wire:model.live="statusProduksiFilter"
                        :options="collect($drawingStatuses)->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->toArray()"
                        placeholder="Semua Status" searchPlaceholder="Cari status..." />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Status Review MNO</label>
                    <x-searchable-select wire:model.live="statusReviewMnoFilter"
                        :options="collect($drawingStatuses)->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->toArray()"
                        placeholder="Semua Status" searchPlaceholder="Cari status..." />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Status Approval Klas</label>
                    <x-searchable-select wire:model.live="statusKlasFilter"
                        :options="collect($drawingStatuses)->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->toArray()"
                        placeholder="Semua Status" searchPlaceholder="Cari status..." />
                </div>
            @elseif($tab === 'material')
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Kategori</label>
                    <x-searchable-select wire:model.live="materialKategoriFilter"
                        :options="collect($materialKategoris)->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->toArray()"
                        placeholder="Semua Kategori" searchPlaceholder="Cari kategori..." />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Status</label>
                    <x-searchable-select wire:model.live="materialStatusFilter"
                        :options="collect($materialStatuses)->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->toArray()"
                        placeholder="Semua Status" searchPlaceholder="Cari status..." />
                </div>
            @elseif($tab === 'pengujian')
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Status</label>
                    <x-searchable-select wire:model.live="pengujianStatusFilter"
                        :options="collect($pengujianStatuses)->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->toArray()"
                        placeholder="Semua Status" searchPlaceholder="Cari status..." />
                </div>
            @elseif($tab === 'rapat')
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Jenis Rapat</label>
                    <x-searchable-select wire:model.live="rapatJenisFilter"
                        :options="collect($rapatJenises)->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->toArray()"
                        placeholder="Semua Jenis" searchPlaceholder="Cari jenis..." />
                </div>
            @endif
        </x-filter-popover>

        @can('proyek_data_create')
            <x-loading-button wire:click="startCreate" target="startCreate" variant="primary" size="md" loadingText="Memuat..." class="flex-1 lg:flex-none">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Tambah {{ $entityLabel }}
            </x-loading-button>
        @endcan
    </div>

    {{-- Inline Form Card --}}
    @if($showForm)
        <div wire:key="form-{{ $tab }}" class="mb-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $formEditMode ? 'Edit' : 'Tambah' }} {{ $entityLabel }}
                </h3>
                <button type="button" wire:click="cancelForm" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" title="Tutup form">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form wire:submit="save" class="p-5">
                @if($tab === 'drawing')
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="f_nomor" value="Nomor Drawing" :required="true" />
                            <x-text-input wire:model="form.nomor" id="f_nomor" placeholder="Mis. DWG-001" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('form.nomor')" class="mt-1" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="f_judul" value="Judul Drawing" :required="true" />
                            <x-text-input wire:model="form.judul" id="f_judul" placeholder="Mis. General Arrangement Plan" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('form.judul')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="f_stprod" value="Status Produksi (Galangan)" :required="true" />
                            <x-searchable-select wire:model="form.status_produksi" id="f_stprod"
                                :options="collect($drawingStatuses)->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->toArray()"
                                placeholder="Pilih status" :clearable="false" />
                            <x-input-error :messages="$errors->get('form.status_produksi')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="f_stmno" value="Status Review MNO" :required="true" />
                            <x-searchable-select wire:model="form.status_review_mno" id="f_stmno"
                                :options="collect($drawingStatuses)->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->toArray()"
                                placeholder="Pilih status" :clearable="false" />
                            <x-input-error :messages="$errors->get('form.status_review_mno')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="f_stklas" value="Status Approval Klas" :required="true" />
                            <x-searchable-select wire:model="form.status_approval_klas" id="f_stklas"
                                :options="collect($drawingStatuses)->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->toArray()"
                                placeholder="Pilih status" :clearable="false" />
                            <x-input-error :messages="$errors->get('form.status_approval_klas')" class="mt-1" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Comment MNO</p>
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <x-input-label for="f_mno_o" value="Open" />
                                    <x-text-input type="number" min="0" wire:model="form.mno_comment_open" id="f_mno_o" placeholder="0" class="mt-1 block w-full" />
                                </div>
                                <div>
                                    <x-input-label for="f_mno_c" value="Closed" />
                                    <x-text-input type="number" min="0" wire:model="form.mno_comment_closed" id="f_mno_c" placeholder="0" class="mt-1 block w-full" />
                                </div>
                                <div>
                                    <x-input-label for="f_mno_i" value="Informasi" />
                                    <x-text-input type="number" min="0" wire:model="form.mno_comment_informasi" id="f_mno_i" placeholder="0" class="mt-1 block w-full" />
                                </div>
                            </div>
                        </div>
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Comment Klas</p>
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <x-input-label for="f_klas_o" value="Open" />
                                    <x-text-input type="number" min="0" wire:model="form.klas_comment_open" id="f_klas_o" placeholder="0" class="mt-1 block w-full" />
                                </div>
                                <div>
                                    <x-input-label for="f_klas_c" value="Closed" />
                                    <x-text-input type="number" min="0" wire:model="form.klas_comment_closed" id="f_klas_c" placeholder="0" class="mt-1 block w-full" />
                                </div>
                                <div>
                                    <x-input-label for="f_klas_i" value="Informasi" />
                                    <x-text-input type="number" min="0" wire:model="form.klas_comment_informasi" id="f_klas_i" placeholder="0" class="mt-1 block w-full" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-input-label for="f_dket" value="Keterangan" />
                        <x-text-input wire:model="form.keterangan" id="f_dket" placeholder="Keterangan tambahan (opsional)" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('form.keterangan')" class="mt-1" />
                    </div>
                @elseif($tab === 'vendor')
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="f_vnama" value="Nama Vendor / Sub-Contractor" :required="true" />
                            <x-text-input wire:model="form.nama" id="f_vnama" placeholder="Mis. PT Maju Bersama" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('form.nama')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="f_vlingkup" value="Lingkup Pekerjaan" />
                            <x-text-input wire:model="form.lingkup_pekerjaan" id="f_vlingkup" placeholder="Mis. Piping System" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('form.lingkup_pekerjaan')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="f_vkontak" value="Kontak" />
                            <x-text-input wire:model="form.kontak" id="f_vkontak" placeholder="Mis. 0812xxxx / email" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('form.kontak')" class="mt-1" />
                        </div>
                        <div class="md:col-span-3">
                            <x-input-label for="f_vket" value="Keterangan" />
                            <x-text-input wire:model="form.keterangan" id="f_vket" placeholder="Keterangan tambahan (opsional)" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('form.keterangan')" class="mt-1" />
                        </div>
                    </div>
                @elseif($tab === 'material')
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="f_mkat" value="Kategori" :required="true" />
                            <x-searchable-select wire:model="form.kategori" id="f_mkat"
                                :options="collect($materialKategoris)->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->toArray()"
                                placeholder="Pilih kategori" :clearable="false" />
                            <x-input-error :messages="$errors->get('form.kategori')" class="mt-1" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="f_mitem" value="Nama Item" :required="true" />
                            <x-text-input wire:model="form.nama_item" id="f_mitem" placeholder="Mis. Steel Plate 12mm AH36" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('form.nama_item')" class="mt-1" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="f_mspek" value="Spesifikasi" />
                            <x-text-input wire:model="form.spesifikasi" id="f_mspek" placeholder="Mis. Grade AH36, 12 x 1500 x 6000 mm" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('form.spesifikasi')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="f_mjml" value="Jumlah" />
                            <x-text-input type="number" step="0.01" min="0" wire:model="form.jumlah" id="f_mjml" placeholder="0" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('form.jumlah')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="f_msat" value="Satuan" />
                            <x-text-input wire:model="form.satuan" id="f_msat" placeholder="Mis. Ton, Pcs, Set" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('form.satuan')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="f_mstatus" value="Status" :required="true" />
                            <x-searchable-select wire:model="form.status" id="f_mstatus"
                                :options="collect($materialStatuses)->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->toArray()"
                                placeholder="Pilih status" :clearable="false" />
                            <x-input-error :messages="$errors->get('form.status')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="f_mtgl" value="Tanggal (Estimasi/Aktual)" />
                            <x-tanggal-picker wire:model="form.tanggal" id="f_mtgl" placeholder="Pilih tanggal" display-format="j F Y" />
                            <x-input-error :messages="$errors->get('form.tanggal')" class="mt-1" />
                        </div>
                        <div class="md:col-span-3">
                            <x-input-label for="f_mket" value="Keterangan" />
                            <x-text-input wire:model="form.keterangan" id="f_mket" placeholder="Keterangan tambahan (opsional)" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('form.keterangan')" class="mt-1" />
                        </div>
                    </div>
                @elseif($tab === 'pengujian')
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <x-input-label for="f_pnama" value="Nama Pengujian" :required="true" />
                            <x-text-input wire:model="form.nama_pengujian" id="f_pnama" placeholder="Mis. NDT Test Block A" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('form.nama_pengujian')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="f_ptgl" value="Tanggal" />
                            <x-tanggal-picker wire:model="form.tanggal" id="f_ptgl" placeholder="Pilih tanggal" display-format="j F Y" />
                            <x-input-error :messages="$errors->get('form.tanggal')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="f_pstatus" value="Status" :required="true" />
                            <x-searchable-select wire:model="form.status" id="f_pstatus"
                                :options="collect($pengujianStatuses)->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->toArray()"
                                placeholder="Pilih status" :clearable="false" />
                            <x-input-error :messages="$errors->get('form.status')" class="mt-1" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="f_phasil" value="Hasil" />
                            <x-text-input wire:model="form.hasil" id="f_phasil" placeholder="Mis. Lulus, Memuaskan" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('form.hasil')" class="mt-1" />
                        </div>
                        <div class="md:col-span-3">
                            <x-input-label for="f_pket" value="Keterangan" />
                            <x-text-input wire:model="form.keterangan" id="f_pket" placeholder="Keterangan tambahan (opsional)" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('form.keterangan')" class="mt-1" />
                        </div>
                    </div>
                @elseif($tab === 'rapat')
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="f_rtgl" value="Tanggal Rapat" :required="true" />
                            <x-tanggal-picker wire:model="form.tanggal" id="f_rtgl" placeholder="Pilih tanggal" display-format="j F Y" required />
                            <x-input-error :messages="$errors->get('form.tanggal')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="f_rjenis" value="Jenis Rapat" :required="true" />
                            <x-searchable-select wire:model="form.jenis" id="f_rjenis"
                                :options="collect($rapatJenises)->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->toArray()"
                                placeholder="Pilih jenis" :clearable="false" />
                            <x-input-error :messages="$errors->get('form.jenis')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="f_rlokasi" value="Lokasi" />
                            <x-text-input wire:model="form.lokasi" id="f_rlokasi" placeholder="Mis. Ruang QC Galangan" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('form.lokasi')" class="mt-1" />
                        </div>
                        <div class="md:col-span-3">
                            <x-input-label for="f_rtopik" value="Topik / Hasil Rapat" />
                            <x-text-input wire:model="form.topik" id="f_rtopik" placeholder="Mis. Pembahasan progress & kendala material" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('form.topik')" class="mt-1" />
                        </div>
                    </div>
                @endif

                <div class="mt-5 flex flex-col sm:flex-row items-center justify-end gap-2">
                    <x-cancel-button wire:click="cancelForm" target="cancelForm" variant="secondary" size="md" class="w-full sm:w-auto" />
                    <x-loading-button type="submit" target="save" variant="primary" size="md" loadingText="Menyimpan..." class="w-full sm:w-auto">
                        {{ $formEditMode ? 'Update' : 'Simpan' }}
                    </x-loading-button>
                </div>
            </form>
        </div>
    @endif

    {{-- Table Card --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        @if($tab === 'drawing')
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nomor</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Judul</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Produksi</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Review MNO</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Approval Klas</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Comment</th>
                        @elseif($tab === 'vendor')
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nama</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Lingkup Pekerjaan</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kontak</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Keterangan</th>
                        @elseif($tab === 'material')
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Item</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kategori</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Jumlah</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tanggal</th>
                        @elseif($tab === 'pengujian')
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nama Pengujian</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tanggal</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Hasil</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Keterangan</th>
                        @elseif($tab === 'rapat')
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tanggal</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Jenis</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Lokasi</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Topik</th>
                        @endif
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($items as $item)
                        <tr wire:key="row-{{ $tab }}-{{ $item->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            @if($tab === 'drawing')
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ $item->nomor }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $item->judul }}</td>
                                <td class="px-4 py-3 whitespace-nowrap"><span class="px-2 py-1 text-xs font-medium rounded-full {{ $item->status_produksi->badgeClass() }}">{{ $item->status_produksi->label() }}</span></td>
                                <td class="px-4 py-3 whitespace-nowrap"><span class="px-2 py-1 text-xs font-medium rounded-full {{ $item->status_review_mno->badgeClass() }}">{{ $item->status_review_mno->label() }}</span></td>
                                <td class="px-4 py-3 whitespace-nowrap"><span class="px-2 py-1 text-xs font-medium rounded-full {{ $item->status_approval_klas->badgeClass() }}">{{ $item->status_approval_klas->label() }}</span></td>
                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    <div>MNO: {{ $item->mno_comment_open }}O / {{ $item->mno_comment_closed }}C / {{ $item->mno_comment_informasi }}I</div>
                                    <div>Klas: {{ $item->klas_comment_open }}O / {{ $item->klas_comment_closed }}C / {{ $item->klas_comment_informasi }}I</div>
                                </td>
                            @elseif($tab === 'vendor')
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ $item->nama }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $item->lingkup_pekerjaan ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $item->kontak ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $item->keterangan ?? '-' }}</td>
                            @elseif($tab === 'material')
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $item->nama_item }}</div>
                                    @if($item->spesifikasi)
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item->spesifikasi }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap"><span class="px-2 py-1 text-xs font-medium rounded-full {{ $item->kategori->badgeClass() }}">{{ $item->kategori->label() }}</span></td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white whitespace-nowrap">{{ $item->jumlah !== null ? rtrim(rtrim(number_format((float) $item->jumlah, 2, ',', '.'), '0'), ',').' '.($item->satuan ?? '') : '-' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap"><span class="px-2 py-1 text-xs font-medium rounded-full {{ $item->status->badgeClass() }}">{{ $item->status->label() }}</span></td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $item->tanggal?->translatedFormat('d M Y') ?? '-' }}</td>
                            @elseif($tab === 'pengujian')
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ $item->nama_pengujian }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $item->tanggal?->translatedFormat('d M Y') ?? '-' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap"><span class="px-2 py-1 text-xs font-medium rounded-full {{ $item->status->badgeClass() }}">{{ $item->status->label() }}</span></td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $item->hasil ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $item->keterangan ?? '-' }}</td>
                            @elseif($tab === 'rapat')
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white whitespace-nowrap">{{ $item->tanggal->translatedFormat('d M Y') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap"><span class="px-2 py-1 text-xs font-medium rounded-full {{ $item->jenis->badgeClass() }}">{{ $item->jenis->label() }}</span></td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $item->lokasi ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $item->topik ?? '-' }}</td>
                            @endif
                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    @can('proyek_data_update')
                                        <button wire:click="startEdit({{ $item->id }})"
                                            wire:loading.attr="disabled" wire:target="startEdit({{ $item->id }})"
                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 disabled:opacity-50"
                                            title="Edit">
                                            <svg wire:loading.class="hidden" wire:target="startEdit({{ $item->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            <svg wire:loading wire:target="startEdit({{ $item->id }})" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                            </svg>
                                        </button>
                                    @endcan
                                    @can('proyek_data_delete')
                                        <button wire:click="confirmDelete({{ $item->id }})"
                                            wire:loading.attr="disabled" wire:target="confirmDelete({{ $item->id }})"
                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50"
                                            title="Hapus">
                                            <svg wire:loading.class="hidden" wire:target="confirmDelete({{ $item->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            <svg wire:loading wire:target="confirmDelete({{ $item->id }})" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                            </svg>
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Belum ada data {{ strtolower($entityLabel) }} untuk kapal ini</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
            {{ $items->links() }}
        </div>
    </div>

    <x-delete-modal
        :show="$showDeleteModal"
        wire:model="showDeleteModal"
        title="Hapus {{ $entityLabel }}"
        message="Apakah Anda yakin ingin menghapus data ini?"
        :itemName="$deletingLabel"
        confirmMethod="delete"
    />
</div>
