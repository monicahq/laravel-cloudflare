<?php

namespace Monicahq\Cloudflare\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Monicahq\Cloudflare\CloudflareProxies;

class TrustProxies extends Middleware
{
    /**
     * Sets the trusted proxies on the request to the value of Cloudflare ips.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    protected function setTrustedProxyIpAddresses(Request $request)
    {
        $cachedProxies = Cache::get(Config::get('laravelcloudflare.cache'), function () {
            /** @var CloudflareProxies $cloudflareProxies */
            $cloudflareProxies = app(CloudflareProxies::class);

            return $cloudflareProxies->load();
        });

        if (is_array($cachedProxies) && count($cachedProxies) > 0) {
            $this->proxies = array_merge((array) $this->proxies, $cachedProxies);
        }

        parent::setTrustedProxyIpAddresses($request);
    }
}
