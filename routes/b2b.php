<?php

use Illuminate\Support\Facades\Route;
use VanguardLTE\Http\Middleware\VerifyB2BSignature;
use VanguardLTE\Http\Controllers\Api\B2B\GameCatalogController;
use VanguardLTE\Http\Controllers\Api\B2B\GameLaunchController;
use VanguardLTE\Http\Controllers\Api\B2B\WalletController;
use VanguardLTE\Http\Controllers\Api\B2B\ReportsController;

/*
|--------------------------------------------------------------------------
| B2B Aggregator API
|--------------------------------------------------------------------------
|
| This file is included from routes/api.php by the installer.
| In a standard Laravel install the final URL prefix is /api/b2b/v1/...
|
*/

Route::prefix('b2b/v1')->group(function () {
    Route::get('health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'b2b-aggregator',
            'time' => now()->toIso8601String(),
        ]);
    });
});

Route::prefix('b2b/v1')
    ->middleware(['api', VerifyB2BSignature::class])
    ->group(function () {
        Route::get('games', [GameCatalogController::class, 'index']);
        Route::post('games/launch', [GameLaunchController::class, 'store']);

        Route::post('wallet/balance', [WalletController::class, 'balance']);
        Route::post('wallet/bet', [WalletController::class, 'bet']);
        Route::post('wallet/win', [WalletController::class, 'win']);
        Route::post('wallet/refund', [WalletController::class, 'refund']);
        Route::post('wallet/rollback', [WalletController::class, 'rollback']);

        Route::get('reports/transactions', [ReportsController::class, 'transactions']);
        Route::get('reports/ggr', [ReportsController::class, 'ggr']);
    });
