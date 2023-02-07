<?php

namespace Monicahq\Cloudflare\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Monicahq\Cloudflare\LaravelCloudflare;

class TrustProxies extends Middleware
{
    /**
     * Sets the trusted proxies on the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function setTrustedProxyIpAddresses(Request $request)
    {
        if ((bool) Config::get('laravelcloudflare.enabled')) {
            $this->setTrustedProxyCloudflare($request);
        }

        parent::setTrustedProxyIpAddresses($request);
    }

    /**
     * Sets the trusted proxies on the request to the value of Cloudflare ips.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function setTrustedProxyCloudflare(Request $request): void
    {
        $cacheKey = Config::get('laravelcloudflare.cache');
        $cachedProxies = Cache::rememberForever($cacheKey, fn () => LaravelCloudflare::getProxies());

        if (count($cachedProxies) > 0) {
            $this->proxies = array_merge((array) $this->proxies, $cachedProxies);
        }
    }
}
