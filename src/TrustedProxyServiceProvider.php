<?php

namespace Monicahq\Cloudflare;

use Illuminate\Support\ServiceProvider;

class TrustedProxyServiceProvider extends ServiceProvider
{
    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        /** @var \Illuminate\Contracts\Foundation\Application */
        $app = $this->app;
        if ($app->runningInConsole()) {
            $this->commands([
                Commands\Reload::class,
                Commands\View::class,
            ]);
        }
    }
}
