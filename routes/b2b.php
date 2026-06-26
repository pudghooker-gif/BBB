<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use VanguardLTE\B2B\Support\B2BApiResponse;
use VanguardLTE\Http\Controllers\Api\B2B\GameCatalogController;
use VanguardLTE\Http\Controllers\Api\B2B\GameLaunchController;
use VanguardLTE\Http\Controllers\Api\B2B\OperatorController;
use VanguardLTE\Http\Controllers\Api\B2B\ReportsController;
use VanguardLTE\Http\Controllers\Api\B2B\SessionController;
use VanguardLTE\Http\Controllers\Api\B2B\WalletController;

/*
|--------------------------------------------------------------------------
| B2B API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded from routes/api.php. The public health endpoint is
| intentionally unsigned. All operator endpoints must pass HMAC middleware.
|
*/

Route::prefix('b2b/v1')->group(function () {
    Route::get('health', function (Request $request) {
        return B2BApiResponse::success($request, [
            'service' => 'bbb-b2b',
            'version' => 'v6-reporting',
            'time' => now()->toIso8601String(),
        ]);
    });

    Route::middleware(['b2b.signature'])->group(function () {
        Route::get('operator/me', [OperatorController::class, 'me']);

        Route::get('games', [GameCatalogController::class, 'index']);
        Route::post('games/launch', [GameLaunchController::class, 'store']);

        Route::get('sessions', [SessionController::class, 'index']);
        Route::get('sessions/{session_uid}', [SessionController::class, 'show']);
        Route::post('sessions/{session_uid}/close', [SessionController::class, 'close']);

        Route::post('wallet/balance', [WalletController::class, 'balance']);
        Route::post('wallet/bet', [WalletController::class, 'bet']);
        Route::post('wallet/win', [WalletController::class, 'win']);
        Route::post('wallet/refund', [WalletController::class, 'refund']);
        Route::post('wallet/rollback', [WalletController::class, 'rollback']);

        Route::get('reports/summary', [ReportsController::class, 'summary']);
        Route::get('reports/transactions', [ReportsController::class, 'transactions']);
        Route::get('reports/ggr', [ReportsController::class, 'ggr']);
        Route::get('reports/settlements', [ReportsController::class, 'settlements']);
        Route::post('reports/settlements/export', [ReportsController::class, 'exportSettlement']);
        Route::get('reports/settlements/{settlement_uid}', [ReportsController::class, 'settlement']);
        Route::get('reports/reconciliation', [ReportsController::class, 'reconciliation']);
        Route::get('reports/transactions/{transaction_uid}', [ReportsController::class, 'transaction']);
    });
});
