<?php

namespace Monicahq\Cloudflare\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TrustProxies
{
    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_ALL;

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $proxies = Cache::get('cloudflare.proxies', []);

        if (! empty($proxies)) {
            $request->setTrustedProxies($proxies, $this->headers);
        }

        return $next($request);
    }
}
