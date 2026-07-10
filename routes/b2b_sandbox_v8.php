<?php

use Illuminate\Support\Facades\Route;
use VanguardLTE\Http\Controllers\Api\B2B\SandboxController;
use VanguardLTE\Http\Controllers\Api\B2B\SandboxWalletController;

// Effective URLs are /api/b2b/sandbox/... because this file is loaded from routes/api.php.
Route::prefix('b2b/sandbox')->group(function () {
    Route::get('health', [SandboxWalletController::class, 'health']);
    Route::post('wallet', [SandboxWalletController::class, 'handle']);
    Route::post('wallet/{action}', [SandboxWalletController::class, 'action'])
        ->where('action', 'balance|bet|win|refund|rollback|credit|debit');
});

// Protected sandbox operator tools. These use the normal B2B HMAC middleware.
Route::prefix('b2b/v1')
    ->middleware(['b2b.signature'])
    ->group(function () {
        Route::middleware('b2b.scope:sandbox.wallet.read')->group(function () {
            Route::get('sandbox/wallet/{player_id}', [SandboxController::class, 'wallet']);
            Route::get('sandbox/wallet/{player_id}/entries', [SandboxController::class, 'entries']);
        });
        Route::middleware('b2b.scope:sandbox.wallet.mutate')->group(function () {
            Route::post('sandbox/wallet/{player_id}/credit', [SandboxController::class, 'credit']);
            Route::post('sandbox/wallet/{player_id}/debit', [SandboxController::class, 'debit']);
        });
    });
