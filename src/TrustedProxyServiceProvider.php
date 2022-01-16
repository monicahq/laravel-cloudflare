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
        $this->app->singleton(\Monicahq\Cloudflare\Facades\CloudflareProxies::class, \Monicahq\Cloudflare\CloudflareProxies::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\Reload::class,
                Commands\View::class,
            ]);
        }
    }
}
