@props([
    'show' => false,
])

@if($show)
    <div class="fixed inset-0 z-[60] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80 transition-opacity" wire:click="closeKurvaSImportModal"></div>
            
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-2xl z-10">
                {{-- Header --}}
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Import Kurva S Template</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Upload file Excel untuk import data Kurva S</p>
                            </div>
                        </div>
                        <button wire:click="closeKurvaSImportModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Body --}}
                <div class="px-6 py-5">
                    {{-- Info Alert --}}
                    <div class="mb-5 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div class="flex-1">
                                <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-300 mb-1">Petunjuk Import</h4>
                                <ul class="text-xs text-blue-800 dark:text-blue-400 space-y-1 list-disc list-inside">
                                    <li>File harus dalam format <strong>.xlsx</strong> atau <strong>.xls</strong></li>
                                    <li>Maksimal ukuran file: <strong>5MB</strong></li>
                                    <li>Gunakan template yang sudah di-export untuk memastikan format benar</li>
                                    <li>Total bobot work groups harus = <strong>100%</strong></li>
                                    <li>Total pct_rencana per work group harus = <strong>100%</strong></li>
                                    <li><strong class="text-red-600 dark:text-red-400">⚠ Data lama akan dihapus dan diganti dengan data baru</strong></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    {{-- File Upload --}}
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            File Excel <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input 
                                type="file" 
                                wire:model="kurvaS_file" 
                                accept=".xlsx,.xls"
                                class="block w-full text-sm text-gray-900 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer bg-gray-50 dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-l-lg file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 dark:file:bg-purple-900/30 dark:file:text-purple-400 dark:hover:file:bg-purple-900/50
                                @error('kurvaS_file') border-red-500 @enderror">
                            
                            <div wire:loading wire:target="kurvaS_file" class="absolute inset-0 bg-white/80 dark:bg-gray-800/80 rounded-lg flex items-center justify-center">
                                <div class="flex items-center gap-2 text-purple-600 dark:text-purple-400">
                                    <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span class="text-sm font-medium">Memuat file...</span>
                                </div>
                            </div>
                        </div>
                        @error('kurvaS_file')
                            <p class="mt-1.5 text-sm text-red-600 dark:text-red-400 flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                        
                        @if($this->kurvaS_file)
                            <div class="mt-2 flex items-center gap-2 text-sm text-green-600 dark:text-green-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>File terpilih: <strong>{{ $this->kurvaS_file->getClientOriginalName() }}</strong></span>
                            </div>
                        @endif
                    </div>

                    {{-- Warning --}}
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <div>
                                <h4 class="text-sm font-semibold text-red-900 dark:text-red-300 mb-1">Peringatan Penting!</h4>
                                <p class="text-xs text-red-800 dark:text-red-400">
                                    Proses import akan <strong>menghapus semua data Kurva S yang ada</strong> dan menggantinya dengan data dari file Excel. 
                                    Pastikan Anda sudah melakukan backup jika diperlukan. Proses ini <strong>tidak dapat dibatalkan</strong>.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 rounded-b-2xl flex items-center justify-end gap-3">
                    <button 
                        wire:click="closeKurvaSImportModal" 
                        type="button"
                        class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 rounded-xl transition">
                        Batal
                    </button>
                    <button 
                        wire:click="importKurvaSTemplate" 
                        wire:loading.attr="disabled"
                        wire:target="importKurvaSTemplate"
                        type="button"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white text-sm font-semibold rounded-xl shadow-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg wire:loading.remove wire:target="importKurvaSTemplate" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <svg wire:loading wire:target="importKurvaSTemplate" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="importKurvaSTemplate">Import Sekarang</span>
                        <span wire:loading wire:target="importKurvaSTemplate">Mengimport...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
