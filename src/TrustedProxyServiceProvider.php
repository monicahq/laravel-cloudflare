<?php

namespace Monicahq\Cloudflare;

use Illuminate\Support\ServiceProvider;

class TrustedProxyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->registerPublishing();
    }

    /**
     * Register the package's publishable resources.
     */
    private function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laravelcloudflare.php' => config_path('laravelcloudflare.php'),
            ], 'laravelcloudflare-config');
        }
    }

    /**
     * Register any package services.
     */
    #[\Override]
    public function register(): void
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
