<?php

namespace VanguardLTE\Providers;

use Illuminate\Support\ServiceProvider;
use VanguardLTE\Support\Html\FormBuilder;
use VanguardLTE\Support\Html\HtmlBuilder;

class HtmlServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('html', function ($app) {
            return new HtmlBuilder($app['url']);
        });

        $this->app->singleton('form', function ($app) {
            return new FormBuilder($app['html'], $app['url'], $app['session.store']);
        });
    }
}
