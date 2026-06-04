@props(['laporanExternal', 'deletingExternalId'])

<div class="mt-6">
    <div class="flex items-center justify-between mb-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Laporan External Tambahan
            <span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded-full @if(count($laporanExternal) > 0) bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 @endif">
                {{ count($laporanExternal) }} laporan
            </span>
        </label>
        <button type="button" wire:click="addExternalReport" wire:key="add-external-btn"
            wire:loading.attr="disabled" wire:target="addExternalReport"
            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 transition-colors">
            @if(count($laporanExternal) === 0)
                <svg wire:loading.remove wire:target="addExternalReport" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <svg wire:loading wire:target="addExternalReport" class="animate-spin w-4 h-4" wire:key="add-loading-icon-empty" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span wire:loading.remove wire:target="addExternalReport">Tambah Laporan</span>
                <span wire:loading wire:target="addExternalReport">Memproses...</span>
            @else
                <svg wire:loading.remove wire:target="addExternalReport" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <svg wire:loading wire:target="addExternalReport" class="animate-spin w-4 h-4" wire:key="add-loading-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span wire:loading.remove wire:target="addExternalReport">Tambah</span>
                <span wire:loading wire:target="addExternalReport">Memproses...</span>
            @endif
        </button>
    </div>

    @if(count($laporanExternal) > 0)
        <div class="space-y-3">
            @foreach($laporanExternal as $index => $external)
                <div wire:key="external-{{ $external['id'] }}" class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="flex items-start justify-between gap-4 mb-3">
                        <div class="flex-1 min-w-0">
                            <input type="text" wire:model="laporanExternal.{{ $index }}.judul"
                                placeholder="Judul laporan (wajib)"
                                class="w-full px-3 py-2 text-sm font-medium text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:focus:ring-blue-600 transition-colors">
                            @error('laporanExternal.'.$index.'.judul')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="button"
                            wire:click="confirmDeleteExternal('{{ $external['id'] }}')"
                            wire:loading.attr="disabled"
                            wire:target="confirmDeleteExternal('{{ $external['id'] }}')"
                            class="text-red-400 hover:text-red-600 p-1 disabled:opacity-50" title="Hapus laporan">
                            <svg wire:loading.class="hidden" wire:target="confirmDeleteExternal('{{ $external['id'] }}')" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            <svg wire:loading wire:target="confirmDeleteExternal('{{ $external['id'] }}')" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="mb-3">
                        <textarea wire:model="laporanExternal.{{ $index }}.deskripsi"
                            placeholder="Deskripsi (opsional)"
                            rows="2"
                            class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:focus:ring-blue-600 resize-none transition-colors"></textarea>
                        @error('laporanExternal.'.$index.'.deskripsi')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- File Upload --}}
                    <div>
                        @if(isset($external['file']) && $external['file'] && is_object($external['file']))
                            {{-- File Selected (New Upload) --}}
                            <div class="flex items-center gap-2 px-3 py-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                                <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span class="text-xs text-blue-700 dark:text-blue-300 truncate flex-1">{{ $external['file']->getClientOriginalName() }}</span>
                                <span class="text-xs text-blue-500 dark:text-blue-400">{{ number_format($external['file']->getSize() / 1024, 0) }} KB</span>
                                <button type="button" wire:click="removeExternalFile({{ $index }})"
                                    wire:loading.attr="disabled"
                                    wire:target="removeExternalFile({{ $index }})"
                                    class="text-red-500 hover:text-red-700 p-0.5 disabled:opacity-50" title="Hapus file">
                                    <svg wire:loading.class="hidden" wire:target="removeExternalFile({{ $index }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    <svg wire:loading wire:target="removeExternalFile({{ $index }})" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </button>
                            </div>
                        @elseif(isset($external['existing_file_name']) && $external['existing_file_name'])
                            {{-- Existing File from Database --}}
                            <div class="flex items-center gap-2 p-2 bg-gray-50 dark:bg-gray-900/30 rounded-lg border border-gray-200 dark:border-gray-700">
                                @php
                                    $isProcessing = isset($external['file_status']) && in_array($external['file_status'], ['pending', 'processing']);
                                    $isFailed = isset($external['file_status']) && $external['file_status'] === 'failed';
                                    $isCompleted = isset($external['file_status']) && $external['file_status'] === 'completed';
                                @endphp
                                @if($isProcessing)
                                    <svg class="animate-spin w-5 h-5 text-blue-500 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                @elseif($isFailed)
                                    <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $external['existing_file_name'] }}</p>
                                    @if(isset($external['existing_file_size']))
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($external['existing_file_size'] / 1024, 1) }} KB</p>
                                    @endif
                                    @if($isFailed && isset($external['file_error']))
                                        <p class="text-xs text-red-600 dark:text-red-400">{{ $external['file_error'] }}</p>
                                    @endif
                                </div>
                                @if($isCompleted)
                                    <button type="button" wire:click="downloadExternalFile({{ $index }})"
                                        wire:loading.attr="disabled" wire:target="downloadExternalFile({{ $index }})"
                                        class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                        <svg wire:loading.remove wire:target="downloadExternalFile({{ $index }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        <svg wire:loading wire:target="downloadExternalFile({{ $index }})" wire:key="download-loading-{{ $index }}" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span wire:loading.remove wire:target="downloadExternalFile({{ $index }})">Download</span>
                                        <span wire:loading wire:target="downloadExternalFile({{ $index }})">Mengunduh...</span>
                                    </button>
                                @endif
                            </div>
                        @else
                            {{-- Upload Area with Loading Progress --}}
                            <div x-data="{ uploading: false, progress: 0 }"
                                 x-on:livewire-upload-start="uploading = true"
                                 x-on:livewire-upload-finish="uploading = false; progress = 0"
                                 x-on:livewire-upload-cancel="uploading = false"
                                 x-on:livewire-upload-error="uploading = false"
                                 x-on:livewire-upload-progress="progress = $event.detail.progress">
                                <label class="flex flex-col items-center justify-center w-full px-3 py-3 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:border-blue-400 dark:hover:border-blue-500 transition-colors bg-white dark:bg-gray-800">
                                    <div x-show="!uploading" class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                        </svg>
                                        <span>Klik untuk upload file</span>
                                    </div>
                                    <div x-show="uploading" x-cloak class="flex items-center justify-center gap-2">
                                        <svg class="animate-spin w-3 h-3 text-blue-500" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="text-xs text-blue-600" x-text="progress + '%'"></span>
                                    </div>
                                    <input type="file" wire:model="laporanExternal.{{ $index }}.file" class="hidden"
                                        accept=".{{ implode(',.', get_allowed_mimes_array('laporan_external')) }}">
                                </label>
                            </div>
                        @endif
                        @error('laporanExternal.'.$index.'.file') 
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ get_upload_config_display('laporan_external') }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="p-6 text-center border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Belum ada laporan external tambahan
            </p>
            <p class="text-xs text-gray-400 dark:text-gray-500">
                Klik "Tambah Laporan" untuk menambahkan laporan external
            </p>
        </div>
    @endif

</div>
