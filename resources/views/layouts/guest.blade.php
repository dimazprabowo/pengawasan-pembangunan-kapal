@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data :class="{ 'dark': $store.darkMode.dark }">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? $title . ' - ' . config('app.name', 'Laravel') : config('app.name', 'Laravel') }}</title>

        <!-- Favicon -->
        <link rel="icon" type="image/webp" href="{{ asset('images/bki-main.webp') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/bki-main.webp') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- Alpine stores, dark mode sync, teleport cleanup loaded via Vite (alpine-stores.js) --}}

        @if(config('services.recaptcha.enabled'))
        <!-- Google reCAPTCHA v2 -->
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
        @endif
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col lg:flex-row relative">
            <!-- Dark Mode Toggle - Fixed Position -->
            <div class="fixed top-4 right-4 z-50">
                <button @click="$store.darkMode.toggle()"
                        class="p-3 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-200 border border-gray-200 dark:border-gray-700">
                    <svg x-show="!$store.darkMode.dark" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                    <svg x-show="$store.darkMode.dark" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </button>
            </div>

            <!-- Left Side - Branding -->
            <x-auth-brand-panel />

            <!-- Right Side - Content -->
            <div class="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8 bg-gray-50 dark:bg-gray-900 min-h-screen lg:min-h-0">
                <div class="w-full max-w-md">
                    <!-- Mobile Logo -->
                    <div class="lg:hidden flex flex-col items-center mb-8">
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="w-14 h-14 bg-white rounded-xl flex items-center justify-center shadow-lg p-1.5 overflow-hidden">
                                <img src="{{ email_logo_url() }}" alt="BKI Logo" class="w-full h-full object-contain rounded-lg">
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ config('app.name', 'SIMPRO') }}</h1>
                                <p class="text-sm text-gray-600 dark:text-gray-400">PT. Biro Klasifikasi Indonesia</p>
                            </div>
                        </div>
                    </div>

                    {{ $slot }}
                </div>
            </div>
        </div>

        <x-toast />
    </body>
</html>
