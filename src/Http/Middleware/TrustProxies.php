<?php

namespace Monicahq\Cloudflare\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Config\Repository;

class TrustProxies
{
    /**
     * The config repository instance.
     *
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;

    /**
     * Create a new middleware instance.
     *
     * @param \Illuminate\Contracts\Config\Repository $config
     */
    public function __construct(Repository $config)
    {
        $this->config = $config;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $proxies = Cache::get($this->config->get('laravelcloudflare.cache'), []);

        if (! empty($proxies)) {
            $request->setTrustedProxies($proxies, $this->getTrustedHeaderNames());
        }

        return $next($request);
    }

    /**
     * Retrieve trusted header name(s), falling back to defaults if config not set.
     *
     * @return int A bit field of Request::HEADER_*, to set which headers to trust from your proxies.
     */
    protected function getTrustedHeaderNames()
    {
        $headers = $this->config->get('laravelcloudflare.headers');
        switch ($headers) {
            case 'HEADER_X_FORWARDED_AWS_ELB':
            case Request::HEADER_X_FORWARDED_AWS_ELB:
                $headers = Request::HEADER_X_FORWARDED_AWS_ELB;
                break;
            case 'HEADER_FORWARDED':
            case Request::HEADER_FORWARDED:
                $headers = Request::HEADER_FORWARDED;
                break;
            default:
                $headers = Request::HEADER_X_FORWARDED_ALL;
        }

        return $headers;
    }
}
