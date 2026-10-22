@props([
    'disabledDates' => [],
    'excludeIndex' => null,
    'itemsKey' => 'items',
    'siblingKey' => 'tanggal_laporan',
    'placeholder' => 'Pilih tanggal',
    'required' => false,
    'maxDate' => null,
])

@php
    $tanggalModelPath = $attributes->wire('model')->value();
@endphp

<div class="relative" x-data
    x-init="
        (function () {
            var picker = flatpickr($refs.input, {
                dateFormat: 'Y-m-d',
                disableMobile: true,
                allowInput: false,
                locale: { firstDayOfWeek: 1 },
                defaultDate: $wire.get(@js($tanggalModelPath)) || null,
                @if($maxDate)
                maxDate: @js($maxDate),
                @endif
                disable: [
                    @js(collect($disabledDates)->values()->all()),
                    function (date) {
                        var items = $wire.get(@js($itemsKey)) || [];
                        var ymd = flatpickr.formatDate(date, 'Y-m-d');
                        return items.some(function (it, i) {
                            return i !== {{ $excludeIndex ?? -1 }} && it && it.{{ $siblingKey }} === ymd;
                        });
                    }
                ],
                onChange: function (selectedDates, dateStr) {
                    $wire.set(@js($tanggalModelPath), dateStr);
                }
            });
            $wire.$watch(@js($tanggalModelPath), function (v) {
                if (($refs.input.value || '') !== (v || '')) {
                    picker.setDate(v || null, false);
                }
            });
        })();
    ">
    <input x-ref="input" type="text" readonly
        placeholder="{{ $placeholder }}"
        {{ $attributes->except(['wire:model', 'wire:model.live', 'wire:model.blur', 'wire:model.defer', 'wire:model.change'])->merge([
            'class' => 'w-full px-3 py-2 pr-9 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white cursor-pointer',
        ]) }}>
    <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500"
        fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
    </svg>
</div>
