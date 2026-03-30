<?php

use App\Http\Controllers\Api\SsoSyncController;
use Illuminate\Support\Facades\Route;

// SSO Server sync endpoints — only register if SSO is enabled
// Protected by X-SSO-Secret header + rate limiting
if (config('services.sso.enabled')) {
    Route::middleware(['throttle:60,1', 'sso.secret'])->prefix('sso')->group(function () {
        Route::get('/ping', [SsoSyncController::class, 'ping']);

        // User sync
        Route::post('/users/sync', [SsoSyncController::class, 'syncUser']);
        Route::post('/users/remove', [SsoSyncController::class, 'removeUser']);
        Route::post('/users/sync-roles', [SsoSyncController::class, 'syncUserRoles']);
        Route::get('/users', [SsoSyncController::class, 'listUsers']);

        // Role & Permission sync
        Route::post('/roles/sync', [SsoSyncController::class, 'syncRole']);
        Route::post('/roles/delete', [SsoSyncController::class, 'deleteRole']);
        Route::get('/roles', [SsoSyncController::class, 'listRoles']);
        Route::get('/permissions', [SsoSyncController::class, 'listPermissions']);
    });
}
