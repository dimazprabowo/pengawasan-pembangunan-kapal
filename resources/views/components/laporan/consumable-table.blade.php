@props([
    'rows' => [],
    'rowsModel' => '',
    'addMethod' => '',
    'deletePrefix' => '',
    'wireKeyPrefix' => 'consumable',
    'readonly' => false,
])

<div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-between mb-3">
        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">D. Consumable dan Material</h4>
        @if(!$readonly)
            <button type="button"
                wire:click="{{ $addMethod }}"
                wire:loading.attr="disabled"
                wire:target="{{ $addMethod }}"
                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/30 rounded-lg transition-colors disabled:opacity-50">
                <svg wire:loading.class="hidden" wire:target="{{ $addMethod }}" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <svg wire:loading wire:target="{{ $addMethod }}" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Tambah Consumable</span>
            </button>
        @endif
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-10">No</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Jenis</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Jumlah</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Keterangan</th>
                    @if(!$readonly)
                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($rows as $rIndex => $row)
                    <tr wire:key="{{ $wireKeyPrefix }}-{{ $rIndex }}">
                        <td class="px-3 py-2 text-sm text-center text-gray-500 dark:text-gray-400">{{ $rIndex + 1 }}</td>
                        @if($readonly)
                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">{{ $row['jenis'] ?? '-' }}</td>
                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">{{ $row['jumlah'] ?? '-' }}</td>
                            <td class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $row['keterangan'] ?? '-' }}</td>
                        @else
                            <td class="px-3 py-2">
                                <input type="text" wire:model="{{ $rowsModel }}.{{ $rIndex }}.jenis"
                                    class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                    placeholder="Jenis">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" wire:model="{{ $rowsModel }}.{{ $rIndex }}.jumlah" min="1"
                                    class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                    placeholder="Jumlah">
                            </td>
                            <td class="px-3 py-2">
                                <input type="text" wire:model="{{ $rowsModel }}.{{ $rIndex }}.keterangan"
                                    class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                    placeholder="Keterangan">
                            </td>
                            <td class="px-3 py-2 text-center">
                                <button type="button"
                                    wire:click="{{ $deletePrefix }}{{ $rIndex }})"
                                    wire:loading.attr="disabled"
                                    wire:target="{{ $deletePrefix }}{{ $rIndex }})"
                                    class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50" title="Hapus">
                                    <svg wire:loading.class="hidden" wire:target="{{ $deletePrefix }}{{ $rIndex }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    <svg wire:loading wire:target="{{ $deletePrefix }}{{ $rIndex }})" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $readonly ? 4 : 5 }}" class="px-3 py-6 text-center text-sm text-gray-400 dark:text-gray-500">Tidak ada data consumable</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
