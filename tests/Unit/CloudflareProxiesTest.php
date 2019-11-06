<?php

namespace Monicahq\Cloudflare\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Monicahq\Cloudflare\CloudflareProxies;
use Monicahq\Cloudflare\Tests\FeatureTestCase;

class CloudflareProxiesTest extends FeatureTestCase
{
    public function test_load_empty()
    {
        $loader = new CloudflareProxies($this->app->make('config'));

        $ips = $loader->load(0);

        $this->assertNotNull($ips);
        $this->assertCount(0, $ips);
    }

    public function test_load_ipv4()
    {
        $mock = new MockHandler([
            new Response(200, [], '0.0.0.0/20'),
        ]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $loader = new CloudflareProxies($this->app->make('config'), $client);

        $ips = $loader->load(CloudflareProxies::IP_VERSION_4);

        $this->assertNotNull($ips);
        $this->assertEquals([
            '0.0.0.0/20',
        ], $ips);
    }

    public function test_load_ipv6()
    {
        $mock = new MockHandler([
            new Response(200, [], '::1/32'),
        ]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $loader = new CloudflareProxies($this->app->make('config'), $client);

        $ips = $loader->load(CloudflareProxies::IP_VERSION_6);

        $this->assertNotNull($ips);
        $this->assertEquals([
            '::1/32',
        ], $ips);
    }

    public function test_load_all()
    {
        $mock = new MockHandler([
            new Response(200, [], '0.0.0.0/20'),
            new Response(200, [], '::1/32'),
        ]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $loader = new CloudflareProxies($this->app->make('config'), $client);

        $ips = $loader->load(CloudflareProxies::IP_VERSION_ANY);

        $this->assertNotNull($ips);
        $this->assertEquals([
            '0.0.0.0/20',
            '::1/32',
        ], $ips);
    }

    public function test_load_default()
    {
        $me = $this;
        $mock = new MockHandler([
            new Response(200, [], '0.0.0.0/20'),
            new Response(200, [], '::1/32'),
        ]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $loader = new CloudflareProxies($this->app->make('config'), $client);

        $ips = $loader->load();

        $this->assertNotNull($ips);

        $this->assertEquals([
            '0.0.0.0/20',
            '::1/32',
        ], $ips);
    }

    public function test_right_urls()
    {
        $me = $this;
        $mock = new MockHandler([
            function (\Psr\Http\Message\RequestInterface $request, array $options) use ($me) {
                $me->assertEquals('https://www.cloudflare.com/ips-v4', (string) $request->getUri());

                return new Response(200, [], '0.0.0.0/20');
            },
            function (\Psr\Http\Message\RequestInterface $request, array $options) use ($me) {
                $me->assertEquals('https://www.cloudflare.com/ips-v6', (string) $request->getUri());

                return new Response(200, [], '::1/32');
            },
        ]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $loader = new CloudflareProxies($this->app->make('config'), $client);

        $loader->load();
    }

    public function test_create_guzzle()
    {
        $loader = new CloudflareProxies($this->app->make('config'));

        $reflection = new \ReflectionClass(CloudflareProxies::class);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);

        $client = $property->getValue($loader);

        $this->assertNotNull($client);
        $this->assertInstanceOf(Client::class, $client);
    }
}
