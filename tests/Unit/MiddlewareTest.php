<?php

namespace Monicahq\Cloudflare\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Monicahq\Cloudflare\Tests\FeatureTestCase;
use Monicahq\Cloudflare\Http\Middleware\TrustProxies;

class MiddlewareTest extends FeatureTestCase
{
    public function test_trust_proxies()
    {
        Cache::shouldReceive('get')
            ->with('cloudflare.proxies', [])
            ->andReturn(['expect']);

        $request = new Request();
        (new TrustProxies($this->app->make('config')))->handle($request, function() {});

        $this->assertEquals(
            $request->getTrustedProxies(),
            ['expect']
        );
    }
}
