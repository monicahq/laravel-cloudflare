<?php

namespace Monicahq\Cloudflare\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Monicahq\Cloudflare\ProxyCache;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TrustProxies extends Middleware
{
    /**
     * Request attribute holding the Cloudflare ranges for the current request.
     *
     * @var string
     */
    private const RANGES_ATTRIBUTE = 'laravelcloudflare.ranges';

    /**
     * Handle an incoming request.
     *
     * @throws HttpException
     */
    #[\Override]
    public function handle(Request $request, Closure $next)
    {
        if ((bool) Config::get('laravelcloudflare.enabled')) {
            $request->attributes->set(self::RANGES_ATTRIBUTE, App::make(ProxyCache::class)->forRequest());

            if (Config::get('laravelcloudflare.replace_ip') === true) {
                $this->setRemoteAddr($request);
            }
        }

        return parent::handle($request, $next);
    }

    /**
     * Set RemoteAddr server value using Cf-Connecting-Ip header.
     * The header is only used when the request comes from Cloudflare and holds a single valid IP address.
     */
    protected function setRemoteAddr(Request $request): void
    {
        $ranges = $this->cloudflareRanges($request);
        $remoteAddr = $request->server->get('REMOTE_ADDR');

        if ($ranges === [] || ! is_string($remoteAddr) || ! IpUtils::checkIp($remoteAddr, $ranges)) {
            return;
        }

        $ip = trim($request->headers->get('Cf-Connecting-Ip') ?? '');

        if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
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

            return;
        }

        parent::setTrustedProxyIpAddresses($request);
    }

    /**
     * Sets the trusted proxies on the request to the operator's proxies and the Cloudflare ranges.
     * The trusted proxies are computed for each request: no static state is modified.
     */
    protected function setTrustedProxyCloudflare(Request $request): void
    {
        $proxies = $this->proxies();

        if ($proxies === null || $proxies === [] || $proxies === '') {
            $proxies = Config::get('trustedproxy.proxies');
        }

        if ($proxies === '*' || $proxies === '**') {
            parent::setTrustedProxyIpAddresses($request);

            return;
        }

        $proxies = is_string($proxies)
            ? array_filter(array_map(trim(...), explode(',', $proxies)), fn (string $proxy): bool => $proxy !== '')
            : (array) $proxies;

        $trusted = array_values(array_unique(array_merge($proxies, $this->cloudflareRanges($request))));

        if ($trusted === []) {
            parent::setTrustedProxyIpAddresses($request);

            return;
        }

        $this->setTrustedProxyIpAddressesToSpecificIps($request, $trusted);
    }

    /**
     * Get the Cloudflare ranges for the current request.
     *
     * @return list<string>
     */
    private function cloudflareRanges(Request $request): array
    {
        $ranges = $request->attributes->get(self::RANGES_ATTRIBUTE);

        if (! is_array($ranges)) {
            $ranges = App::make(ProxyCache::class)->forRequest();
            $request->attributes->set(self::RANGES_ATTRIBUTE, $ranges);
        }

        return array_values(array_filter($ranges, is_string(...)));
    }
}
