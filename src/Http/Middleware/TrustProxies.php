<?php

namespace Monicahq\Cloudflare\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class TrustProxies extends Middleware
{
    /**
     * Sets the trusted proxies on the request to the value of Cloudflare ips.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    protected function setTrustedProxyIpAddresses(Request $request)
    {
        $proxies = Cache::get(Config::get('laravelcloudflare.cache'), []);

        if (is_array($proxies) && count($proxies) > 0) {
            $this->proxies = array_merge((array) $this->proxies, $proxies);
        }

        parent::setTrustedProxyIpAddresses($request);
    }
}
