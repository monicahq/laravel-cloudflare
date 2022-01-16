<?php

namespace Monicahq\Cloudflare\Tests\Unit;

use Illuminate\Http\Request;
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

        LaravelCloudflare::getProxiesUsing(function() {
            static::$run = true;
            return ['expect'];
        });

        $request = new Request();

        $this->app->make(TrustProxies::class)->handle($request, function () {
        });

        $proxies = $request->getTrustedProxies();

        $this->assertTrue(static::$run);
        $this->assertEquals(['expect'], $proxies);
    }
}
