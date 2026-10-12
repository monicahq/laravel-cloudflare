<?php

namespace Monicahq\Cloudflare\Tests;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Http;
use Monicahq\Cloudflare\LaravelCloudflare;
use Monicahq\Cloudflare\TrustedProxyServiceProvider;
use Orchestra\Testbench\TestCase;

class FeatureTestCase extends TestCase
{
    public const CF_IPV4 = "173.245.48.0/20\n104.16.0.0/13";

    public const CF_IPV6 = "2400:cb00::/32\n2a06:98c0::/29";

    #[\Override]
    protected function tearDown(): void
    {
        LaravelCloudflare::getProxiesUsing(null);
        TrustProxies::flushState();

        parent::tearDown();
    }

    /**
     * Fake the Cloudflare ip lists endpoints.
     */
    protected function fakeCloudflare(string $v4 = self::CF_IPV4, string $v6 = self::CF_IPV6, int $status = 200): void
    {
        $this->app['config']->set('laravelcloudflare.url', 'https://fake');

        Http::fake([
            'https://fake/ips-v4' => Http::response($v4, $status),
            'https://fake/ips-v6' => Http::response($v6, $status),
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [
            TrustedProxyServiceProvider::class,
        ];
    }

    protected function resolveApplicationCore($app)
    {
        parent::resolveApplicationCore($app);

        $app->detectEnvironment(fn () => 'testing');
    }
}
