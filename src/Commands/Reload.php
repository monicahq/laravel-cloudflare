<?php

namespace Monicahq\Cloudflare\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Monicahq\Cloudflare\CloudflareProxies;

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
     * @return void
     */
    public function handle()
    {
        /** @var CloudflareProxies */
        $loader = $this->laravel->make(CloudflareProxies::class);

        Cache::forever($this->laravel->make('config')->get('laravelcloudflare.cache'), $loader->load());

        $this->info('Cloudflare\'s IP blocks have been reloaded.');
    }
}
