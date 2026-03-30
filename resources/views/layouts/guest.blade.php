<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' || localStorage.getItem('darkMode') === null && window.matchMedia('(prefers-color-scheme: dark)').matches }" x-init="$watch('darkMode', val => localStorage.setItem('darkMode', val)); if (localStorage.getItem('darkMode') === null) { localStorage.setItem('darkMode', darkMode) }" :class="{ 'dark': darkMode }">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- Alpine stores, dark mode sync, teleport cleanup loaded via Vite (alpine-stores.js) --}}

        <!-- Google reCAPTCHA v2 -->
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    </head>
    <body class="font-sans text-gray-900 antialiased">
        {{ $slot }}
        
        <x-toast />
    </body>
</html>
