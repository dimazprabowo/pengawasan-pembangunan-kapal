<div>
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center gap-3">
        <!-- Search -->
        <div class="flex-1">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari perusahaan..."
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
        </div>

        <!-- Filter Status -->
        <div class="w-full lg:w-48">
            <x-searchable-select
                wire:model.live="statusFilter"
                :options="collect($statuses)->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->toArray()"
                placeholder="Filter Status"
                searchPlaceholder="Cari status..."
            />
        </div>

        <div class="w-full lg:w-32">
            <x-searchable-select
                wire:model.live="perPage"
                :options="[
                    ['value' => '10', 'label' => '10'],
                    ['value' => '25', 'label' => '25'],
                    ['value' => '50', 'label' => '50'],
                    ['value' => '100', 'label' => '100']
                ]"
                placeholder="10"
                searchPlaceholder="Pilih jumlah..."
                :clearable="false"
            />
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2 flex-wrap">
            @can('companies_export_excel')
                <x-loading-button wire:click="exportExcel" target="exportExcel" variant="success" size="md" loadingText="Exporting..." title="Export Excel">
                    <x-slot:icon>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </x-slot:icon>
                    Excel
                </x-loading-button>
            @endcan
            @can('companies_export_pdf')
                <x-loading-button wire:click="exportPdf" target="exportPdf" variant="danger" size="md" loadingText="Exporting..." title="Export PDF">
                    <x-slot:icon>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </x-slot:icon>
                    PDF
                </x-loading-button>
            @endcan
            @can('companies_create')
                <x-loading-button wire:click="create" target="create" variant="primary" size="md" loadingText="Memuat..." class="flex-1 lg:flex-none">
                    <x-slot:icon>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </x-slot:icon>
                    Tambah Perusahaan
                </x-loading-button>
            @endcan
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Perusahaan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kontak</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">PIC</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Jenis Kapal</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($companies as $company)
                        <tr class="hover:bg-purple-50 dark:hover:bg-purple-900/10 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center text-white font-semibold text-xs">
                                            {{ substr($company->code, 0, 2) }}
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $company->name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $company->code }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white">{{ $company->email ?? '-' }}</div>
                                @if($company->phone)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $company->phone }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white">{{ $company->pic_name ?? '-' }}</div>
                                @if($company->pic_email)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $company->pic_email }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400">
                                    {{ $company->users_count }} user
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400">
                                    {{ $company->jenis_kapal_count }} kapal
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @can('companies_update')
                                    <button wire:click="toggleStatus({{ $company->id }})"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2
                                            {{ $company->status->value === 'active' ? 'bg-purple-600' : 'bg-gray-200 dark:bg-gray-700' }}">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform
                                            {{ $company->status->value === 'active' ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </button>
                                @else
                                    @php
                                        $statusColors = [
                                            'active' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
                                            'inactive' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400',
                                            'suspended' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
                                        ];
                                    @endphp
                                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $statusColors[$company->status->value] ?? $statusColors['inactive'] }}">
                                        {{ $company->status->label() }}
                                    </span>
                                @endcan
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    @can('companies_update')
                                        <button wire:click="edit({{ $company->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="edit({{ $company->id }})"
                                            class="text-purple-600 hover:text-purple-900 dark:text-purple-400 dark:hover:text-purple-300 disabled:opacity-50"
                                            title="Edit">
                                            <svg wire:loading.class="hidden" wire:target="edit({{ $company->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            <svg wire:loading wire:target="edit({{ $company->id }})" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </button>
                                    @endcan
                                    @can('companies_delete')
                                        @if($company->users_count === 0)
                                            <button wire:click="confirmDelete({{ $company->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="confirmDelete({{ $company->id }})"
                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50"
                                                title="Hapus">
                                                <svg wire:loading.class="hidden" wire:target="confirmDelete({{ $company->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                                <svg wire:loading wire:target="confirmDelete({{ $company->id }})" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Tidak ada perusahaan ditemukan</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $companies->links() }}
        </div>
    </div>

    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75" @click="$wire.closeModal()"></div>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <form wire:submit="save">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                {{ $editMode ? 'Edit Perusahaan' : 'Tambah Perusahaan' }}
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Informasi Perusahaan -->
                                <div class="md:col-span-2">
                                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Informasi Perusahaan</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Kode Perusahaan <span class="text-red-500">*</span></label>
                                    <input wire:model="code" type="text" required placeholder="Contoh: ACME"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white uppercase">
                                    @error('code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Perusahaan <span class="text-red-500">*</span></label>
                                    <input wire:model="name" type="text" required
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Perusahaan</label>
                                    <input wire:model="email" type="email"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Telepon Perusahaan</label>
                                    <input wire:model="phone" type="text"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    @error('phone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Alamat</label>
                                    <textarea wire:model="address" rows="2"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                                    @error('address') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <!-- Informasi PIC -->
                                <div class="md:col-span-2 mt-2">
                                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Person In Charge (PIC)</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama PIC</label>
                                    <input wire:model="pic_name" type="text"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    @error('pic_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email PIC</label>
                                    <input wire:model="pic_email" type="email"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    @error('pic_email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Telepon PIC</label>
                                    <input wire:model="pic_phone" type="text"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    @error('pic_phone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status <span class="text-red-500">*</span></label>
                                    <x-searchable-select
                                        wire:model.live="status"
                                        :options="collect($statuses)->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->toArray()"
                                        placeholder="Pilih Status"
                                        searchPlaceholder="Cari status..."
                                        :error="$errors->has('status')"
                                    />
                                    @error('status') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-900 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                            <x-loading-button type="submit" target="save" variant="primary" size="lg"
                                loadingText="Menyimpan..." class="w-full sm:w-auto">
                                {{ $editMode ? 'Update' : 'Simpan' }}
                            </x-loading-button>
                            <x-loading-button type="button" @click="$wire.closeModal()" variant="secondary" size="lg"
                                class="mt-3 sm:mt-0 w-full sm:w-auto">
                                Batal
                            </x-loading-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Delete Confirmation Modal -->
    <x-delete-modal 
        :show="$showDeleteModal"
        wire:model="showDeleteModal"
        title="Hapus Perusahaan"
        message="Apakah Anda yakin ingin menghapus perusahaan"
        :itemName="$deletingCompanyName"
        confirmMethod="delete"
    />
</div>
