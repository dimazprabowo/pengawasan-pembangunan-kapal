<div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 sm:p-8 border border-gray-200 dark:border-gray-700">
        <div class="mb-6">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Konfirmasi Password</h2>
            <p class="text-gray-600 dark:text-gray-400 mt-2 text-sm sm:text-base">
                Ini adalah area aman aplikasi. Silakan konfirmasi password Anda sebelum melanjutkan.
            </p>
        </div>

        <form wire:submit="confirmPassword" class="space-y-6">
            <!-- Password -->
            <div>
                <x-input-label for="password" value="Password" :required="true" />
                <x-text-input wire:model="password"
                              id="password"
                              class="block mt-1 w-full"
                              type="password"
                              name="password"
                              required autocomplete="current-password"
                              placeholder="Password Anda saat ini" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <button type="submit"
                class="w-full inline-flex items-center justify-center px-4 py-3 bg-blue-600 border border-transparent rounded-lg font-semibold text-base text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
                wire:loading.attr="disabled"
                wire:target="confirmPassword">
                <span class="inline-flex items-center justify-center gap-2">
                    <svg wire:loading wire:target="confirmPassword"
                        class="animate-spin h-5 w-5 text-white"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading.class="hidden" wire:target="confirmPassword">Konfirmasi</span>
                    <span wire:loading wire:target="confirmPassword">Memproses...</span>
                </span>
            </button>
        </form>
    </div>
</div>
