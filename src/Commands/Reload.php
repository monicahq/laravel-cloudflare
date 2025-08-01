<?php

namespace Monicahq\Cloudflare\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Factory as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Monicahq\Cloudflare\LaravelCloudflare;

final class Reload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cloudflare:reload';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reload trust proxies IPs and store in cache.';

    /**
     * Execute the console command.
     */
    public function handle(Cache $cache, Config $config): void
    {
        if (! (bool) $config->get('laravelcloudflare.enabled')) {
            return;
        }

        $proxies = LaravelCloudflare::getProxies();

        $cache->store()->forever($config->get('laravelcloudflare.cache'), $proxies);

        $this->info('Cloudflare\'s IP blocks have been reloaded.');
    }
}
