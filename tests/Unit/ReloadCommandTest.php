<?php

namespace Monicahq\Cloudflare\Tests\Unit;

use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Http;
use Monicahq\Cloudflare\Tests\FeatureTestCase;

class ReloadCommandTest extends FeatureTestCase
{
    /** @test */
    public function it_saves_address_in_cache()
    {
        $this->app['config']->set('laravelcloudflare.url', 'https://fake');
        $this->app[HttpClient::class] = Http::fake([
            'https://fake/ips-v4' => Http::response('0.0.0.0/20', 200),
            'https://fake/ips-v6' => Http::response('::1/32', 200),
        ]);

        $this->withoutMockingConsoleOutput();

        $this->artisan('cloudflare:reload');

        $this->assertEquals(['0.0.0.0/20', '::1/32'], $this->app['cache']->get('cloudflare.proxies'));
    }
}
