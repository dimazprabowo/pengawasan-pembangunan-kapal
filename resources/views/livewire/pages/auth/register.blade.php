<div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 sm:p-8 border border-gray-200 dark:border-gray-700">
        <div class="mb-8">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Buat Akun</h2>
            <p class="text-gray-600 dark:text-gray-400 mt-2 text-sm sm:text-base">Daftarkan akun baru untuk mengakses sistem</p>
        </div>

        <form wire:submit="register" class="space-y-5">
            <!-- Name -->
            <div>
                <x-input-label for="name" value="Nama Lengkap" :required="true" />
                <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" name="name" required autofocus autocomplete="name" placeholder="Nama lengkap Anda" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <!-- Email Address -->
            <div>
                <x-input-label for="email" value="Email" :required="true" />
                <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autocomplete="username" placeholder="nama@email.com" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <!-- Password -->
            <div>
                <x-input-label for="password" value="Password" :required="true" />
                <x-text-input wire:model="password" id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" placeholder="Minimal 8 karakter" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <!-- Confirm Password -->
            <div>
                <x-input-label for="password_confirmation" value="Konfirmasi Password" :required="true" />
                <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Ulangi password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <button type="submit"
                class="w-full inline-flex items-center justify-center px-4 py-3 bg-blue-600 border border-transparent rounded-lg font-semibold text-base text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
                wire:loading.attr="disabled"
                wire:target="register">
                <span class="inline-flex items-center justify-center gap-2">
                    <svg wire:loading wire:target="register"
                        class="animate-spin h-5 w-5 text-white"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading.class="hidden" wire:target="register">Daftar</span>
                    <span wire:loading wire:target="register">Memproses...</span>
                </span>
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="{{ route('login') }}" wire:navigate class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium transition-colors">
                Sudah punya akun? Login
            </a>
        </div>
    </div>
</div>
