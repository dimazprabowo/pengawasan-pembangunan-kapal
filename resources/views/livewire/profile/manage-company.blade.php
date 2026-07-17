<div>
    @if($canManage)
        {{-- Company Information Section --}}
        <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
            <div class="max-w-xl">
                <section>
                    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                Informasi Perusahaan
                            </h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                @if($hasCompany)
                                    Informasi perusahaan tempat Anda terdaftar.
                                @else
                                    Anda belum terdaftar di perusahaan manapun.
                                @endif
                            </p>
                        </div>
                        <button wire:click="openModal" 
                            wire:loading.attr="disabled"
                            wire:target="openModal"
                            class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition-colors gap-2 w-full sm:w-auto whitespace-nowrap">
                            
                            {{-- Loading Icon --}}
                            <svg wire:loading wire:target="openModal" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>

                            @if($hasCompany)
                                <svg wire:loading.remove wire:target="openModal" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                <span wire:loading.remove wire:target="openModal">Edit</span>
                                <span wire:loading wire:target="openModal">Memuat...</span>
                            @else
                                <svg wire:loading.remove wire:target="openModal" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                <span wire:loading.remove wire:target="openModal">Daftarkan Perusahaan</span>
                                <span wire:loading wire:target="openModal">Memuat...</span>
                            @endif
                        </button>
                    </header>

                    @if($hasCompany)
                        <div class="mt-6 space-y-4">
                            {{-- Company Name --}}
                            <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 sm:w-32 flex-shrink-0">
                                    Nama Perusahaan
                                </dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">
                                    {{ $company->name }}
                                </dd>
                            </div>

                            {{-- Company Code --}}
                            @if($company->code)
                            <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 sm:w-32 flex-shrink-0">
                                    Kode
                                </dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">
                                    <span class="inline-flex items-center whitespace-nowrap px-2.5 py-0.5 rounded-md text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                        {{ $company->code }}
                                    </span>
                                </dd>
                            </div>
                            @endif

                            {{-- NPWP --}}
                            @if($company->npwp)
                            <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 sm:w-32 flex-shrink-0">
                                    NPWP
                                </dt>
                                <dd class="text-sm font-mono text-gray-900 dark:text-gray-100">
                                    {{ $company->npwp_formatted }}
                                </dd>
                            </div>
                            @endif

                            {{-- Company Email --}}
                            @if($company->email)
                            <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 sm:w-32 flex-shrink-0">
                                    Email
                                </dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">
                                    <a href="mailto:{{ $company->email }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ $company->email }}
                                    </a>
                                </dd>
                            </div>
                            @endif

                            {{-- Company Phone --}}
                            @if($company->phone)
                            <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 sm:w-32 flex-shrink-0">
                                    Telepon
                                </dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">
                                    {{ $company->phone }}
                                </dd>
                            </div>
                            @endif

                            {{-- Company Address --}}
                            @if($company->address)
                            <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 sm:w-32 flex-shrink-0">
                                    Alamat
                                </dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">
                                    {{ $company->address }}
                                </dd>
                            </div>
                            @endif

                            {{-- Divider --}}
                            @if($company->pic_name || $company->pic_email || $company->pic_phone)
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-4">
                                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                    Person In Charge (PIC)
                                </h3>
                                
                                @if($company->pic_name)
                                <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4 mb-3">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 sm:w-32 flex-shrink-0">
                                        Nama PIC
                                    </dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">
                                        {{ $company->pic_name }}
                                    </dd>
                                </div>
                                @endif

                                @if($company->pic_email)
                                <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4 mb-3">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 sm:w-32 flex-shrink-0">
                                        Email PIC
                                    </dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">
                                        <a href="mailto:{{ $company->pic_email }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                            {{ $company->pic_email }}
                                        </a>
                                    </dd>
                                </div>
                                @endif

                                @if($company->pic_phone)
                                <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 sm:w-32 flex-shrink-0">
                                        Telepon PIC
                                    </dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">
                                        {{ $company->pic_phone }}
                                    </dd>
                                </div>
                                @endif
                            </div>
                            @endif

                            {{-- Company Status --}}
                            <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 sm:w-32 flex-shrink-0">
                                    Status
                                </dt>
                                <dd class="text-sm">
                                    @if($company->status->value === 'active')
                                        <span class="inline-flex items-center whitespace-nowrap px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                            Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center whitespace-nowrap px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                            </svg>
                                            Tidak Aktif
                                        </span>
                                    @endif
                                </dd>
                            </div>
                        </div>
                    @else
                        <div class="mt-6 p-6 bg-gray-50 dark:bg-gray-700/50 rounded-lg text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Klik tombol "Daftarkan Perusahaan" untuk mendaftarkan perusahaan Anda.
                            </p>
                        </div>
                    @endif
                </section>
            </div>
        </div>
    @elseif($hasCompany)
        {{-- Show company info without edit button for users without permission --}}
        <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
            <div class="max-w-xl">
                <section>
                    <header>
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            Informasi Perusahaan
                        </h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Informasi perusahaan tempat Anda terdaftar.
                        </p>
                    </header>

                    <div class="mt-6 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 sm:w-32 flex-shrink-0">
                                Nama Perusahaan
                            </dt>
                            <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">
                                {{ $company->name }}
                            </dd>
                        </div>

                        @if($company->code)
                        <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 sm:w-32 flex-shrink-0">
                                Kode
                            </dt>
                            <dd class="text-sm text-gray-900 dark:text-gray-100">
                                <span class="inline-flex items-center whitespace-nowrap px-2.5 py-0.5 rounded-md text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                    {{ $company->code }}
                                </span>
                            </dd>
                        </div>
                        @endif

                        @if($company->npwp)
                        <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 sm:w-32 flex-shrink-0">
                                NPWP
                            </dt>
                            <dd class="text-sm font-mono text-gray-900 dark:text-gray-100">
                                {{ $company->npwp_formatted }}
                            </dd>
                        </div>
                        @endif

                        @if($company->email)
                        <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 sm:w-32 flex-shrink-0">
                                Email
                            </dt>
                            <dd class="text-sm text-gray-900 dark:text-gray-100">
                                <a href="mailto:{{ $company->email }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                    {{ $company->email }}
                                </a>
                            </dd>
                        </div>
                        @endif

                        @if($company->phone)
                        <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 sm:w-32 flex-shrink-0">
                                Telepon
                            </dt>
                            <dd class="text-sm text-gray-900 dark:text-gray-100">
                                {{ $company->phone }}
                            </dd>
                        </div>
                        @endif

                        @if($company->address)
                        <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 sm:w-32 flex-shrink-0">
                                Alamat
                            </dt>
                            <dd class="text-sm text-gray-900 dark:text-gray-100">
                                {{ $company->address }}
                            </dd>
                        </div>
                        @endif

                        @if($company->pic_name || $company->pic_email || $company->pic_phone)
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-4">
                            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                Person In Charge (PIC)
                            </h3>
                            
                            @if($company->pic_name)
                            <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4 mb-3">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 sm:w-32 flex-shrink-0">
                                    Nama PIC
                                </dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">
                                    {{ $company->pic_name }}
                                </dd>
                            </div>
                            @endif

                            @if($company->pic_email)
                            <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4 mb-3">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 sm:w-32 flex-shrink-0">
                                    Email PIC
                                </dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">
                                    <a href="mailto:{{ $company->pic_email }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ $company->pic_email }}
                                    </a>
                                </dd>
                            </div>
                            @endif

                            @if($company->pic_phone)
                            <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 sm:w-32 flex-shrink-0">
                                    Telepon PIC
                                </dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">
                                    {{ $company->pic_phone }}
                                </dd>
                            </div>
                            @endif
                        </div>
                        @endif

                        <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 sm:w-32 flex-shrink-0">
                                Status
                            </dt>
                            <dd class="text-sm">
                                @if($company->status->value === 'active')
                                    <span class="inline-flex items-center whitespace-nowrap px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center whitespace-nowrap px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                        </svg>
                                        Tidak Aktif
                                    </span>
                                @endif
                            </dd>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    @endif

    {{-- Modal --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeModal"></div>

                <div class="inline-block align-bottom w-full bg-white dark:bg-gray-800 rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                {{ $isEditMode ? 'Edit Informasi Perusahaan' : 'Daftarkan Perusahaan' }}
                            </h3>
                            <x-cancel-button icon wire:click="closeModal" target="closeModal" />
                        </div>

                        <form wire:submit.prevent="save" class="space-y-4">
                            <x-company-form :editMode="$isEditMode" :showStatus="false" />

                            {{-- Buttons --}}
                            <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <x-cancel-button wire:click="closeModal" target="closeModal" />
                                <button type="submit" 
                                    wire:loading.attr="disabled"
                                    wire:target="save"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg transition-colors">
                                    
                                    {{-- Loading Icon --}}
                                    <svg wire:loading wire:target="save" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>

                                    <span wire:loading.remove wire:target="save">
                                        {{ $isEditMode ? 'Perbarui' : 'Simpan' }}
                                    </span>
                                    <span wire:loading wire:target="save">
                                        {{ $isEditMode ? 'Memperbarui...' : 'Menyimpan...' }}
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
