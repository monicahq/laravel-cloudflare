<?php

namespace Monicahq\Cloudflare;

use Closure;
use Monicahq\Cloudflare\Facades\CloudflareProxies;

final class LaravelCloudflare
{
    /**
     * The callback that should be used to get the proxies addresses.
     *
     * @var \Closure|null
     */
    protected static $getProxiesCallback;

    /**
     * Get the proxies addresses.
     */
    public static function getProxies(): array
    {
        if (self::$getProxiesCallback !== null) {
            return call_user_func(self::$getProxiesCallback);
        }

        return CloudflareProxies::load();
    }

    /**
     * Set a callback that should be used when getting the proxies addresses.
     */
    public static function getProxiesUsing(?Closure $callback): void
    {
        self::$getProxiesCallback = $callback;
    }
}
