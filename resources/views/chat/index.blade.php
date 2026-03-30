<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Chat
        </h2>
    </x-slot>

    <livewire:chat.chat-index :chat="request()->query('chat')" />
</x-app-layout>
