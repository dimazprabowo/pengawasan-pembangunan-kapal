<?php

use App\Livewire\Actions\Logout;
use App\Livewire\Laporan\LaporanIndex;
use App\Livewire\LaporanBulanan\LaporanBulananCreate;
use App\Livewire\LaporanBulanan\LaporanBulananEdit;
use App\Livewire\LaporanBulanan\LaporanBulananIndex;
use App\Livewire\LaporanBulanan\LaporanBulananShow;
use App\Livewire\LaporanHarian\LaporanHarianCreate;
use App\Livewire\LaporanHarian\LaporanHarianEdit;
use App\Livewire\LaporanHarian\LaporanHarianIndex;
use App\Livewire\LaporanHarian\LaporanHarianShow;
use App\Livewire\LaporanMingguan\LaporanMingguanCreate;
use App\Livewire\LaporanMingguan\LaporanMingguanEdit;
use App\Livewire\LaporanMingguan\LaporanMingguanIndex;
use App\Livewire\LaporanMingguan\LaporanMingguanShow;
use App\Livewire\MasterData\DataKapalForm;
use App\Livewire\Pages\Dashboard;
use App\Models\LaporanLampiran;
use App\Models\LaporanNcFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

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
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    // Profile
    Route::view('profile', 'profile')->name('profile');

    // Master Data Routes
    Route::prefix('master-data')->name('master-data.')->group(function () {
        Route::get('/perusahaan', function () {
            return view('master-data.perusahaan');
        })->middleware('can:companies_view')->name('perusahaan');

        Route::prefix('data-kapal')->group(function () {
            Route::get('/', function () {
                return view('master-data.data-kapal');
            })->middleware('can:data_kapal_view')->name('data-kapal');
            Route::get('/create', DataKapalForm::class)
                ->middleware('can:data_kapal_create')
                ->name('data-kapal.create');
            Route::get('/{dataKapal}/edit', DataKapalForm::class)
                ->middleware('can:data_kapal_update')
                ->name('data-kapal.edit');
        });

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

    // Manajemen Laporan Harian
    // Manajemen Laporan — pilih data kapal dulu, lalu laporan per kapal
    Route::get('/laporan', LaporanIndex::class)
        ->middleware('can:laporan_view')
        ->name('laporan.index');

    Route::prefix('laporan/{dataKapal}')->middleware('can:laporan_view')->group(function () {
        Route::prefix('harian')->name('laporan-harian.')->group(function () {
            Route::get('/', LaporanHarianIndex::class)->name('index');
            Route::get('/create', LaporanHarianCreate::class)
                ->middleware('can:laporan_create')
                ->name('create');
            Route::get('/{laporanHarian}', LaporanHarianShow::class)
                ->middleware('can:laporan_show')
                ->name('show');
            Route::get('/{laporanHarian}/edit', LaporanHarianEdit::class)
                ->middleware('can:laporan_update')
                ->name('edit');
        });

        Route::prefix('mingguan')->name('laporan-mingguan.')->group(function () {
            Route::get('/', LaporanMingguanIndex::class)->name('index');
            Route::get('/create', LaporanMingguanCreate::class)
                ->middleware('can:laporan_create')
                ->name('create');
            Route::get('/{laporanMingguan}', LaporanMingguanShow::class)
                ->middleware('can:laporan_show')
                ->name('show');
            Route::get('/{laporanMingguan}/edit', LaporanMingguanEdit::class)
                ->middleware('can:laporan_update')
                ->name('edit');
        });

        Route::prefix('bulanan')->name('laporan-bulanan.')->group(function () {
            Route::get('/', LaporanBulananIndex::class)->name('index');
            Route::get('/create', LaporanBulananCreate::class)
                ->middleware('can:laporan_create')
                ->name('create');
            Route::get('/{laporanBulanan}', LaporanBulananShow::class)
                ->middleware('can:laporan_show')
                ->name('show');
            Route::get('/{laporanBulanan}/edit', LaporanBulananEdit::class)
                ->middleware('can:laporan_update')
                ->name('edit');
        });
    });

    // File lampiran/NC di-serve via route (bukan base64 di payload Livewire):
    // request HTTP biasa + browser cache. Akses = izin lampiran_preview +
    // scope data kapal perusahaan (User::canAccessDataKapal). ID terenkripsi.
    Route::get('/laporan-lampiran/{lampiran}/file', function (LaporanLampiran $lampiran) {
        $lampiran->loadMissing('laporanHarian.dataKapal');
        $dataKapal = $lampiran->laporanHarian?->dataKapal;

        abort_unless($dataKapal && auth()->user()->canAccessDataKapal($dataKapal), 403);
        abort_unless(
            $lampiran->isFileCompleted() && $lampiran->file_path && Storage::disk('local')->exists($lampiran->file_path),
            404
        );

        return Storage::disk('local')->response($lampiran->file_path, $lampiran->file_name, [
            'Cache-Control' => 'private, max-age=86400, immutable',
        ]);
    })->middleware('can:laporan_lampiran_preview')->name('laporan-lampiran.file');

    Route::get('/laporan-nc-file/{file}/file', function (LaporanNcFile $file) {
        $file->loadMissing('dataKapal');

        abort_unless($file->dataKapal && auth()->user()->canAccessDataKapal($file->dataKapal), 403);
        abort_unless(
            $file->isFileCompleted() && $file->file_path && Storage::disk('local')->exists($file->file_path),
            404
        );

        return Storage::disk('local')->response($file->file_path, $file->file_name, [
            'Cache-Control' => 'private, max-age=86400, immutable',
        ]);
    })->middleware('can:laporan_lampiran_preview')->name('laporan-nc-file.file');

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
