<div>
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
