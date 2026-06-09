<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->describe('Display an inspiring quote');

// B2B operator toolkit console commands — v5.2 hotfix
if (file_exists(base_path('routes/b2b_console.php'))) {
    require base_path('routes/b2b_console.php');
}
