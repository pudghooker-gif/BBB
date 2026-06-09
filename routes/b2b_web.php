<?php

use Illuminate\Support\Facades\Route;

Route::get('b2b/launcher/{game}/{token}', 'Api\\B2B\\B2BLauncherController@launch')
    ->name('b2b.launcher');
