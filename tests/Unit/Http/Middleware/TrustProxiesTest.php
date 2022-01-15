<?php

namespace Monicahq\Cloudflare\Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use Monicahq\Cloudflare\Http\Middleware\TrustProxies;
use Monicahq\Cloudflare\Tests\FeatureTestCase;
use Monicahq\Cloudflare\CloudflareProxies;

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
        $this->mock(CloudflareProxies::class, function (MockInterface $mock) {
            $mock->shouldReceive('load')
                ->andReturn(['expect']);
        });

        $request = new Request();

        $this->app->make(TrustProxies::class)->handle($request, function () {
        });

        $proxies = $request->getTrustedProxies();

        $this->assertEquals(['expect'], $proxies);
    }
}
