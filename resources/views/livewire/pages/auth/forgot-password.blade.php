<div class="min-h-screen flex flex-col lg:flex-row relative">
    <!-- Dark Mode Toggle - Fixed Position -->
    <div class="fixed top-4 right-4 z-50">
        <button @click="darkMode = !darkMode" 
                class="p-3 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-200 border border-gray-200 dark:border-gray-700">
            <svg x-show="!darkMode" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
            </svg>
            <svg x-show="darkMode" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
        </button>
    </div>

    <!-- Left Side - Branding (sama seperti login) -->
    <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-blue-600 via-blue-700 to-blue-800 dark:from-blue-800 dark:via-blue-900 dark:to-gray-900 p-12 flex-col justify-between relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-0 left-0 w-96 h-96 bg-white rounded-full -translate-x-1/2 -translate-y-1/2"></div>
            <div class="absolute bottom-0 right-0 w-96 h-96 bg-white rounded-full translate-x-1/2 translate-y-1/2"></div>
        </div>

        <!-- Logo & Title -->
        <div class="relative z-10">
            <div class="flex items-center space-x-4 mb-8">
                <div class="w-16 h-16 bg-white rounded-xl flex items-center justify-center shadow-lg p-1.5 overflow-hidden">
                    <img src="{{ email_logo_url() }}" alt="BKI Logo" class="w-full h-full object-contain rounded-lg">
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-white">{{ config('app.name', 'Boilerplate') }}</h1>
                    <p class="text-blue-100 text-sm">PT. Biro Klasifikasi Indonesia</p>
                </div>
            </div>

            <h2 class="text-4xl font-bold text-white mb-4">{{ config('app.name', 'Boilerplate') }}</h2>
            <p class="text-blue-100 text-lg leading-relaxed">
                Laravel boilerplate application with authentication, role-based access control, and user management.
            </p>
        </div>

        <!-- Features -->
        <div class="relative z-10 space-y-6">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-white/10 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-white font-semibold mb-1">Authentication & Authorization</h3>
                    <p class="text-blue-100 text-sm">Login, register, email verification, password reset</p>
                </div>
            </div>

            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-white/10 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-white font-semibold mb-1">Role-Based Access Control</h3>
                    <p class="text-blue-100 text-sm">Flexible roles & permissions management</p>
                </div>
            </div>

            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-white/10 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-white font-semibold mb-1">User & System Management</h3>
                    <p class="text-blue-100 text-sm">Complete user CRUD and system configuration</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="relative z-10 text-blue-100 text-sm">
            © {{ date('Y') }} {{ config('app.name', 'Boilerplate') }}. All rights reserved.
        </div>
    </div>

    <!-- Right Side - Forgot Password Form -->
    <div class="flex-1 flex items-center justify-center p-6 sm:p-8 bg-gray-50 dark:bg-gray-900">
        <div class="w-full max-w-md">
            <!-- Mobile Logo -->
            <div class="lg:hidden flex items-center justify-center mb-8">
                <div class="w-16 h-16 bg-white rounded-xl flex items-center justify-center shadow-lg p-1.5 overflow-hidden">
                    <img src="{{ email_logo_url() }}" alt="BKI Logo" class="w-full h-full object-contain rounded-lg">
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 sm:p-8 border border-gray-200 dark:border-gray-700">
                <div class="mb-8">
                    <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Lupa Password?</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2 text-sm sm:text-base">
                        @if($emailSent)
                            Link reset password telah dikirim ke email Anda. Silakan cek inbox atau folder spam.
                        @else
                            Masukkan email Anda dan kami akan mengirimkan link untuk reset password.
                        @endif
                    </p>
                </div>

                <!-- Session Status -->
                <x-auth-session-status class="mb-4" :status="session('status')" />

                @if(!$emailSent)
                    <form wire:submit="sendResetLink" class="space-y-6">
                        <!-- Email Address -->
                        <div>
                            <x-input-label for="email" value="Email" :required="true" />
                            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus autocomplete="email" placeholder="nama@email.com" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <button 
                            type="submit" 
                            class="w-full inline-flex items-center justify-center px-4 py-3 bg-blue-600 border border-transparent rounded-lg font-semibold text-base text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed" 
                            wire:loading.attr="disabled"
                            wire:target="sendResetLink">

                            <span class="inline-flex items-center justify-center gap-2">

                                <!-- ICON LOADING -->
                                <svg 
                                    wire:loading 
                                    wire:target="sendResetLink"
                                    class="animate-spin h-5 w-5 text-white"
                                    xmlns="http://www.w3.org/2000/svg" 
                                    fill="none" 
                                    viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>

                                <!-- TEXT NORMAL -->
                                <span wire:loading.class="hidden" wire:target="sendResetLink">
                                    Kirim Link Reset Password
                                </span>

                                <!-- TEXT LOADING -->
                                <span wire:loading wire:target="sendResetLink">
                                    Mengirim...
                                </span>

                            </span>
                        </button>

                    </form>
                @else
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6">
                        <div class="flex items-start">
                            <svg class="w-6 h-6 text-green-600 dark:text-green-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <h3 class="text-sm font-medium text-green-800 dark:text-green-200">Email Terkirim!</h3>
                                <p class="text-sm text-green-700 dark:text-green-300 mt-1">
                                    Silakan cek email Anda untuk link reset password. Link akan kadaluarsa dalam 60 menit.
                                </p>
                            </div>
                        </div>
                    </div>

                    <button type="button" wire:click="$set('emailSent', false)" class="w-full inline-flex items-center justify-center px-4 py-3 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-lg font-semibold text-base text-gray-700 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        Kirim Ulang
                    </button>
                @endif

                <div class="mt-6 text-center">
                    <a href="{{ route('login') }}" wire:navigate class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium transition-colors">
                        ← Kembali ke Login
                    </a>
                </div>
            </div>

            <p class="text-center text-sm text-gray-600 dark:text-gray-400 mt-6">
                Butuh bantuan? <a href="#" class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-medium transition-colors">Hubungi Support</a>
            </p>
        </div>
    </div>
</div>
