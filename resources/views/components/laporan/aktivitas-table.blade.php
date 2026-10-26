@props([
    'rows' => [],
    'rowsModel' => '',
    'addMethod' => '',
    'deletePrefix' => '',
    'wireKeyPrefix' => 'aktivitas',
    'readonly' => false,
    'title' => 'E. Uraian Aktivitas',
])

{{-- $rows boleh berisi index asli (non-sekuensial) — index dipakai sebagai path wire:model, nomor tampil pakai $loop --}}
<div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-between gap-2 mb-3">
        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2 min-w-0">
            <span class="truncate">{{ $title }}</span>
            @if(count($rows) > 0)
                <span class="min-w-[20px] px-1.5 rounded-full bg-blue-100 dark:bg-blue-900/40 text-[10px] leading-4 text-center text-blue-700 dark:text-blue-300">{{ count($rows) }}</span>
            @endif
        </h4>
        @if(!$readonly)
            <button type="button"
                wire:click="{{ $addMethod }}"
                wire:loading.attr="disabled"
                wire:target="{{ $addMethod }}"
                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/30 rounded-lg transition-colors disabled:opacity-50 flex-shrink-0">
                <svg wire:loading.class="hidden" wire:target="{{ $addMethod }}" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <svg wire:loading wire:target="{{ $addMethod }}" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Tambah Aktivitas</span>
            </button>
        @endif
    </div>

    <div class="overflow-x-auto custom-scrollbar rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="hidden md:table-header-group bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-10">No</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aktivitas</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-40">PIC</th>
                    @if(!$readonly)
                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">Aksi</th>
                    @endif
                </tr>
            </thead>
            {{-- Mobile: tiap baris jadi kartu vertikal (atas-bawah); md+ kembali jadi baris tabel --}}
            <tbody class="block md:table-row-group p-2 md:p-0 space-y-2 md:space-y-0 bg-gray-50 md:bg-white dark:bg-gray-900/50 md:dark:bg-gray-800 divide-y-0 md:divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($rows as $rIndex => $row)
                    <tr wire:key="{{ $wireKeyPrefix }}-{{ $row['row_key'] ?? $rIndex }}-{{ $rIndex }}"
                        class="flex flex-col md:table-row bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 md:border-0 rounded-lg md:rounded-none p-3 md:p-0">
                        <td class="block md:table-cell px-0 md:px-3 pb-1 md:py-2 text-xs md:text-sm font-semibold md:font-normal md:text-center text-gray-500 dark:text-gray-400">{{ $loop->iteration }}.</td>
                        @if($readonly)
                            <td class="block md:table-cell px-0 md:px-3 py-1 md:py-2 text-sm text-gray-900 dark:text-white">{{ $row['aktivitas'] ?? '-' }}</td>
                            <td class="block md:table-cell px-0 md:px-3 py-1 md:py-2 text-sm text-gray-500 dark:text-gray-400"><span class="md:hidden text-xs">PIC: </span>{{ $row['pic'] ?? '-' }}</td>
                        @else
                            <td class="block md:table-cell px-0 md:px-3 py-1 md:py-2">
                                {{-- .live.debounce: kartu di tab Lampiran digenerate server-side dari uraian ini --}}
                                <textarea wire:model.live.debounce.400ms="{{ $rowsModel }}.{{ $rIndex }}.aktivitas" rows="2"
                                    class="w-full px-2 py-1.5 text-sm border rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white {{ $errors->has($rowsModel.'.'.$rIndex.'.aktivitas') ? 'border-red-500 dark:border-red-400' : 'border-gray-300 dark:border-gray-600' }}"
                                    placeholder="Deskripsi aktivitas"></textarea>
                                @error($rowsModel.'.'.$rIndex.'.aktivitas') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </td>
                            <td class="block md:table-cell px-0 md:px-3 py-1 md:py-2 align-top">
                                <input type="text" wire:model.live.debounce.400ms="{{ $rowsModel }}.{{ $rIndex }}.pic"
                                    class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                    placeholder="PIC">
                            </td>
                            <td class="order-first md:order-none block md:table-cell px-0 md:px-3 pb-1 md:py-2 text-right md:text-center">
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
                    <tr class="block md:table-row">
                        <td colspan="{{ $readonly ? 3 : 4 }}" class="block md:table-cell px-3 py-6 text-center text-sm text-gray-400 dark:text-gray-500">Tidak ada data aktivitas</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
