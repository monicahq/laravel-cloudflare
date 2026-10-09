<?php

namespace Monicahq\Cloudflare\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Monicahq\Cloudflare\Facades\CloudflareProxies;
use Monicahq\Cloudflare\Http\Middleware\TrustProxies;
use Monicahq\Cloudflare\LaravelCloudflare;
use Monicahq\Cloudflare\Tests\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

class LaravelCloudflareTest extends FeatureTestCase
{
    private static bool $run;

    #[Test]
    public function it_call_callback()
    {
        static::$run = false;

        LaravelCloudflare::getProxiesUsing(function () {
            static::$run = true;

            return ['173.245.48.0/20'];
        });

        try {
            $request = new Request;

            $this->app->make(TrustProxies::class)->handle($request, fn () => null);

            $proxies = $request->getTrustedProxies();

            $this->assertTrue(static::$run);
            $this->assertEquals(['173.245.48.0/20'], $proxies);
        } finally {
            LaravelCloudflare::getProxiesUsing(null);
        }
    }

    #[Test]
    public function it_call_load()
    {
        CloudflareProxies::shouldReceive('load')
            ->once()
            ->andReturn(['173.245.48.0/20']);

        $request = new Request;

        $this->app->make(TrustProxies::class)->handle($request, fn () => null);

        $proxies = $request->getTrustedProxies();

        $this->assertEquals(['173.245.48.0/20'], $proxies);
        $this->assertEquals(['173.245.48.0/20'], Cache::get('cloudflare.proxies'));
    }
}
