<?php

use Illuminate\Support\Facades\Route;
use VanguardLTE\Http\Controllers\Api\B2B\B2BLauncherController;

Route::get('b2b/launcher/{game}/{token}', [B2BLauncherController::class, 'launch'])
    ->name('b2b.launcher');
