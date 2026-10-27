<?php

namespace AmrSaleh\FasahPay;

use AmrSaleh\FasahPay\Console\SubscribeCallbacksCommand;
use AmrSaleh\FasahPay\Console\TestAuthCommand;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FasahPayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fasahpay.php', 'fasahpay');

        $this->app->singleton(FasahPayManager::class, fn ($app) => new FasahPayManager($app->make(Repository::class)));
        $this->app->alias(FasahPayManager::class, 'fasahpay');
        $this->app->bind(FasahPayClient::class, fn ($app) => $app->make(FasahPayManager::class)->connection());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/fasahpay.php' => config_path('fasahpay.php')], 'fasahpay-config');
            $this->commands([TestAuthCommand::class, SubscribeCallbacksCommand::class]);
        }

        if (config('fasahpay.callbacks.enabled')) {
            Route::group([
                'prefix' => config('fasahpay.callbacks.prefix', 'fasahpay'),
                'middleware' => config('fasahpay.callbacks.middleware', ['api']),
            ], fn () => $this->loadRoutesFrom(__DIR__.'/../routes/callbacks.php'));
        }
    }
}
