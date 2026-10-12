<?php

namespace Monicahq\Cloudflare\Tests\Unit\Commands;

use Illuminate\Support\Facades\Cache;
use Monicahq\Cloudflare\ProxyCache;
use Monicahq\Cloudflare\Tests\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

class ViewTest extends FeatureTestCase
{
    #[Test]
    public function it_displays_addresses()
    {
        $this->freezeSecond();
        $this->app->make(ProxyCache::class)->store(['173.245.48.0/20', '2400:cb00::/32']);

        $this->artisan('cloudflare:view')
            ->expectsTable(['Address'], [['173.245.48.0/20'], ['2400:cb00::/32']])
            ->expectsOutput('Last refreshed: '.gmdate('c', now()->getTimestamp()).' (0 days ago)')
            ->doesntExpectOutputToContain('STALE')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_flags_a_stale_list()
    {
        $this->app->make(ProxyCache::class)->store(['173.245.48.0/20']);
        $this->travel(8)->days();

        $this->artisan('cloudflare:view')
            ->expectsOutputToContain('(8 days ago)')
            ->expectsOutputToContain('STALE (older than 7 days)')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_displays_a_legacy_list_without_writing()
    {
        Cache::forever('cloudflare.proxies', ['173.245.48.0/20']);

        $this->artisan('cloudflare:view')
            ->expectsTable(['Address'], [['173.245.48.0/20']])
            ->expectsOutput('Last refreshed: unknown (cached by an earlier version)')
            ->assertExitCode(0)
            ->run();

        $this->assertFalse(Cache::has('cloudflare.proxies.meta'));
    }

    #[Test]
    public function it_tells_when_nothing_is_cached()
    {
        $this->artisan('cloudflare:view')
            ->expectsOutput('No Cloudflare IP ranges are cached. Run `php artisan cloudflare:reload` to load them.')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_tells_when_the_cached_value_is_invalid()
    {
        Cache::forever('cloudflare.proxies', 'garbage');

        $this->artisan('cloudflare:view')
            ->expectsOutput('No Cloudflare IP ranges are cached. Run `php artisan cloudflare:reload` to load them.')
            ->assertExitCode(0);
    }
}
