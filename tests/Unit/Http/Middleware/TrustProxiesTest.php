<?php

namespace Monicahq\Cloudflare\Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Monicahq\Cloudflare\Facades\CloudflareProxies;
use Monicahq\Cloudflare\Http\Middleware\TrustProxies;
use Monicahq\Cloudflare\Tests\FeatureTestCase;

class TrustProxiesTest extends FeatureTestCase
{
    /** @test */
    public function it_sets_trusted_proxies()
    {
        Cache::shouldReceive('get')
            ->with('cloudflare.proxies', \Closure::class)
            ->andReturn(['expect']);

        $request = new Request();

        $this->app->make(TrustProxies::class)->handle($request, function () {
        });

        $proxies = $request->getTrustedProxies();

        $this->assertEquals(['expect'], $proxies);
    }

    /** @test */
    public function it_does_not_sets_trusted_proxies()
    {
        Cache::shouldReceive('get')
            ->with('cloudflare.proxies', \Closure::class)
            ->andReturn([]);

        $request = new Request();

        $this->app->make(TrustProxies::class)->handle($request, function () {
        });

        $proxies = $request->getTrustedProxies();

        $this->assertEquals([], $proxies);
    }

    /** @test */
    public function it_load_trustproxies()
    {
        CloudflareProxies::shouldReceive('load')
            ->once()
            ->andReturn(['expect']);

        $request = new Request();

        $this->app->make(TrustProxies::class)->handle($request, function () {
        });

        $proxies = $request->getTrustedProxies();

        $this->assertEquals(['expect'], $proxies);
        $this->assertEquals(['expect'], $this->app['cache']->get('cloudflare.proxies'));
    }
}
