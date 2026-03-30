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

    <!-- Left Side - Branding -->
    <div class="hidden lg:flex lg:w-1/2 xl:w-2/5 bg-gradient-to-br from-blue-600 via-blue-700 to-blue-900 dark:from-blue-800 dark:via-blue-900 dark:to-gray-900 p-8 lg:p-12 flex-col justify-between relative overflow-hidden">
        <!-- Decorative Background Elements -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-0 right-0 w-96 h-96 bg-white rounded-full -translate-y-1/2 translate-x-1/2"></div>
            <div class="absolute bottom-0 left-0 w-96 h-96 bg-white rounded-full translate-y-1/2 -translate-x-1/2"></div>
        </div>
        
        <div class="relative z-10">
            <div class="flex items-center space-x-3 mb-12">
                <div class="w-14 h-14 bg-white rounded-xl flex items-center justify-center shadow-lg p-1.5 overflow-hidden">
                    <img src="{{ email_logo_url() }}" alt="BKI Logo" class="w-full h-full object-contain rounded-lg">
                </div>
                <div class="text-white">
                    <h1 class="text-2xl lg:text-3xl font-bold">{{ config('app.name', 'Boilerplate') }}</h1>
                    <p class="text-sm text-blue-100">PT. Biro Klasifikasi Indonesia</p>
                </div>
            </div>
            <div class="space-y-6 max-w-lg">
                <h2 class="text-3xl lg:text-4xl xl:text-5xl font-bold text-white leading-tight">
                    {{ config('app.name', 'Boilerplate') }}
                </h2>
                <p class="text-lg lg:text-xl text-blue-100 leading-relaxed">
                    Laravel boilerplate application with authentication, role-based access control, and user management.
                </p>
                <div class="space-y-4 pt-8">
                    <div class="flex items-start space-x-4 text-blue-50">
                        <div class="flex-shrink-0 w-8 h-8 bg-blue-500/30 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-white mb-1">Authentication & Authorization</h3>
                            <p class="text-sm text-blue-200">Login, register, email verification, password reset</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-4 text-blue-50">
                        <div class="flex-shrink-0 w-8 h-8 bg-blue-500/30 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-white mb-1">Role-Based Access Control</h3>
                            <p class="text-sm text-blue-200">Flexible roles & permissions management</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-4 text-blue-50">
                        <div class="flex-shrink-0 w-8 h-8 bg-blue-500/30 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-white mb-1">User & System Management</h3>
                            <p class="text-sm text-blue-200">Complete user CRUD and system configuration</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="relative z-10 text-blue-100 text-sm">
            <p>&copy; {{ date('Y') }} {{ config('app.name', 'Boilerplate') }}. All rights reserved.</p>
        </div>
    </div>

    <!-- Right Side - Login Form -->
    <div class="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8 bg-gray-50 dark:bg-gray-900 min-h-screen lg:min-h-0">
        <div class="w-full max-w-md">
            <!-- Mobile Logo -->
            <div class="lg:hidden flex flex-col items-center mb-8">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-14 h-14 bg-white rounded-xl flex items-center justify-center shadow-lg p-1.5 overflow-hidden">
                        <img src="{{ email_logo_url() }}" alt="BKI Logo" class="w-full h-full object-contain rounded-lg">
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ config('app.name', 'Boilerplate') }}</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">PT. Biro Klasifikasi Indonesia</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 sm:p-8 border border-gray-200 dark:border-gray-700">
                <div class="mb-8">
                    <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Selamat Datang</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2 text-sm sm:text-base">Silakan login untuk melanjutkan</p>
                </div>

                <!-- Session Status -->
                <x-auth-session-status class="mb-4" :status="session('status')" />

                <form wire:submit="login" class="space-y-6">
                    <!-- Email Address -->
                    <div>
                        <x-input-label for="email" value="Email" :required="true" />
                        <x-text-input wire:model="form.email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus autocomplete="username" placeholder="nama@email.com" />
                        <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
                    </div>

                    <!-- Password -->
                    <div>
                        <x-input-label for="password" value="Password" :required="true" />
                        <div class="relative">
                            <x-text-input 
                                wire:model="form.password" 
                                id="password" 
                                class="block mt-1 w-full pr-10"
                                type="{{ $showPassword ? 'text' : 'password' }}"
                                name="password"
                                required 
                                autocomplete="current-password" 
                                placeholder="••••••••" 
                            />
                            <button 
                                type="button"
                                wire:click="togglePasswordVisibility"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                            >
                                @if($showPassword)
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                    </svg>
                                @else
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                @endif
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center justify-between">
                        <label for="remember" class="inline-flex items-center">
                            <input wire:model="form.remember" id="remember" type="checkbox" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-blue-600 shadow-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:focus:ring-offset-gray-800" name="remember">
                            <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Ingat saya</span>
                        </label>

                        @if (Route::has('password.request'))
                            <a class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300" href="{{ route('password.request') }}" wire:navigate>
                                Lupa password?
                            </a>
                        @endif
                    </div>

                    <!-- reCAPTCHA v2 Checkbox -->
                    <div class="space-y-3">
                        <!-- Hidden input to store token -->
                        <input type="hidden" id="recaptcha-token-input" wire:model="form.recaptcha_token">
                        
                        <div id="recaptcha-wrapper" class="flex justify-center">
                            <div id="recaptcha-container" wire:ignore></div>
                        </div>
                        
                        <!-- Reload Button -->
                        <div class="flex items-center justify-center gap-4 mt-3">
                            <button type="button" onclick="window.reloadRecaptcha()" class="inline-flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                <span>Reload reCAPTCHA</span>
                            </button>
                        </div>
                        
                        @error('form.recaptcha_token')
                            <p class="text-sm text-red-600 dark:text-red-400 text-center">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" 
                        class="w-full inline-flex items-center justify-center px-4 py-3 bg-blue-600 border border-transparent rounded-lg font-semibold text-base text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
                        wire:loading.attr="disabled"
                        wire:target="login">

                        <span class="inline-flex items-center justify-center gap-2">

                            <!-- ICON LOADING -->
                            <svg wire:loading wire:target="login"
                                class="animate-spin h-5 w-5 text-white"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>

                            <!-- TEKS NORMAL -->
                            <span wire:loading.class="hidden" wire:target="login">
                                Masuk
                            </span>

                            <!-- TEKS LOADING -->
                            <span wire:loading wire:target="login">
                                Memproses...
                            </span>

                        </span>
                    </button>


                    <!-- reCAPTCHA Badge Info -->
                    <div class="text-xs text-gray-500 dark:text-gray-400 text-center mt-4">
                        This site is protected by reCAPTCHA and the Google
                        <a href="https://policies.google.com/privacy" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">Privacy Policy</a> and
                        <a href="https://policies.google.com/terms" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">Terms of Service</a> apply.
                    </div>
                </form>

                @if(config('services.sso.enabled'))
                    <!-- SSO Divider -->
                    <div class="relative my-6">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300 dark:border-gray-600"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-3 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400">atau</span>
                        </div>
                    </div>

                    <!-- SSO Login Button -->
                    <a href="{{ route('sso.redirect') }}"
                       x-data="{ loading: false }"
                       @click="loading = true"
                       :class="loading ? 'opacity-70 pointer-events-none' : ''"
                       class="w-full inline-flex items-center justify-center gap-3 px-4 py-3 bg-white dark:bg-gray-700 border-2 border-blue-200 dark:border-blue-700 rounded-lg font-semibold text-sm text-blue-700 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:border-blue-400 dark:hover:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        <svg x-show="!loading" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                        </svg>
                        <svg x-show="loading" x-cloak class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span x-text="loading ? 'Menghubungkan ke SSO...' : 'Login dengan SSO Server'"></span>
                    </a>

                    @if($errors->has('sso'))
                        <div class="mt-3 p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $errors->first('sso') }}</p>
                        </div>
                    @endif
                @endif
            </div>

            <p class="text-center text-sm text-gray-600 dark:text-gray-400 mt-6">
                Butuh bantuan? <a href="#" class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-medium transition-colors">Hubungi Support</a>
            </p>
        </div>
    </div>
