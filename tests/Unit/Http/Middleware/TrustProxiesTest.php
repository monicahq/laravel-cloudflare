<?php

namespace Monicahq\Cloudflare\Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Monicahq\Cloudflare\Http\Middleware\TrustProxies;
use Monicahq\Cloudflare\LaravelCloudflare;
use Monicahq\Cloudflare\Tests\FeatureTestCase;

class TrustProxiesTest extends FeatureTestCase
{
    /** @test */
    public function it_sets_trusted_proxies()
    {
        Cache::shouldReceive('rememberForever')
            ->with('cloudflare.proxies', \Closure::class)
            ->andReturn(['expect']);

        $request = new Request();

        $this->app->make(TrustProxies::class)->handle($request, fn () => null);

        $proxies = $request->getTrustedProxies();

        $this->assertEquals(['expect'], $proxies);
    }

    /** @test */
    public function it_sets_trusted_proxies_with_cache()
    {
        LaravelCloudflare::getProxiesUsing(function () {
            return ['expect'];
        });

        try {
            $request = new Request();

            $this->app->make(TrustProxies::class)->handle($request, fn () => null);

            $proxies = $request->getTrustedProxies();

            $this->assertEquals(['expect'], $proxies);
            $this->assertTrue(Cache::has('cloudflare.proxies'));
            $this->assertEquals(['expect'], Cache::get('cloudflare.proxies'));
        } finally {
            LaravelCloudflare::getProxiesUsing(null);
        }
    }

    /** @test */
    public function it_does_not_sets_trusted_proxies()
    {
        Cache::shouldReceive('rememberForever')
            ->with('cloudflare.proxies', \Closure::class)
            ->andReturn([]);

        $request = new Request();

        $this->app->make(TrustProxies::class)->handle($request, fn () => null);

        $proxies = $request->getTrustedProxies();

        $this->assertEquals([], $proxies);
    }

    /** @test */
    public function it_deactivates_middleware()
    {
        config(['laravelcloudflare.enabled' => false]);

        $request = new Request();

        $this->app->make(TrustProxies::class)->handle($request, fn () => null);

        $proxies = $request->getTrustedProxies();

        $this->assertEquals([], $proxies);
        $this->assertFalse(Cache::has('cloudflare.proxies'));
    }

    /** @test */
    public function it_sets_remote_addr()
    {
        config(['laravelcloudflare.replace_ip' => true]);

        $request = new Request();
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->headers->set('Cf-Connecting-Ip', '127.0.1.1');

        $this->app->make(TrustProxies::class)->handle($request, fn () => null);

        $this->assertEquals('127.0.1.1', $request->ip());
    }
}
