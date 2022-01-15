<?php

namespace Monicahq\Cloudflare\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Config\Repository;

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
     *
     * @param  Factory  $cache
     * @param  Repository  $config
     * @return void
     */
    public function handle(Factory $cache, Repository $config)
    {
        $proxies = $cache->store()->get($config->get('laravelcloudflare.cache'), []);

        $rows = array_map(function ($value): array {
            return [
                $value,
            ];
        }, $proxies);

        $this->table([
            'Address',
        ], $rows);
    }
}
