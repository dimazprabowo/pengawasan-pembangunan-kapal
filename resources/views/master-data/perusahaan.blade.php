<x-app-layout title="Master Data - Perusahaan">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Master Data - Perusahaan') }}
        </h2>
    </x-slot>

    <livewire:master-data.company-management />
</x-app-layout>
