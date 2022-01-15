<?php

namespace Monicahq\Cloudflare\Tests\Unit\Commands;

use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Monicahq\Cloudflare\Tests\FeatureTestCase;
use Monicahq\Cloudflare\CloudflareProxies;

class ReloadTest extends FeatureTestCase
{
    /** @test */
    public function it_loads_proxies()
    {
        $this->mock(CloudflareProxies::class, function (MockInterface $mock) {
            $mock->shouldReceive('load')
                ->andReturn(['expect']);
        });

        $this->artisan('cloudflare:reload')
            ->expectsOutput('Cloudflare\'s IP blocks have been reloaded.')
            ->assertExitCode(0);

        $this->assertEquals(['expect'], $this->app['cache']->get('cloudflare.proxies'));
    }

    /** @test */
    public function it_saves_address_in_cache()
    {
        $this->app['config']->set('laravelcloudflare.url', 'https://fake');
        $this->app[HttpClient::class] = Http::fake([
            'https://fake/ips-v4' => Http::response('0.0.0.0/20', 200),
            'https://fake/ips-v6' => Http::response('::1/32', 200),
        ]);

        $this->artisan('cloudflare:reload');

        $this->assertEquals(['0.0.0.0/20', '::1/32'], $this->app['cache']->get('cloudflare.proxies'));
    }
}
