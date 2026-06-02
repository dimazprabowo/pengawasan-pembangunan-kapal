@props([
    'variant' => 'default', // 'default' | 'compact'
])

<div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
    {{-- Export dengan Data --}}
    <button 
        wire:click="exportKurvaSTemplate(true)"
        wire:loading.attr="disabled"
        wire:target="exportKurvaSTemplate(true)"
        class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white text-sm font-semibold rounded-lg shadow-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed"
        title="Export Kurva S dengan Data">
        <svg wire:loading.remove wire:target="exportKurvaSTemplate(true)" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <svg wire:loading wire:target="exportKurvaSTemplate(true)" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span wire:loading.remove wire:target="exportKurvaSTemplate(true)">Export Data</span>
        <span wire:loading wire:target="exportKurvaSTemplate(true)">Mengexport Data...</span>
    </button>

    {{-- Export Template Kosong --}}
    <button 
        wire:click="exportKurvaSTemplate(false)"
        wire:loading.attr="disabled"
        wire:target="exportKurvaSTemplate(false)"
        class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-semibold rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed"
        title="Export Template Kosong">
        <svg wire:loading.remove wire:target="exportKurvaSTemplate(false)" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <svg wire:loading wire:target="exportKurvaSTemplate(false)" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span wire:loading.remove wire:target="exportKurvaSTemplate(false)">Template Kosong</span>
        <span wire:loading wire:target="exportKurvaSTemplate(false)">Mendownload Template...</span>
    </button>

    {{-- Import Kurva S --}}
    <button 
        wire:click="openKurvaSImportModal"
        wire:loading.attr="disabled"
        wire:target="openKurvaSImportModal"
        class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white text-sm font-semibold rounded-lg shadow-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed"
        title="Import Kurva S dari Excel">
        <svg wire:loading.remove wire:target="openKurvaSImportModal" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
        </svg>
        <svg wire:loading wire:target="openKurvaSImportModal" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span wire:loading.remove wire:target="openKurvaSImportModal">Import</span>
        <span wire:loading wire:target="openKurvaSImportModal">Loading...</span>
    </button>
</div>
