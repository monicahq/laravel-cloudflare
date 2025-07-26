<?php

namespace Monicahq\Cloudflare\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Factory as Cache;
use Illuminate\Contracts\Config\Repository as Config;

class View extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cloudflare:view';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'View list of trust proxies IPs stored in cache.';

    /**
     * Execute the console command.
     */
    public function handle(Cache $cache, Config $config): void
    {
        $proxies = $cache->store()->get($config->get('laravelcloudflare.cache'), []);

        $rows = array_map(fn ($value): array => [$value], $proxies);

        $this->table(['Address'], $rows);
    }
}
