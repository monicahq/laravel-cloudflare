<?php

namespace Monicahq\Cloudflare;

use Illuminate\Support\ServiceProvider;

class TrustedProxyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPublishing();
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    private function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laravelcloudflare.php' => config_path('laravelcloudflare.php'),
            ], 'laravelcloudflare-config');
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravelcloudflare.php', 'laravelcloudflare'
        );

        /** @var \Illuminate\Contracts\Foundation\Application */
        $app = $this->app;

        if ($app->runningInConsole()) {
            $app->singleton(CloudflareProxies::class, function ($app) {
                return new CloudflareProxies($app->make('config'));
            });

            $this->commands([
                Commands\Reload::class,
                Commands\View::class,
            ]);
        }
    }
}
