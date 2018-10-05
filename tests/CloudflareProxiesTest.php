<?php

namespace Tests;

use Monicahq\Cloudflare\CloudflareProxies;
use PHPUnit\Framework\TestCase;

class CloudflareProxiesTest extends TestCase
{
    function test_load_empty()
    {
        $loader = new CloudflareProxies();

        $ips = $loader->load(0);

        $this->assertNotNull($ips);
        $this->assertCount(0, $ips);
    }

    function test_load_ipv4()
    {
        $loader = new CloudflareProxies();

        $ips = $loader->load(CloudflareProxies::IP_VERSION_4);

        $this->assertNotNull($ips);
        $this->assertGreaterThan(5, count($ips));
    }

    function test_load_ipv6()
    {
        $loader = new CloudflareProxies();

        $ips = $loader->load(CloudflareProxies::IP_VERSION_6);

        $this->assertNotNull($ips);
        $this->assertGreaterThan(5, count($ips));
    }

    function test_load_all()
    {
        $loader = new CloudflareProxies();

        $ips = $loader->load(CloudflareProxies::IP_VERSION_ANY);

        $this->assertNotNull($ips);
        $this->assertGreaterThan(10, count($ips));
    }

    function test_load_default()
    {
        $loader = new CloudflareProxies();

        $ips = $loader->load();

        $this->assertNotNull($ips);
        $this->assertGreaterThan(10, count($ips));
    }
}