<x-document-confirm-modal
    :show="$showMissingReports"
    showProperty="showMissingReports"
    title="Laporan Sumber Belum Lengkap"
    message="Generate belum dapat dilanjutkan. Lengkapi laporan berikut terlebih dahulu. Laporan berstatus Libur tetap dihitung tersedia."
    type="regenerate"
    :show-confirm="false"
    cancelLabel="Tutup"
    cancelAction="closeMissingReports"
    confirmAction="closeMissingReports"
>
    <div class="mt-4 max-h-80 overflow-y-auto custom-scrollbar space-y-3">
        @foreach($missingReports as $index => $missing)
            <div wire:key="missing-{{ $missing['type'] }}-{{ $missing['value'] }}" class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/40 p-3">
                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $missing['label'] }}</p>
                @if(!$missing['can_create'])
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Tanggal pelaporan belum tiba.</p>
                @else
                @can('laporan_create')
                    <x-loading-button wire:click="createMissingReport({{ $index }})" target="createMissingReport({{ $index }})" variant="primary" size="sm" loadingText="Membuka..." class="mt-2 w-full sm:w-auto" title="Buat laporan yang belum tersedia">
                        <x-slot:icon>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        </x-slot:icon>
                        Buat Laporan
                    </x-loading-button>
                @endcan
                @endif
            </div>
        @endforeach
    </div>
</x-document-confirm-modal>
