<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// Public Routes
Route::redirect('/', '/login');

// Logout Route (must be authenticated)
Route::post('/logout', function (Request $request, Logout $logout) {
    $logout();
    return redirect('/');
})->middleware('auth')->name('logout');

// Authenticated Routes
Route::middleware(['auth', 'verified', 'active'])->group(function () {
    
    // Dashboard
    Route::get('/dashboard', App\Livewire\Pages\Dashboard::class)->name('dashboard');

    // Profile
    Route::view('profile', 'profile')->name('profile');

    // Master Data Routes
    Route::prefix('master-data')->name('master-data.')->group(function () {
        Route::get('/perusahaan', function () {
            return view('master-data.perusahaan');
        })->middleware('can:companies_view')->name('perusahaan');

        Route::get('/jenis-kapal', function () {
            return view('master-data.jenis-kapal');
        })->middleware('can:jenis_kapal_view')->name('jenis-kapal');

        Route::get('/galangan', function () {
            return view('master-data.galangan');
        })->middleware('can:galangan_view')->name('galangan');

        Route::get('/kelembaban', function () {
            return view('master-data.kelembaban');
        })->middleware('can:kelembaban_view')->name('kelembaban');

        Route::get('/cuaca', function () {
            return view('master-data.cuaca');
        })->middleware('can:cuaca_view')->name('cuaca');
    });

    // Notifications
    Route::get('/notifications', function () {
        return view('notifications.index');
    })->middleware('can:notifications_view')->name('notifications.index');

    Route::get('/notifications/send', function () {
        return view('notifications.send');
    })->middleware('can:notifications_send')->name('notifications.send');

    // Chat
    Route::get('/chat', function () {
        return view('chat.index');
    })->middleware('can:chat_view')->name('chat.index');

    // Manajemen Laporan
    Route::prefix('laporan')->name('laporan.')->middleware('can:laporan_view')->group(function () {
        Route::get('/', App\Livewire\Laporan\LaporanIndex::class)->name('index');
        Route::get('/create/{tipe}', App\Livewire\Laporan\LaporanCreate::class)
            ->middleware('can:laporan_create')
            ->name('create');
        Route::get('/{laporan}', App\Livewire\Laporan\LaporanShow::class)
            ->middleware('can:laporan_show')
            ->name('show');
        Route::get('/{laporan}/edit', App\Livewire\Laporan\LaporanEdit::class)
            ->middleware('can:laporan_update')
            ->name('edit');
        Route::get('/{laporan}/download', [App\Http\Controllers\LaporanFileController::class, 'download'])
            ->middleware('can:laporan_download')
            ->name('download');
        Route::get('/{laporan}/preview', [App\Http\Controllers\LaporanFileController::class, 'preview'])
            ->middleware('can:laporan_show')
            ->name('preview');
        Route::get('/{laporan}/lampiran/{lampiran}/download', [App\Http\Controllers\LaporanFileController::class, 'downloadLampiran'])
            ->middleware('can:laporan_lampiran_download')
            ->name('lampiran.download');
        Route::get('/{laporan}/lampiran/{lampiran}/preview', [App\Http\Controllers\LaporanFileController::class, 'previewLampiran'])
            ->middleware('can:laporan_lampiran_preview')
            ->name('lampiran.preview');
    });

    // Settings Routes - each route checks its own permission
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/system', function () {
            return view('settings.system');
        })->middleware('can:configuration_view')->name('system');
        
        Route::get('/users', function () {
            return view('settings.users');
        })->middleware('can:users_view')->name('users');
        
        Route::get('/roles', function () {
            return view('settings.roles');
        })->middleware('can:roles_view')->name('roles');
    });
});

require __DIR__.'/auth.php';
