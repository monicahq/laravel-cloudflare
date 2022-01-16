<?php

namespace Monicahq\Cloudflare\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Config\Repository;
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
     * @var string
     */
    protected $description = 'Reload trust proxies IPs and store in cache.';

    /**
     * Execute the console command.
     *
     * @param  Factory  $cache
     * @param  Repository  $config
     * @return void
     */
    public function handle(Factory $cache, Repository $config)
    {
        $proxies = LaravelCloudflare::getProxies();

        $cache->store()->forever($config->get('laravelcloudflare.cache'), $proxies);

        $this->info('Cloudflare\'s IP blocks have been reloaded.');
    }
}
