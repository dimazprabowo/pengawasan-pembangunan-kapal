<div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 sm:p-8 border border-gray-200 dark:border-gray-700">
                <div class="mb-8">
                    <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Reset Password</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2 text-sm sm:text-base">Buat password baru yang kuat dan aman</p>
                </div>

                <form wire:submit="resetPassword" class="space-y-6">
                    <!-- Email Address (Read-only) -->
                    <div>
                        <x-input-label for="email" value="Email" :required="true" />
                        <input 
                            wire:model="email" 
                            id="email" 
                            type="email" 
                            readonly
                            class="block mt-1 w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-500 dark:text-gray-400 text-sm cursor-not-allowed opacity-70 focus:outline-none"
                        />
                        @error('email')
                            <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div>
                        <x-input-label for="password" value="Password Baru" :required="true" />
                        <div class="relative mt-1">
                            <input 
                                wire:model="password" 
                                id="password" 
                                type="{{ $showPassword ? 'text' : 'password' }}" 
                                required
                                autocomplete="new-password"
                                class="block w-full px-4 py-2.5 pr-11 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-sm transition"
                                placeholder="Minimal 8 karakter"
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
                        <div class="mt-2 space-y-1">
                            <p class="text-xs text-gray-600 dark:text-gray-400">Password harus mengandung:</p>
                            <ul class="text-xs text-gray-500 dark:text-gray-400 space-y-0.5 ml-4">
                                <li class="flex items-center gap-1">
                                    <svg class="h-3 w-3 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    Minimal 8 karakter
                                </li>
                                <li class="flex items-center gap-1">
                                    <svg class="h-3 w-3 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    Huruf besar dan kecil
                                </li>
                                <li class="flex items-center gap-1">
                                    <svg class="h-3 w-3 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    Angka dan simbol (@$!%*#?&_-.)
                                </li>
                            </ul>
                        </div>
                        @error('password')
                            <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <x-input-label for="password_confirmation" value="Konfirmasi Password Baru" :required="true" />
                        <div class="relative mt-1">
                            <input 
                                wire:model="password_confirmation" 
                                id="password_confirmation" 
                                type="{{ $showPasswordConfirmation ? 'text' : 'password' }}" 
                                required
                                autocomplete="new-password"
                                class="block w-full px-4 py-2.5 pr-11 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-sm transition"
                                placeholder="Ulangi password baru"
                            />
                            <button 
                                type="button"
                                wire:click="togglePasswordConfirmationVisibility"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                            >
                                @if($showPasswordConfirmation)
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
                        @error('password_confirmation')
                            <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Submit Button -->
                    <button 
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="resetPassword"
                        class="w-full inline-flex items-center justify-center px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl shadow-sm hover:shadow-md transition-all duration-200 gap-2 text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg wire:loading wire:target="resetPassword" class="animate-spin h-5 w-5 text-white shrink-0" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span wire:loading.class="hidden" wire:target="resetPassword">Reset Password</span>
                        <span wire:loading wire:target="resetPassword">Memproses...</span>
                    </button>
                </form>

                <!-- Back to Login -->
                <div class="mt-6 text-center">
                    <a href="{{ route('login') }}" wire:navigate class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium transition-colors inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                        Kembali ke Login
                    </a>
                </div>
            </div>

            <!-- Mobile Footer -->
            <p class="lg:hidden text-center text-gray-400 dark:text-gray-500 text-xs mt-8">
                &copy; {{ date('Y') }} {{ config('app.name', 'SIMPRO') }}. All rights reserved.
            </p>
</div>
