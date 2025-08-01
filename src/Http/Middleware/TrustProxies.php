<?php

namespace Monicahq\Cloudflare\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Monicahq\Cloudflare\LaravelCloudflare;

class TrustProxies extends Middleware
{
    /**
     * Handle an incoming request.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    #[\Override]
    public function handle(Request $request, Closure $next)
    {
        if (Config::get('laravelcloudflare.replace_ip') === true) {
            $this->setRemoteAddr($request);
        }

        return parent::handle($request, $next);
    }

    /**
     * Set RemoteAddr server value using Cf-Connecting-Ip header.
     */
    protected function setRemoteAddr(Request $request): void
    {
        if (($ip = $request->header('Cf-Connecting-Ip')) !== null) {
            $request->server->set('REMOTE_ADDR', $ip);
        }
    }

    /**
     * Sets the trusted proxies on the request.
     */
    #[\Override]
    protected function setTrustedProxyIpAddresses(Request $request): void
    {
        if ((bool) Config::get('laravelcloudflare.enabled')) {
            $this->setTrustedProxyCloudflare($request);
        }

        parent::setTrustedProxyIpAddresses($request);
    }

    /**
     * Sets the trusted proxies on the request to the value of Cloudflare ips.
     */
    protected function setTrustedProxyCloudflare(Request $request): void
    {
        $cacheKey = Config::get('laravelcloudflare.cache');
        $cachedProxies = Cache::rememberForever($cacheKey, fn () => LaravelCloudflare::getProxies());

        if (count($cachedProxies) > 0) {
            parent::at(collect((array) parent::$alwaysTrustProxies)
                ->merge($cachedProxies)
                ->unique()
                ->toArray()
            );
        }
    }
}
