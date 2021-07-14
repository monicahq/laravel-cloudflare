<?php

namespace Monicahq\Cloudflare\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Monicahq\Cloudflare\CloudflareProxies;
use Monicahq\Cloudflare\Tests\FeatureTestCase;
use Illuminate\Http\Client\Factory as HttpClient;

class CloudflareProxiesTest extends FeatureTestCase
{
    public function test_load_empty()
    {
        $loader = $this->app->make(CloudflareProxies::class);

        $ips = $loader->load(0);

        $this->assertNotNull($ips);
        $this->assertCount(0, $ips);
    }

    public function test_load_real()
    {
        $loader = $this->app->make(CloudflareProxies::class);

        $ips = $loader->load();

        $this->assertNotNull($ips);
        $this->assertTrue(count($ips) > 0);
    }

    public function test_load_ipv4()
    {
        $this->app['config']->set('laravelcloudflare.url', 'https://fake');
        $this->app[HttpClient::class] = Http::fake([
            'https://fake/ips-v4' => Http::response('0.0.0.0/20', 200),
        ]);

        $loader = $this->app->make(CloudflareProxies::class);

        $ips = $loader->load(CloudflareProxies::IP_VERSION_4);

        $this->assertNotNull($ips);
        $this->assertEquals([
            '0.0.0.0/20',
        ], $ips);
    }

    public function test_load_ipv6()
    {
        $this->app['config']->set('laravelcloudflare.url', 'https://fake');
        $this->app[HttpClient::class] = Http::fake([
            'https://fake/ips-v6' => Http::response('::1/32', 200),
        ]);

        $loader = $this->app->make(CloudflareProxies::class);

        $ips = $loader->load(CloudflareProxies::IP_VERSION_6);

        $this->assertNotNull($ips);
        $this->assertEquals([
            '::1/32',
        ], $ips);
    }

    public function test_load_all()
    {
        $this->app['config']->set('laravelcloudflare.url', 'https://fake');
        $this->app[HttpClient::class] = Http::fake([
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

    public function test_load_default()
    {
        $this->app['config']->set('laravelcloudflare.url', 'https://fake');
        $this->app[HttpClient::class] = Http::fake([
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
}
