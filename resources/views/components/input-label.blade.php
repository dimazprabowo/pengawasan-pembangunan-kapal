@props(['value', 'required' => false])

<label {{ $attributes->merge(['class' => 'form-input-label block font-medium text-sm text-gray-700 dark:text-gray-300']) }}>
    {{ $value ?? $slot }}
    @if($required)
        <span class="text-red-500 ml-0.5">*</span>
    @endif
</label>