</div>

@script
<script>
// Use window object to avoid redeclaration
window.recaptchaWidgetId = window.recaptchaWidgetId || null;
window.recaptchaRendered = window.recaptchaRendered || false;

// Render reCAPTCHA v2
window.renderRecaptcha = function() {
    if (typeof grecaptcha === 'undefined') {
        setTimeout(window.renderRecaptcha, 100);
        return;
    }
    
    const container = document.getElementById('recaptcha-container');
    if (!container) return;
    
    // Check if already rendered and valid
    if (window.recaptchaWidgetId !== null && container.children.length > 0) {
        return;
    }
    
    // Clear container completely
    container.innerHTML = '';
    window.recaptchaRendered = false;
    window.recaptchaWidgetId = null;
    
    try {
        grecaptcha.ready(function() {
            try {
                window.recaptchaWidgetId = grecaptcha.render('recaptcha-container', {
                    'sitekey': '{{ config('services.recaptcha.site_key') }}',
                    'theme': document.documentElement.classList.contains('dark') ? 'dark' : 'light',
                    'callback': function(token) {
                        // Set token to hidden input (primary method)
                        const hiddenInput = document.getElementById('recaptcha-token-input');
                        if (hiddenInput) {
                            hiddenInput.value = token;
                            hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                        // Also set via $wire (backup method)
                        if (typeof $wire !== 'undefined') {
                            $wire.set('form.recaptcha_token', token);
                        }
                    },
                    'expired-callback': function() {
                        if (typeof $wire !== 'undefined') {
                            $wire.set('form.recaptcha_token', '');
                        }
                    },
                    'error-callback': function() {
                        if (typeof $wire !== 'undefined') {
                            $wire.set('form.recaptcha_token', '');
                        }
                    }
                });
                window.recaptchaRendered = true;
            } catch (error) {
                // If already rendered error, try to get existing widget
                if (error.message && error.message.includes('already been rendered')) {
                    const widgets = container.querySelectorAll('[id^="rc-"]');
                    if (widgets.length > 0) {
                        window.recaptchaRendered = true;
                    }
                }
            }
        });
    } catch (error) {
        window.recaptchaRendered = false;
        window.recaptchaWidgetId = null;
    }
}

// Reload reCAPTCHA - completely destroy and recreate
window.reloadRecaptcha = function() {
    // Clear token first
    if (typeof $wire !== 'undefined') {
        $wire.set('form.recaptcha_token', '');
    }
    
    const wrapper = document.getElementById('recaptcha-wrapper');
    if (!wrapper) return;
    
    // Completely destroy old container and create new one
    wrapper.innerHTML = '<div id="recaptcha-container" wire:ignore></div>';
    
    // Reset state
    window.recaptchaRendered = false;
    window.recaptchaWidgetId = null;
    
    // Render fresh with small delay to ensure DOM is ready
    setTimeout(function() {
        window.renderRecaptcha();
    }, 100);
}

// Initialize on page load
window.renderRecaptcha();
</script>
@endscript
