@props([
    'wireModel' => 'jenis_kapal_id',
    'jenisKapalList' => null,
    'variant' => 'form', // 'form' (white bg) or 'filter' (blue bg)
    'placeholder' => 'Pilih jenis kapal',
    'searchPlaceholder' => 'Cari jenis kapal...',
    'label' => 'Jenis Kapal',
    'required' => true,
    'showEmptyWarning' => false,
    'error' => null,
    'selectedValue' => null,
])

@php
    $variantClasses = $variant === 'filter' 
        ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800' 
        : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700';
    
    $labelIcon = $variant === 'filter';
    $labelText = $variant === 'filter' ? 'Pilih Jenis Kapal:' : $label;
    
    // Get the selected value from the parent component
    $currentValue = $selectedValue ?? $attributes->get($wireModel);
@endphp

@if($jenisKapalList?->isNotEmpty())
    <div class="{{ $variantClasses }} rounded-lg border px-4 pt-4 pb-4 min-h-[145px] ">
        <div class="flex flex-col gap-3">
            @if($labelIcon)
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    <label class="text-sm font-medium text-blue-900 dark:text-blue-200">{{ $labelText }}</label>
                </div>
            @else
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {{ $labelText }} @if($required) <span class="text-red-500">*</span> @endif
                </label>
            @endif
            
            <div class="w-full">
                <x-searchable-select
                    wire:model.live="{{ $wireModel }}"
                    :options="$jenisKapalList->map(fn($jk) => [
                        'value' => $jk->id,
                        'label' => $jk->nama . ($jk->company ? ' (' . $jk->company->name . ')' : '') . ($jk->galangan ? ' - (' . $jk->galangan->nama . ')' : '')
                    ])->toArray()"
                    :placeholder="$placeholder"
                    :searchPlaceholder="$searchPlaceholder"
                    :error="$error"
                />
                @if($error && !$labelIcon)
                    <span class="text-red-500 text-xs mt-1">{{ $error }}</span>
                @endif
            </div>
            
            @if($currentValue)
                @php
                    $selectedJenisKapal = $jenisKapalList->firstWhere('id', $currentValue);
                @endphp
                @if($selectedJenisKapal)
                    <div class="flex flex-wrap items-center gap-2 text-sm text-blue-700 dark:text-blue-300">
                        <div class="flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="font-medium">{{ $selectedJenisKapal->nama }}</span>
                        </div>
                        @if($selectedJenisKapal->company)
                            <span class="text-blue-600 dark:text-blue-400">•</span>
                            <span>{{ $selectedJenisKapal->company->name }}</span>
                        @endif
                        @if($selectedJenisKapal->galangan)
                            <span class="text-blue-600 dark:text-blue-400">•</span>
                            <span>{{ $selectedJenisKapal->galangan->nama }}</span>
                        @endif
                    </div>
                @endif
            @endif
        </div>
    </div>
@elseif($showEmptyWarning)
    <div class="mb-6 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div>
                <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Belum Ada Jenis Kapal</h3>
                <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">Silakan tambahkan jenis kapal terlebih dahulu di menu Master Data untuk dapat mengelola laporan.</p>
                @can('jenis_kapal_create')
                    <a href="{{ route('master-data.jenis-kapal') }}" wire:navigate class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-yellow-800 dark:text-yellow-200 hover:text-yellow-900 dark:hover:text-yellow-100">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Tambah Jenis Kapal
                    </a>
                @endcan
            </div>
        </div>
    </div>
@endif
