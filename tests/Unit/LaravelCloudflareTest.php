<?php

namespace Monicahq\Cloudflare\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Monicahq\Cloudflare\Facades\CloudflareProxies;
use Monicahq\Cloudflare\Http\Middleware\TrustProxies;
use Monicahq\Cloudflare\LaravelCloudflare;
use Monicahq\Cloudflare\Tests\FeatureTestCase;

class LaravelCloudflareTest extends FeatureTestCase
{
    private static bool $run;

    /** @test */
    public function it_call_callback()
    {
        static::$run = false;

        LaravelCloudflare::getProxiesUsing(function () {
            static::$run = true;

            return ['expect'];
        });

        try {
            $request = new Request;

            $this->app->make(TrustProxies::class)->handle($request, fn () => null);

            $proxies = $request->getTrustedProxies();

            $this->assertTrue(static::$run);
            $this->assertEquals(['expect'], $proxies);
        } finally {
            LaravelCloudflare::getProxiesUsing(null);
        }
    }

    /** @test */
    public function it_call_load()
    {
        CloudflareProxies::shouldReceive('load')
            ->once()
            ->andReturn(['expect']);

        $request = new Request;

        $this->app->make(TrustProxies::class)->handle($request, fn () => null);

        $proxies = $request->getTrustedProxies();

        $this->assertEquals(['expect'], $proxies);
        $this->assertEquals(['expect'], Cache::get('cloudflare.proxies'));
    }
}
