<?php

namespace Monicahq\Cloudflare\Tests\Unit\Commands;

use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Http;
use Monicahq\Cloudflare\Facades\CloudflareProxies;
use Monicahq\Cloudflare\Tests\FeatureTestCase;

class ReloadTest extends FeatureTestCase
{
    /** @test */
    public function it_loads_proxies()
    {
        CloudflareProxies::shouldReceive('load')
            ->once()
            ->andReturn(['expect']);

        $this->artisan('cloudflare:reload')
            ->expectsOutput('Cloudflare\'s IP blocks have been reloaded.')
            ->assertExitCode(0)
            ->run();

        $this->assertTrue($this->app['cache']->has('cloudflare.proxies'));
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

        $this->artisan('cloudflare:reload')
            ->run();

        $this->assertEquals(['0.0.0.0/20', '::1/32'], $this->app['cache']->get('cloudflare.proxies'));
    }

    /** @test */
    public function it_deactivate_command()
    {
        config(['laravelcloudflare.enabled' => false]);

        $this->artisan('cloudflare:reload')
            ->doesntExpectOutput('Cloudflare\'s IP blocks have been reloaded.')
            ->assertExitCode(0)
            ->run();

        $this->assertFalse($this->app['cache']->has('cloudflare.proxies'));
    }
}
