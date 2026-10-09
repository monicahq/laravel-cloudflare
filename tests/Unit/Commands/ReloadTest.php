<?php

namespace Monicahq\Cloudflare\Tests\Unit\Commands;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Monicahq\Cloudflare\Facades\CloudflareProxies;
use Monicahq\Cloudflare\LaravelCloudflare;
use Monicahq\Cloudflare\ProxyCache;
use Monicahq\Cloudflare\Tests\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class ReloadTest extends FeatureTestCase
{
    #[Test]
    public function it_loads_proxies()
    {
        CloudflareProxies::shouldReceive('load')
            ->once()
            ->andReturn(['173.245.48.0/20', '2400:cb00::/32']);

        $this->artisan('cloudflare:reload')
            ->expectsOutput('Cloudflare\'s IP blocks have been reloaded: 1 IPv4 and 1 IPv6 ranges.')
            ->assertExitCode(0)
            ->run();

        $this->assertEquals(['173.245.48.0/20', '2400:cb00::/32'], Cache::get('cloudflare.proxies'));
    }

    #[Test]
    public function it_saves_address_in_cache()
    {
        $this->freezeSecond();
        $this->fakeCloudflare();

        $this->artisan('cloudflare:reload')
            ->expectsOutput('Cloudflare\'s IP blocks have been reloaded: 2 IPv4 and 2 IPv6 ranges.')
            ->assertExitCode(0)
            ->run();

        $this->assertEquals(['173.245.48.0/20', '104.16.0.0/13', '2400:cb00::/32', '2a06:98c0::/29'], Cache::get('cloudflare.proxies'));
        $this->assertEquals(['refreshed_at' => now()->getTimestamp()], Cache::get('cloudflare.proxies.meta'));
    }

    #[Test]
    public function it_keeps_the_previous_list_when_the_download_fails()
    {
        $this->app->make(ProxyCache::class)->store(['173.245.48.0/20']);
        $before = [Cache::get('cloudflare.proxies'), Cache::get('cloudflare.proxies.meta')];
        $this->fakeCloudflare(status: 500);
        Log::spy();

        $this->artisan('cloudflare:reload')
            ->expectsOutputToContain('The previously cached list was kept.')
            ->assertExitCode(1)
            ->run();

        $this->assertSame($before, [Cache::get('cloudflare.proxies'), Cache::get('cloudflare.proxies.meta')]);
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context) => $message === 'laravel-cloudflare: reload failed'
                && array_keys($context) === ['reason', 'source', 'url']
                && $context['source'] === 'reload'
                && $context['url'] === 'https://fake')
            ->once();
    }

    #[Test]
    public function it_fails_on_an_invalid_list()
    {
        $this->fakeCloudflare('0.0.0.0/0');

        $this->artisan('cloudflare:reload')
            ->expectsOutputToContain('0.0.0.0/0')
            ->assertExitCode(1)
            ->run();

        $this->assertFalse(Cache::has('cloudflare.proxies'));
    }

    #[Test]
    public function it_fails_when_the_callback_throws()
    {
        LaravelCloudflare::getProxiesUsing(fn () => throw new RuntimeException('boom'));

        $this->artisan('cloudflare:reload')
            ->expectsOutputToContain('boom')
            ->assertExitCode(1)
            ->run();
    }

    #[Test]
    public function it_deactivate_command()
    {
        Http::fake();
        config(['laravelcloudflare.enabled' => false]);

        $this->artisan('cloudflare:reload')
            ->expectsOutput('Laravel Cloudflare is disabled; nothing was reloaded.')
            ->assertExitCode(0)
            ->run();

        $this->assertFalse(Cache::has('cloudflare.proxies'));
        Http::assertNothingSent();
    }
}
