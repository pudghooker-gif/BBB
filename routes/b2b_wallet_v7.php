<?php

use Illuminate\Support\Facades\Route;
use VanguardLTE\Http\Middleware\VerifyB2BSignature;
use VanguardLTE\Http\Controllers\Api\B2B\WalletAttemptController;
use VanguardLTE\Http\Controllers\Api\B2B\WalletHealthController;
use VanguardLTE\Http\Controllers\Api\B2B\WalletTransactionStatusController;

Route::prefix('b2b/v1')
    ->middleware([VerifyB2BSignature::class])
    ->group(function () {
        Route::get('/wallet/health', [WalletHealthController::class, 'show']);
        Route::get('/wallet/transactions/{transaction_uid}/status', [WalletTransactionStatusController::class, 'show']);
        Route::get('/wallet/transactions/{transaction_uid}/attempts', [WalletAttemptController::class, 'index']);
    });
