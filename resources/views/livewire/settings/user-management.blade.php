<div>
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center gap-3">
        <!-- Search -->
        <div class="flex-1">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari user..."
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
        </div>
        
        <!-- Filters -->
        <div class="w-full lg:w-48">
            <x-searchable-select
                wire:model.live="roleFilter"
                :options="$roles->map(fn($r) => ['value' => $r->name, 'label' => ucfirst($r->name)])->toArray()"
                placeholder="Filter Role"
                searchPlaceholder="Cari role..."
            />
        </div>
        <div class="w-full lg:w-40">
            <x-searchable-select
                wire:model.live="statusFilter"
                :options="[['value' => '1', 'label' => 'Aktif'], ['value' => '0', 'label' => 'Nonaktif']]"
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
            @can('users_export_excel')
                <x-loading-button wire:click="exportExcel" target="exportExcel" variant="success" size="md" loadingText="Exporting..." title="Export Excel">
                    <x-slot:icon>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </x-slot:icon>
                    Excel
                </x-loading-button>
            @endcan
            @can('users_export_pdf')
                <x-loading-button wire:click="exportPdf" target="exportPdf" variant="danger" size="md" loadingText="Exporting..." title="Export PDF">
                    <x-slot:icon>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </x-slot:icon>
                    PDF
                </x-loading-button>
            @endcan
            @can('users_create')
                <x-loading-button wire:click="create" target="create" variant="primary" size="md" loadingText="Memuat..." class="flex-1 lg:flex-none">
                    <x-slot:icon>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </x-slot:icon>
                    Tambah User
                </x-loading-button>
            @endcan
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Company</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($users as $user)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold">
                                            {{ substr($user->name, 0, 1) }}
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $user->name }}</div>
                                        @if($user->position)
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $user->position }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white">{{ $user->email }}</div>
                                @if($user->phone)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $user->phone }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $roleColors = [
                                        'super admin' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-400',
                                        'admin' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400',
                                        'manager' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/20 dark:text-indigo-400',
                                        'sbu' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
                                        'kacab' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400',
                                        'inspector' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400',
                                        'user' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400',
                                    ];
                                @endphp
                                @if($user->roles->isNotEmpty())
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($user->roles as $role)
                                            @php $colorClass = $roleColors[$role->name] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400'; @endphp
                                            <span class="px-2 py-1 text-xs font-medium rounded-full {{ $colorClass }}">
                                                {{ ucfirst($role->name) }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400">No Role</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white">
                                    {{ $user->company->name ?? '-' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @can('users_update')
                                    <button wire:click="toggleActive({{ $user->id }})" 
                                        @if($user->id == auth()->id()) disabled title="Tidak dapat menonaktifkan akun sendiri" @endif
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2
                                            {{ $user->is_active ? 'bg-blue-600' : 'bg-gray-200 dark:bg-gray-700' }}
                                            {{ $user->id == auth()->id() ? 'opacity-50 cursor-not-allowed' : '' }}">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform
                                            {{ $user->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </button>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $user->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400' }}">
                                        {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                @endcan
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    @can('users_update')
                                        <button wire:click="edit({{ $user->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="edit({{ $user->id }})"
                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 disabled:opacity-50"
                                            title="Edit">
                                            <svg wire:loading.class="hidden" wire:target="edit({{ $user->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            <svg wire:loading wire:target="edit({{ $user->id }})" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </button>
                                        <button wire:click="openResetPasswordModal({{ $user->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="openResetPasswordModal({{ $user->id }})"
                                            class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300 disabled:opacity-50"
                                            title="Reset Password">
                                            <svg wire:loading.class="hidden" wire:target="openResetPasswordModal({{ $user->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                            </svg>
                                            <svg wire:loading wire:target="openResetPasswordModal({{ $user->id }})" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </button>
                                    @endcan
                                    @can('users_delete')
                                        @if($user->id != auth()->id())
                                            <button wire:click="confirmDelete({{ $user->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="confirmDelete({{ $user->id }})"
                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50"
                                                title="Hapus">
                                                <svg wire:loading.class="hidden" wire:target="confirmDelete({{ $user->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                                <svg wire:loading wire:target="confirmDelete({{ $user->id }})" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
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
                            <td colspan="6" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Tidak ada user ditemukan</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $users->links() }}
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
                                {{ $editMode ? 'Edit User' : 'Tambah User' }}
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                                    <input wire:model="name" type="text" required
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email <span class="text-red-500">*</span></label>
                                    <input wire:model="email" type="email" required
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div x-data="{ showPassword: false }">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Password @if(!$editMode)<span class="text-red-500">*</span>@endif
                                        <br>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $editMode ? '(kosongkan jika tidak ingin mengubah)' : '' }}
                                        </span>
                                    </label>
                                    
                                    <div class="relative">
                                        <input wire:model="password" :type="showPassword ? 'text' : 'password'" {{ !$editMode ? 'required' : '' }}
                                            class="w-full px-3 py-2 pr-10 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                        <button type="button"
                                                @click="showPassword = !showPassword"
                                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                            <svg x-show="!showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            <svg x-show="showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                            </svg>
                                        </button>
                                    </div>
                                    @error('password') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div x-data="{ showPasswordConfirmation: false }">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Konfirmasi Password @if(!$editMode)<span class="text-red-500">*</span>@endif
                                        <br>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $editMode ? '(kosongkan jika tidak ingin mengubah)' : '' }}
                                        </span>
                                    </label>
                                    <div class="relative">
                                        <input wire:model="password_confirmation" :type="showPasswordConfirmation ? 'text' : 'password'" {{ !$editMode ? 'required' : '' }}
                                            class="w-full px-3 py-2 pr-10 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                        <button type="button"
                                                @click="showPasswordConfirmation = !showPasswordConfirmation"
                                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                            <svg x-show="!showPasswordConfirmation" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            <svg x-show="showPasswordConfirmation" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Role <span class="text-red-500">*</span></label>
                                    <x-multi-searchable-select
                                        wire:model="selectedRoles"
                                        :options="$roles->map(fn($r) => ['value' => $r->name, 'label' => ucfirst($r->name)])->toArray()"
                                        placeholder="Pilih role..."
                                        searchPlaceholder="Cari role..."
                                        :error="$errors->has('selectedRoles')"
                                    />
                                    @error('selectedRoles') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    @error('selectedRoles.*') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Company (Optional)</label>
                                    <x-searchable-select
                                        wire:model.live="company_id"
                                        :options="$companies->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->toArray()"
                                        placeholder="Tidak ada"
                                        searchPlaceholder="Cari perusahaan..."
                                        :error="$errors->has('company_id')"
                                    />
                                    @error('company_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Telepon</label>
                                    <input wire:model="phone" type="text"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    @error('phone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Posisi/Jabatan</label>
                                    <input wire:model="position" type="text"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    @error('position') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label class="flex items-center">
                                        <input wire:model="is_active" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">User Aktif</span>
                                    </label>
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

    <!-- Reset Password Modal -->
    <x-reset-password-modal />

    <!-- Delete Confirmation Modal -->
    <x-delete-modal 
        :show="$showDeleteModal"
        wire:model="showDeleteModal"
        title="Hapus User"
        message="Apakah Anda yakin ingin menghapus user"
        :itemName="$deletingUserName"
        confirmMethod="delete"
    />
</div>
