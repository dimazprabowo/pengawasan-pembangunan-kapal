<x-app-layout title="Profile">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="">
        <div class="space-y-6">
            {{-- Company Information - Livewire Component --}}
            <livewire:profile.manage-company />

            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <livewire:profile.delete-user-form />
                </div>
            </div>

            @can('users_impersonate')
            <div id="impersonate" class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg scroll-mt-20">
                <livewire:profile.impersonate-user />
            </div>
            @endcan
        </div>
    </div>
</x-app-layout>
