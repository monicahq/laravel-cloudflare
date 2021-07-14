<?php

namespace Monicahq\Cloudflare\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Monicahq\Cloudflare\Http\Middleware\TrustProxies;
use Monicahq\Cloudflare\Tests\FeatureTestCase;

class MiddlewareTest extends FeatureTestCase
{
    /** @test */
    public function it_sets_trusted_proxies()
    {
        Cache::shouldReceive('get')
            ->with('cloudflare.proxies', [])
            ->andReturn(['expect']);

        $request = new Request();

        $this->app->make(TrustProxies::class)->handle($request, function () {});

        $this->assertEquals(
            $request->getTrustedProxies(),
            ['expect']
        );
    }

    /** @test */
    public function it_does_not_sets_trusted_proxies()
    {
        Cache::shouldReceive('get')
            ->with('cloudflare.proxies', [])
            ->andReturn([]);

        $request = new Request();

        $this->app->make(TrustProxies::class)->handle($request, function () {});

        $this->assertEquals(
            $request->getTrustedProxies(),
            []
        );
    }
}
