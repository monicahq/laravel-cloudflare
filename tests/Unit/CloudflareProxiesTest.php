<?php

namespace Monicahq\Cloudflare\Tests\Unit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Monicahq\Cloudflare\CloudflareProxies;
use Monicahq\Cloudflare\Exceptions\InvalidProxyListException;
use Monicahq\Cloudflare\Tests\FeatureTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use UnexpectedValueException;

class CloudflareProxiesTest extends FeatureTestCase
{
    #[Test]
    public function it_loads_empty_ips()
    {
        $loader = $this->app->make(CloudflareProxies::class);

        $ips = $loader->load(0);

        $this->assertNotNull($ips);
        $this->assertCount(0, $ips);
    }

    #[Test]
    #[Group('network')]
    public function it_loads_real_mode()
    {
        $loader = $this->app->make(CloudflareProxies::class);

        $ips = $loader->load();

        $this->assertNotNull($ips);
        $this->assertTrue(count($ips) > 0);
    }

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    private function loadFails(): InvalidProxyListException
    {
        try {
            $this->app->make(CloudflareProxies::class)->load();
        } catch (InvalidProxyListException $e) {
            $this->assertInstanceOf(UnexpectedValueException::class, $e);

            return $e;
        }

        $this->fail('InvalidProxyListException not thrown');
    }

    #[Test]
    public function it_trims_lines_and_tolerates_blank_lines()
    {
        $this->fakeCloudflare(" 173.245.48.0/20 \r\n\r\n104.16.0.0/13", "2400:cb00::/32\n\n");

        $ips = $this->app->make(CloudflareProxies::class)->load();

        $this->assertSame(['173.245.48.0/20', '104.16.0.0/13', '2400:cb00::/32'], $ips);
    }

    #[Test]
    public function it_removes_duplicates()
    {
        $this->fakeCloudflare("173.245.48.0/20\n2400:cb00::/32", '2400:cb00::/32');

        $ips = $this->app->make(CloudflareProxies::class)->load();

        $this->assertSame(['173.245.48.0/20', '2400:cb00::/32'], $ips);
    }

    #[Test]
    public function it_rejects_an_html_body()
    {
        $this->fakeCloudflare('<html><body>Error</body></html>');

        $this->assertStringContainsString('<html>', $this->loadFails()->getMessage());
    }

    #[Test]
    public function it_rejects_a_catch_all_range()
    {
        $this->fakeCloudflare("173.245.48.0/20\n0.0.0.0/0");

        $this->assertStringContainsString('0.0.0.0/0', $this->loadFails()->getMessage());
    }

    #[Test]
    public function it_rejects_an_empty_list()
    {
        $this->fakeCloudflare(self::CF_IPV4, '');

        $this->loadFails();
    }

    #[Test]
    public function it_fails_when_one_family_fails()
    {
        $this->app['config']->set('laravelcloudflare.url', 'https://fake');
        Http::fake([
            'https://fake/ips-v4' => Http::response(self::CF_IPV4, 200),
            'https://fake/ips-v6' => Http::response('', 500),
        ]);

        $this->loadFails();
    }

    #[Test]
    public function it_refuses_an_insecure_source()
    {
        Http::fake();
        $this->app['config']->set('laravelcloudflare.url', 'http://fake');

        $this->assertStringContainsString('https://', $this->loadFails()->getMessage());
        Http::assertNothingSent();
    }

    #[Test]
    public function it_does_not_download_when_no_family_is_requested()
    {
        Http::fake();

        $this->assertSame([], $this->app->make(CloudflareProxies::class)->load(0));
        Http::assertNothingSent();
    }

    #[Test]
    public function it_refuses_a_redirect_to_another_host()
    {
        $this->app['config']->set('laravelcloudflare.url', 'https://fake');
        Http::fake([
            'https://fake/ips-v4' => Http::response('', 302, ['Location' => 'https://evil.example/ips-v4']),
            'https://evil.example/*' => Http::response(self::CF_IPV4, 200),
        ]);

        $this->loadFails();
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'evil.example'));
    }

    #[Test]
    public function it_refuses_a_redirect_to_plain_http()
    {
        $this->app['config']->set('laravelcloudflare.url', 'https://fake');
        Http::fake([
            'https://fake/ips-v4' => Http::response('', 302, ['Location' => 'http://fake/ips-v4']),
        ]);

        $this->loadFails();
    }

    #[Test]
    public function it_follows_a_redirect_on_the_same_host()
    {
        $this->app['config']->set('laravelcloudflare.url', 'https://fake');
        Http::fake([
            'https://fake/ips-v4' => Http::response('', 301, ['Location' => '/ips-v4/']),
            'https://fake/ips-v4/' => Http::response(self::CF_IPV4, 200),
            'https://fake/ips-v6' => Http::response(self::CF_IPV6, 200),
        ]);

        $ips = $this->app->make(CloudflareProxies::class)->load();

        $this->assertSame(['173.245.48.0/20', '104.16.0.0/13', '2400:cb00::/32', '2a06:98c0::/29'], $ips);
    }

    private function captureOptions(): \ArrayObject
    {
        $captured = new \ArrayObject;
        $this->app['config']->set('laravelcloudflare.url', 'https://fake');
        Http::fake(function ($request, array $options) use ($captured) {
            $captured->append($options);

            return Http::response(str_ends_with($request->url(), 'ips-v6') ? self::CF_IPV6 : self::CF_IPV4, 200);
        });

        return $captured;
    }

    #[Test]
    public function it_applies_the_configured_timeout()
    {
        $this->app['config']->set('laravelcloudflare.timeout', 2);
        $captured = $this->captureOptions();

        $this->app->make(CloudflareProxies::class)->load();

        $this->assertCount(2, $captured);
        $this->assertSame(2, $captured[0]['timeout']);
        $this->assertLessThanOrEqual(2, $captured[0]['connect_timeout']);
    }

    #[Test]
    public function it_clamps_the_timeout_to_one_second()
    {
        $this->app['config']->set('laravelcloudflare.timeout', 0);
        $captured = $this->captureOptions();

        $this->app->make(CloudflareProxies::class)->load();

        $this->assertSame(1, $captured[0]['timeout']);
    }

    #[Test]
    public function it_converts_connection_errors()
    {
        $this->app['config']->set('laravelcloudflare.url', 'https://fake');
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->assertStringContainsString('Connection timed out', $this->loadFails()->getMessage());
    }

    #[Test]
    public function it_stops_after_too_many_redirects()
    {
        $this->app['config']->set('laravelcloudflare.url', 'https://fake');
        Http::fake([
            'https://fake/ips-v4' => Http::response('', 302, ['Location' => 'https://fake/ips-v4']),
        ]);

        $this->loadFails();
    }
}
