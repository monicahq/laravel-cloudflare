<?php

namespace Monicahq\Cloudflare\Tests\Unit;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Monicahq\Cloudflare\CachedList;
use Monicahq\Cloudflare\Exceptions\InvalidProxyListException;
use Monicahq\Cloudflare\LaravelCloudflare;
use Monicahq\Cloudflare\ProxyCache;
use Monicahq\Cloudflare\Tests\FeatureTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class ProxyCacheTest extends FeatureTestCase
{
    private function proxyCache(): ProxyCache
    {
        return $this->app->make(ProxyCache::class);
    }

    #[Test]
    public function it_stores_a_plain_list_and_metadata()
    {
        $this->freezeSecond();
        Cache::forever('cloudflare.proxies.failed', 1);

        $list = $this->proxyCache()->store(['173.245.48.0/20', '2400:cb00::/32']);

        $this->assertSame(['173.245.48.0/20', '2400:cb00::/32'], Cache::get('cloudflare.proxies'));
        $this->assertSame(['refreshed_at' => now()->getTimestamp()], Cache::get('cloudflare.proxies.meta'));
        $this->assertFalse(Cache::has('cloudflare.proxies.failed'));
        $this->assertSame(now()->getTimestamp(), $list->refreshedAt);
    }

    #[Test]
    public function it_does_not_store_an_invalid_list()
    {
        Cache::forever('cloudflare.proxies', ['173.245.48.0/20']);
        Cache::forever('cloudflare.proxies.meta', ['refreshed_at' => 1000]);

        try {
            $this->proxyCache()->store(['*']);
            $this->fail('Exception not thrown');
        } catch (InvalidProxyListException) {
            //
        }

        $this->assertSame(['173.245.48.0/20'], Cache::get('cloudflare.proxies'));
        $this->assertSame(['refreshed_at' => 1000], Cache::get('cloudflare.proxies.meta'));
    }

    #[Test]
    public function it_reads_the_cached_list()
    {
        Cache::forever('cloudflare.proxies', ['173.245.48.0/20', '2400:cb00::/32']);
        Cache::forever('cloudflare.proxies.meta', ['refreshed_at' => 1000]);

        $list = $this->proxyCache()->read();

        $this->assertInstanceOf(CachedList::class, $list);
        $this->assertSame(['173.245.48.0/20', '2400:cb00::/32'], $list->proxies);
        $this->assertSame(1000, $list->refreshedAt);
    }

    #[Test]
    public function it_reads_null_when_missing()
    {
        $this->assertNull($this->proxyCache()->read());
    }

    public static function corruptedValues(): array
    {
        return [
            ['garbage'],
            [[1, 2]],
            [['173.245.48.0/20', '0.0.0.0/0']],
            [[]],
        ];
    }

    #[Test]
    #[DataProvider('corruptedValues')]
    public function it_reads_null_when_corrupted(mixed $value)
    {
        Cache::forever('cloudflare.proxies', $value);

        $this->assertNull($this->proxyCache()->read());
    }

    private function breakCache(): void
    {
        $cache = \Mockery::mock(CacheFactory::class);
        $cache->shouldReceive('store')->andThrow(new RuntimeException('connection refused'));
        $this->app->instance(CacheFactory::class, $cache);
    }

    #[Test]
    public function it_reads_null_silently_when_the_cache_is_unavailable()
    {
        Log::spy();
        $this->breakCache();

        $this->assertNull($this->proxyCache()->read());

        Log::shouldNotHaveReceived('warning');
    }

    #[Test]
    public function it_serves_requests_without_proxies_when_the_cache_is_unavailable()
    {
        Log::spy();
        $this->breakCache();

        $this->assertSame([], $this->proxyCache()->forRequest());

        Log::shouldHaveReceived('warning')
            ->with('laravel-cloudflare: cache unavailable', ['reason' => 'connection refused', 'source' => 'request'])
            ->once();
    }

    public static function invalidCallbackLists(): array
    {
        return [
            [['*']],
            [['0.0.0.0/0']],
            [['173.245.48.0/20', 'junk']],
        ];
    }

    #[Test]
    #[DataProvider('invalidCallbackLists')]
    public function it_rejects_an_invalid_callback_list(array $proxies)
    {
        Log::spy();
        LaravelCloudflare::getProxiesUsing(fn () => $proxies);

        $this->assertSame([], $this->proxyCache()->forRequest());
        $this->assertFalse(Cache::has('cloudflare.proxies'));

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context) => str_starts_with($message, 'laravel-cloudflare: could not load Cloudflare ranges')
                && $context['source'] === 'request')
            ->once();
    }

    #[Test]
    public function it_caches_a_valid_callback_list()
    {
        LaravelCloudflare::getProxiesUsing(fn () => ['173.245.48.0/20']);

        $this->assertSame(['173.245.48.0/20'], $this->proxyCache()->forRequest());
        $this->assertSame(['173.245.48.0/20'], Cache::get('cloudflare.proxies'));
    }

    #[Test]
    public function it_waits_before_retrying_a_failed_download()
    {
        config(['laravelcloudflare.retry_after' => 60]);
        $this->fakeCloudflare(status: 500);

        $this->assertSame([], $this->proxyCache()->forRequest());
        $this->assertSame([], $this->proxyCache()->forRequest());

        Http::assertSentCount(1);
        $this->assertTrue(Cache::has('cloudflare.proxies.failed'));

        $this->travel(61)->seconds();

        $this->assertSame([], $this->proxyCache()->forRequest());
        Http::assertSentCount(2);
    }

    #[Test]
    public function it_logs_the_retry_delay_on_a_failed_download()
    {
        Log::spy();
        config(['laravelcloudflare.retry_after' => 60]);
        $this->fakeCloudflare(status: 500);

        $this->proxyCache()->forRequest();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context) => str_starts_with($message, 'laravel-cloudflare: could not load Cloudflare ranges')
                && $context['source'] === 'request'
                && $context['url'] === 'https://fake'
                && $context['retry_after'] === 60
                && array_keys($context) === ['reason', 'source', 'url', 'retry_after'])
            ->once();
    }

    #[Test]
    public function it_does_not_download_while_another_download_holds_the_lock()
    {
        config(['laravelcloudflare.timeout' => 1]);
        $this->fakeCloudflare();
        $lock = Cache::lock('cloudflare.proxies.lock', 10);
        $lock->get();

        try {
            $start = microtime(true);

            $this->assertSame([], $this->proxyCache()->forRequest());

            $this->assertLessThan(3, microtime(true) - $start);
            Http::assertNothingSent();
            $this->assertFalse(Cache::has('cloudflare.proxies.failed'));
        } finally {
            $lock->release();
        }
    }

    #[Test]
    public function it_returns_the_cached_list_without_downloading()
    {
        Http::fake();
        $this->proxyCache()->store(['173.245.48.0/20']);

        $this->assertSame(['173.245.48.0/20'], $this->proxyCache()->forRequest());
        Http::assertNothingSent();
    }

    public static function failingSources(): array
    {
        return [
            'server error' => [fn (self $test) => $test->fakeCloudflare(status: 500)],
            'catch-all range' => [fn (self $test) => $test->fakeCloudflare('0.0.0.0/0')],
            'throwing callback' => [fn () => LaravelCloudflare::getProxiesUsing(fn () => throw new RuntimeException('boom'))],
        ];
    }

    #[Test]
    #[DataProvider('failingSources')]
    public function it_keeps_the_good_list_when_a_refresh_fails(\Closure $setUp)
    {
        $this->proxyCache()->store(['173.245.48.0/20']);
        $before = [Cache::get('cloudflare.proxies'), Cache::get('cloudflare.proxies.meta')];
        $this->travel(1)->hours();
        $setUp($this);

        try {
            $this->proxyCache()->refresh();
            $this->fail('InvalidProxyListException not thrown');
        } catch (InvalidProxyListException) {
            //
        }

        $this->assertSame($before, [Cache::get('cloudflare.proxies'), Cache::get('cloudflare.proxies.meta')]);
    }

    #[Test]
    public function it_refreshes_even_while_backing_off()
    {
        Cache::put('cloudflare.proxies.failed', 1, 60);
        $this->fakeCloudflare();

        $list = $this->proxyCache()->refresh();

        $this->assertSame(['173.245.48.0/20', '104.16.0.0/13', '2400:cb00::/32', '2a06:98c0::/29'], $list->proxies);
        $this->assertFalse(Cache::has('cloudflare.proxies.failed'));
    }

    #[Test]
    public function it_warns_about_a_stale_list_once_per_hour()
    {
        Log::spy();
        $this->proxyCache()->store(['173.245.48.0/20']);
        $this->travel(8)->days();

        foreach (range(1, 3) as $i) {
            $this->assertSame(['173.245.48.0/20'], $this->proxyCache()->forRequest());
        }

        Log::shouldHaveReceived('warning')
            ->with('laravel-cloudflare: cached Cloudflare ranges are stale', ['age_days' => 8, 'stale_after' => 7])
            ->once();

        $this->travel(61)->minutes();
        $this->assertSame(['173.245.48.0/20'], $this->proxyCache()->forRequest());

        Log::shouldHaveReceived('warning')
            ->with('laravel-cloudflare: cached Cloudflare ranges are stale', ['age_days' => 8, 'stale_after' => 7])
            ->twice();
    }

    #[Test]
    public function it_does_not_warn_about_a_fresh_list()
    {
        Log::spy();
        $this->proxyCache()->store(['173.245.48.0/20']);
        $this->travel(6)->days();

        $this->proxyCache()->forRequest();

        Log::shouldNotHaveReceived('warning');
    }

    #[Test]
    public function it_starts_the_age_of_a_legacy_list_on_first_request()
    {
        Log::spy();
        $this->freezeSecond();
        Cache::forever('cloudflare.proxies', ['173.245.48.0/20']);

        $this->assertSame(['173.245.48.0/20'], $this->proxyCache()->forRequest());

        $this->assertSame(['refreshed_at' => now()->getTimestamp()], Cache::get('cloudflare.proxies.meta'));
        Log::shouldNotHaveReceived('warning');
    }

    #[Test]
    public function it_reads_a_legacy_list_without_writing_metadata()
    {
        Cache::forever('cloudflare.proxies', ['173.245.48.0/20']);

        $list = $this->proxyCache()->read();

        $this->assertSame(['173.245.48.0/20'], $list?->proxies);
        $this->assertNull($list?->refreshedAt);
        $this->assertFalse(Cache::has('cloudflare.proxies.meta'));
    }
}
