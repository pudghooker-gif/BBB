<?php

use Illuminate\Support\Facades\Route;
use VanguardLTE\Http\Controllers\Api\B2B\GameCatalogController;
use VanguardLTE\Http\Controllers\Api\B2B\GameLaunchController;
use VanguardLTE\Http\Controllers\Api\B2B\HealthController;
use VanguardLTE\Http\Controllers\Api\B2B\OperatorController;
use VanguardLTE\Http\Controllers\Api\B2B\PortalController;
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
    Route::get('health', [HealthController::class, 'health']);
    Route::get('readiness', [HealthController::class, 'readiness']);
    Route::get('metrics', [HealthController::class, 'metrics']);

    Route::middleware(['b2b.signature'])->group(function () {
        Route::get('operator/me', [OperatorController::class, 'me'])
            ->middleware('b2b.scope:operator.read');

        Route::middleware('b2b.scope:portal.read')->group(function () {
            Route::get('portal', [PortalController::class, 'page']);
            Route::get('portal/overview', [PortalController::class, 'overview']);
            foreach (['credentials', 'games', 'sessions', 'transactions', 'settlements', 'cases', 'callbacks', 'reports', 'support', 'docs'] as $portalSection) {
                Route::get('portal/' . $portalSection, [PortalController::class, 'section'])
                    ->defaults('section', $portalSection);
            }
            Route::get('portal/support/cases/{transaction_uid}', [PortalController::class, 'showCase']);
            Route::get('portal/support/cases/{transaction_uid}/thread', [PortalController::class, 'showCaseThread']);
            Route::get('portal/support/tickets/{ticket_uid}', [PortalController::class, 'showSupportTicket']);
            Route::get('portal/support/tickets/{ticket_uid}/thread', [PortalController::class, 'showSupportTicketThread']);
        });

        Route::middleware('b2b.scope:support.write')->group(function () {
            Route::post('portal/support/cases/{transaction_uid}/comments', [PortalController::class, 'commentCase']);
            Route::post('portal/support/tickets', [PortalController::class, 'createSupportTicket']);
            Route::post('portal/support/tickets/{ticket_uid}/comments', [PortalController::class, 'commentSupportTicket']);
            Route::post('portal/support/tickets/{ticket_uid}/close', [PortalController::class, 'closeSupportTicket']);
        });

        Route::middleware('b2b.scope:games.read')->group(function () {
            Route::get('games', [GameCatalogController::class, 'index']);
            Route::get('games/{game_uid}', [GameCatalogController::class, 'show']);
        });
        Route::post('games/launch', [GameLaunchController::class, 'store'])
            ->middleware('b2b.scope:games.launch');

        Route::middleware('b2b.scope:sessions.read')->group(function () {
            Route::get('sessions', [SessionController::class, 'index']);
            Route::get('sessions/{session_uid}', [SessionController::class, 'show']);
        });
        Route::post('sessions/{session_uid}/close', [SessionController::class, 'close'])
            ->middleware('b2b.scope:sessions.close');

        Route::post('wallet/balance', [WalletController::class, 'balance'])
            ->middleware('b2b.scope:wallet.balance');
        Route::middleware('b2b.scope:wallet.mutate')->group(function () {
            Route::post('wallet/bet', [WalletController::class, 'bet']);
            Route::post('wallet/win', [WalletController::class, 'win']);
            Route::post('wallet/refund', [WalletController::class, 'refund']);
            Route::post('wallet/rollback', [WalletController::class, 'rollback']);
        });

        Route::middleware('b2b.scope:reports.read')->group(function () {
            Route::get('reports/summary', [ReportsController::class, 'summary']);
            Route::get('reports/transactions', [ReportsController::class, 'transactions']);
            Route::get('reports/ggr', [ReportsController::class, 'ggr']);
            Route::get('reports/settlements', [ReportsController::class, 'settlements']);
            Route::post('reports/settlements/export', [ReportsController::class, 'exportSettlement'])
                ->middleware('b2b.scope:reports.export');
            Route::get('reports/settlements/{settlement_uid}', [ReportsController::class, 'settlement']);
            Route::get('reports/reconciliation', [ReportsController::class, 'reconciliation']);
            Route::get('reports/transactions/{transaction_uid}', [ReportsController::class, 'transaction']);
        });
    });
});
