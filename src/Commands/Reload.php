<?php

namespace Monicahq\Cloudflare\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Factory as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Monicahq\Cloudflare\LaravelCloudflare;

class Reload extends Command
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
     * @var string|null
     */
    protected $description = 'Reload trust proxies IPs and store in cache.';

    /**
     * Execute the console command.
     *
     * @param  \Illuminate\Contracts\Cache\Factory  $cache
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @return void
     */
    public function handle(Cache $cache, Config $config)
    {
        if (! (bool) $config->get('laravelcloudflare.enabled')) {
            return;
        }

        $proxies = LaravelCloudflare::getProxies();

        $cache->store()->forever($config->get('laravelcloudflare.cache'), $proxies);

        $this->info('Cloudflare\'s IP blocks have been reloaded.');
    }
}
