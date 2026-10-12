<?php

namespace Monicahq\Cloudflare\Tests\Unit\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Monicahq\Cloudflare\Http\Middleware\TrustProxies;
use Monicahq\Cloudflare\LaravelCloudflare;
use Monicahq\Cloudflare\ProxyCache;
use Monicahq\Cloudflare\Tests\FeatureTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class TrustProxiesTest extends FeatureTestCase
{
    private const RANGES = ['173.245.48.0/20', '104.16.0.0/13', '2400:cb00::/32', '2a06:98c0::/29'];

    private function storeRanges(): void
    {
        $this->app->make(ProxyCache::class)->store(self::RANGES);
    }

    private function request(string $remoteAddr = '173.245.48.1', array $headers = []): Request
    {
        $server = ['REMOTE_ADDR' => $remoteAddr];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return Request::create('/', 'GET', [], [], [], $server);
    }

    private function handle(Request $request): mixed
    {
        return $this->app->make(TrustProxies::class)->handle($request, fn () => 'next');
    }

    #[Test]
    public function it_sets_trusted_proxies()
    {
        $this->storeRanges();

        $request = $this->request();
        $this->handle($request);

        $this->assertEquals(self::RANGES, $request->getTrustedProxies());
    }

    #[Test]
    public function it_loads_and_caches_proxies_from_the_callback()
    {
        LaravelCloudflare::getProxiesUsing(fn () => ['173.245.48.0/20']);

        $request = $this->request();
        $this->handle($request);

        $this->assertEquals(['173.245.48.0/20'], $request->getTrustedProxies());
        $this->assertEquals(['173.245.48.0/20'], Cache::get('cloudflare.proxies'));
    }

    #[Test]
    public function it_trusts_forwarded_headers_from_cloudflare()
    {
        $this->storeRanges();

        $request = $this->request('173.245.48.1', ['X-Forwarded-For' => '198.51.100.7']);
        $this->handle($request);

        $this->assertSame('198.51.100.7', $request->ip());
    }

    #[Test]
    public function it_ignores_cf_connecting_ip_from_outside_cloudflare()
    {
        config(['laravelcloudflare.replace_ip' => true]);
        $this->storeRanges();

        $request = $this->request('203.0.113.9', ['Cf-Connecting-Ip' => '198.51.100.7']);
        $this->handle($request);

        $this->assertSame('203.0.113.9', $request->ip());
    }

    #[Test]
    public function it_ignores_forwarded_headers_from_outside_cloudflare()
    {
        $this->storeRanges();

        $request = $this->request('203.0.113.9', ['X-Forwarded-For' => '198.51.100.7']);
        $this->handle($request);

        $this->assertSame('203.0.113.9', $request->ip());
    }

    #[Test]
    public function it_sets_remote_addr_from_cloudflare()
    {
        config(['laravelcloudflare.replace_ip' => true]);
        $this->storeRanges();

        $request = $this->request('173.245.48.1', ['Cf-Connecting-Ip' => '198.51.100.7']);
        $this->handle($request);

        $this->assertSame('198.51.100.7', $request->ip());
    }

    public static function invalidHeaders(): array
    {
        return [
            ['1.2.3.4, 5.6.7.8'],
            ['1.2.3.4:80'],
            ['junk'],
            [''],
        ];
    }

    #[Test]
    #[DataProvider('invalidHeaders')]
    public function it_ignores_invalid_cf_connecting_ip(string $header)
    {
        config(['laravelcloudflare.replace_ip' => true]);
        $this->storeRanges();

        $request = $this->request('173.245.48.1', ['Cf-Connecting-Ip' => $header]);
        $this->handle($request);

        $this->assertSame('173.245.48.1', $request->ip());
    }

    public static function validHeaders(): array
    {
        return [
            [' 198.51.100.7 ', '198.51.100.7'],
            ['2001:db8::1', '2001:db8::1'],
        ];
    }

    #[Test]
    #[DataProvider('validHeaders')]
    public function it_accepts_valid_cf_connecting_ip(string $header, string $expected)
    {
        config(['laravelcloudflare.replace_ip' => true]);
        $this->storeRanges();

        $request = $this->request('173.245.48.1', ['Cf-Connecting-Ip' => $header]);
        $this->handle($request);

        $this->assertSame($expected, $request->ip());
    }

    #[Test]
    public function it_trusts_nothing_and_serves_the_request_when_ranges_are_unavailable()
    {
        config(['laravelcloudflare.replace_ip' => true]);
        $this->fakeCloudflare(status: 500);

        $request = $this->request('173.245.48.1', [
            'X-Forwarded-For' => '198.51.100.7',
            'Cf-Connecting-Ip' => '198.51.100.7',
        ]);
        $response = $this->handle($request);

        $this->assertSame('next', $response);
        $this->assertSame('173.245.48.1', $request->ip());
        $this->assertSame([], $request->getTrustedProxies());
    }

    #[Test]
    public function it_deactivates_middleware()
    {
        Http::fake();
        config([
            'laravelcloudflare.enabled' => false,
            'laravelcloudflare.replace_ip' => true,
        ]);

        $request = $this->request('203.0.113.9', ['Cf-Connecting-Ip' => '198.51.100.7']);
        $this->handle($request);

        $this->assertSame('203.0.113.9', $request->ip());
        $this->assertSame([], $request->getTrustedProxies());
        $this->assertFalse(Cache::has('cloudflare.proxies'));
        Http::assertNothingSent();
    }

    #[Test]
    public function it_keeps_operator_proxies_without_mutating_static_state()
    {
        $this->storeRanges();
        BaseTrustProxies::at(['10.0.0.1', '173.245.48.0/20']);

        $first = $this->request();
        $this->handle($first);
        $second = $this->request();
        $this->handle($second);

        $expected = ['10.0.0.1', '173.245.48.0/20', '104.16.0.0/13', '2400:cb00::/32', '2a06:98c0::/29'];
        $this->assertEquals($expected, $first->getTrustedProxies());
        $this->assertEquals($expected, $second->getTrustedProxies());

        $static = (new \ReflectionProperty(BaseTrustProxies::class, 'alwaysTrustProxies'))->getValue();
        $this->assertSame(['10.0.0.1', '173.245.48.0/20'], $static);
    }

    #[Test]
    public function it_does_not_keep_trusting_ranges_removed_from_the_cache()
    {
        $this->storeRanges();
        $this->handle($this->request());

        $this->app->make(ProxyCache::class)->store(['104.16.0.0/13']);

        $request = $this->request();
        $this->handle($request);

        $this->assertEquals(['104.16.0.0/13'], $request->getTrustedProxies());
    }

    #[Test]
    public function it_keeps_the_wildcard_operator_configuration()
    {
        $this->storeRanges();
        config(['trustedproxy.proxies' => '*']);

        $request = $this->request('203.0.113.9', ['X-Forwarded-For' => '198.51.100.7']);
        $this->handle($request);

        $this->assertSame(['203.0.113.9'], $request->getTrustedProxies());
    }
}
