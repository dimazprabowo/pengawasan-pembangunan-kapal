<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form wire:submit="updateProfileInformation" class="mt-6 space-y-6">
        <div>
            <x-input-label for="name" :value="__('Name')" :required="true" />
            <x-text-input wire:model="name" id="name" name="name" type="text" class="mt-1 block w-full" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" :required="true" />
            <x-text-input wire:model="email" id="email" name="email" type="email" class="mt-1 block w-full" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($authUser->pending_email)
                <div class="mt-3 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div class="flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-yellow-800 dark:text-yellow-300">
                                        Email Menunggu Verifikasi
                                    </p>
                                    <p class="text-sm text-yellow-700 dark:text-yellow-400 mt-1">
                                        Email Anda saat ini: <strong>{{ $authUser->email }}</strong><br>
                                        Email baru: <strong>{{ $authUser->pending_email }}</strong><br>
                                        Silakan cek inbox email baru untuk verifikasi. Email akan berubah setelah verifikasi berhasil.
                                    </p>
                                </div>
                                <button 
                                    wire:click.prevent="sendVerification" 
                                    wire:loading.attr="disabled"
                                    wire:target="sendVerification"
                                    class="px-3 py-1.5 text-xs font-medium text-yellow-800 dark:text-yellow-300 bg-yellow-100 dark:bg-yellow-800/30 hover:bg-yellow-200 dark:hover:bg-yellow-800/50 border border-yellow-300 dark:border-yellow-700 rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-1.5 whitespace-nowrap flex-shrink-0"
                                >
                                    <span class="inline-flex items-center gap-1.5">

                                        <!-- ICON LOADING -->
                                        <svg wire:loading wire:target="sendVerification"
                                            class="animate-spin h-3.5 w-3.5"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>

                                        <!-- ICON NORMAL -->
                                        <svg wire:loading.class="hidden" wire:target="sendVerification"
                                            class="w-3.5 h-3.5"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>

                                        <!-- TEKS NORMAL -->
                                        <span wire:loading.class="hidden" wire:target="sendVerification">
                                            Kirim Ulang
                                        </span>

                                        <!-- TEKS LOADING -->
                                        <span wire:loading wire:target="sendVerification">
                                            Mengirim...
                                        </span>

                                    </span>
                                </button>

                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if ($authUser instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $authUser->hasVerifiedEmail() && ! $authUser->pending_email)
                <div>
                    <p class="text-sm mt-2 text-gray-800 dark:text-gray-200">
                        {{ __('Your email address is unverified.') }}

                        <button 
                            wire:click.prevent="sendVerification" 
                            wire:loading.attr="disabled"
                            wire:target="sendVerification"
                            class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800 disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2"
                        >
                            <span wire:loading.remove wire:target="sendVerification">
                                {{ __('Click here to re-send the verification email.') }}
                            </span>
                            <span wire:loading wire:target="sendVerification" class="inline-flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Mengirim...
                            </span>
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600 dark:text-green-400">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button 
                wire:loading.attr="disabled" 
                wire:target="updateProfileInformation">

                <span class="inline-flex items-center justify-center gap-2">

                    <!-- ICON LOADING -->
                    <svg wire:loading wire:target="updateProfileInformation"
                        class="animate-spin h-4 w-4"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>

                    <!-- TEKS NORMAL -->
                    <span wire:loading.remove wire:target="updateProfileInformation">
                        {{ __('Save') }}
                    </span>

                    <!-- TEKS LOADING -->
                    <span wire:loading wire:target="updateProfileInformation">
                        Menyimpan...
                    </span>

                </span>
            </x-primary-button>


            <x-action-message class="me-3" on="profile-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
