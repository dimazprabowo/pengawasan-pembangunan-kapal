<div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 sm:p-8 border border-gray-200 dark:border-gray-700">
        <div class="mb-6">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Verifikasi Email</h2>
            <p class="text-gray-600 dark:text-gray-400 mt-2 text-sm sm:text-base">
                Terima kasih telah mendaftar! Silakan verifikasi alamat email Anda dengan mengklik link yang baru saja kami kirimkan. Tidak menerima email? Kami akan dengan senang hati mengirim ulang.
            </p>
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="mb-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                <p class="text-sm font-medium text-green-800 dark:text-green-200">
                    Link verifikasi baru telah dikirim ke alamat email yang Anda gunakan saat pendaftaran.
                </p>
            </div>
        @endif

        <div class="space-y-4">
            <button type="button"
                wire:click="sendVerification"
                wire:loading.attr="disabled"
                wire:target="sendVerification"
                class="w-full inline-flex items-center justify-center px-4 py-3 bg-blue-600 border border-transparent rounded-lg font-semibold text-base text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed">
                <span class="inline-flex items-center justify-center gap-2">
                    <svg wire:loading wire:target="sendVerification"
                        class="animate-spin h-5 w-5 text-white"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading.class="hidden" wire:target="sendVerification">Kirim Ulang Email Verifikasi</span>
                    <span wire:loading wire:target="sendVerification">Mengirim...</span>
                </span>
            </button>

            <div class="text-center">
                <button wire:click="logout" type="button" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium transition-colors">
                    Keluar
                </button>
            </div>
        </div>
    </div>
</div>
