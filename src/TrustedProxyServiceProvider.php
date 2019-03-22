<?php

namespace Monicahq\Cloudflare;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Support\DeferrableProvider;

class TrustedProxyServiceProvider extends ServiceProvider implements DeferrableProvider
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
        $app->singleton(CloudflareProxies::class, function ($app) {
            return new CloudflareProxies($app);
        });

        if ($app->runningInConsole()) {
            $this->commands([
                Commands\Reload::class,
                Commands\View::class,
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            CloudflareProxies::class
        ];
    }
}
