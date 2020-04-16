<?php

namespace Monicahq\Cloudflare\Http\Middleware;

use Fideloper\Proxy\TrustProxies as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TrustProxies extends Middleware
{
    /**
     * Sets the trusted proxies on the request to the value of Cloudflare ips.
     *
     * @param \Illuminate\Http\Request $request
     */
    protected function setTrustedProxyIpAddresses(Request $request)
    {
        $proxies = Cache::get($this->config->get('laravelcloudflare.cache'), []);

        if (! empty($proxies)) {
            $this->proxies = array_merge((array) $this->proxies, $proxies);
        }

        parent::setTrustedProxyIpAddresses($request);
    }
}
