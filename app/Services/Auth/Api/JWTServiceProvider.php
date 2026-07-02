<?php

namespace VanguardLTE\Services\Auth\Api;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class JWTServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('tymon.jwt', function ($app) {
            return $app['tymon.jwt.auth'];
        });

        $this->app->singleton('tymon.jwt.auth', function ($app) {
            return new JWTAuth(
                $app['request'],
                $app['config']
            );
        });

        $this->app->alias('tymon.jwt.auth', JWTAuth::class);

        $this->app->rebinding('request', function ($app, $request) {
            $app['tymon.jwt.auth']->setRequest($request);
        });
    }

    public function boot()
    {
        Auth::extend('jwt', function ($app, $name, array $config) {
            $guard = new JwtGuard(
                $app['tymon.jwt.auth'],
                $app['auth']->createUserProvider($config['provider'] ?? null),
                $app['request']
            );

            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });
    }
}
