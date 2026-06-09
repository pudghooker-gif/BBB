<?php

use Illuminate\Support\Facades\Route;
use VanguardLTE\Http\Middleware\VerifyB2BSignature;
use VanguardLTE\Http\Controllers\Api\B2B\GameCatalogController;
use VanguardLTE\Http\Controllers\Api\B2B\GameLaunchController;
use VanguardLTE\Http\Controllers\Api\B2B\WalletController;
use VanguardLTE\Http\Controllers\Api\B2B\ReportsController;
use VanguardLTE\Http\Controllers\Api\B2B\SessionController;

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
    ->middleware([VerifyB2BSignature::class])
    ->group(function () {
        Route::get('games', [GameCatalogController::class, 'index']);
        Route::post('games/launch', [GameLaunchController::class, 'store']);
        Route::get('sessions/{session_uid}', [SessionController::class, 'show']);

        Route::post('wallet/balance', [WalletController::class, 'balance']);
        Route::post('wallet/bet', [WalletController::class, 'bet']);
        Route::post('wallet/win', [WalletController::class, 'win']);
        Route::post('wallet/refund', [WalletController::class, 'refund']);
        Route::post('wallet/rollback', [WalletController::class, 'rollback']);

        Route::get('reports/transactions', [ReportsController::class, 'transactions']);
        Route::get('reports/ggr', [ReportsController::class, 'ggr']);
    });
