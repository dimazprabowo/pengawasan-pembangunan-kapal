@props([
    'label',
    'isEmpty' => false,
    'count' => null,
    'countLabel' => 'data',
])

<div class="border-t border-gray-200 dark:border-gray-700 pt-5">
    <x-laporan.section-header 
        :label="$label" 
        :isEmpty="$isEmpty" 
        :count="$count" 
        :countLabel="$countLabel" 
    />
    
    {{ $slot }}
</div>
