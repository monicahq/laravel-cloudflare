<?php

namespace Monicahq\Cloudflare\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Monicahq\Cloudflare\CloudflareProxies;
use Monicahq\Cloudflare\Tests\FeatureTestCase;
use UnexpectedValueException;

class CloudflareProxiesTest extends FeatureTestCase
{
    /** @test */
    public function it_loads_empty_ips()
    {
        $loader = $this->app->make(CloudflareProxies::class);

        $ips = $loader->load(0);

        $this->assertNotNull($ips);
        $this->assertCount(0, $ips);
    }

    /** @test */
    public function it_loads_real_mode()
    {
        $loader = $this->app->make(CloudflareProxies::class);

        $ips = $loader->load();

        $this->assertNotNull($ips);
        $this->assertTrue(count($ips) > 0);
    }

    /** @test */
    public function it_loads_ipv4()
    {
        $this->app['config']->set('laravelcloudflare.url', 'https://fake');
        Http::fake([
            'https://fake/ips-v4' => Http::response('0.0.0.0/20', 200),
        ]);

        $loader = $this->app->make(CloudflareProxies::class);

        $ips = $loader->load(CloudflareProxies::IP_VERSION_4);

        $this->assertNotNull($ips);
        $this->assertEquals([
            '0.0.0.0/20',
        ], $ips);
    }

    /** @test */
    public function it_loads_ipv6()
    {
        $this->app['config']->set('laravelcloudflare.url', 'https://fake');
        Http::fake([
            'https://fake/ips-v6' => Http::response('::1/32', 200),
        ]);

        $loader = $this->app->make(CloudflareProxies::class);

        $ips = $loader->load(CloudflareProxies::IP_VERSION_6);

        $this->assertNotNull($ips);
        $this->assertEquals([
            '::1/32',
        ], $ips);
    }

    /** @test */
    public function it_loads_all_ips()
    {
        $this->app['config']->set('laravelcloudflare.url', 'https://fake');
        Http::fake([
            'https://fake/ips-v4' => Http::response('0.0.0.0/20', 200),
            'https://fake/ips-v6' => Http::response('::1/32', 200),
        ]);

        $loader = $this->app->make(CloudflareProxies::class);

        $ips = $loader->load(CloudflareProxies::IP_VERSION_ANY);

        $this->assertNotNull($ips);
        $this->assertEquals([
            '0.0.0.0/20',
            '::1/32',
        ], $ips);
    }

    /** @test */
    public function it_loads_all_ips_when_zero_args()
    {
        $this->app['config']->set('laravelcloudflare.url', 'https://fake');
        Http::fake([
            'https://fake/ips-v4' => Http::response('0.0.0.0/20', 200),
            'https://fake/ips-v6' => Http::response('::1/32', 200),
        ]);

        $loader = $this->app->make(CloudflareProxies::class);

        $ips = $loader->load();

        $this->assertNotNull($ips);

        $this->assertEquals([
            '0.0.0.0/20',
            '::1/32',
        ], $ips);
    }

    /** @test */
    public function it_throw_error_if_status_ko()
    {
        $this->app['config']->set('laravelcloudflare.url', 'https://fake');
        Http::fake([
            'https://fake/ips-v4' => Http::response('', 500),
        ]);

        $loader = $this->app->make(CloudflareProxies::class);

        $this->expectException(UnexpectedValueException::class);
        $ips = $loader->load();
    }
}
