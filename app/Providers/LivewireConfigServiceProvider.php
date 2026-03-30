<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class LivewireConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Set Livewire temporary file upload rules dynamically from system configuration
        config([
            'livewire.temporary_file_upload.rules' => [
                'required',
                'file',
                'max:' . get_max_upload_size(),
                'mimes:' . get_allowed_mimes(),
            ],
        ]);
    }
}
