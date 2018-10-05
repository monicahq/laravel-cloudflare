<?php

namespace Monicahq\Cloudflare\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Monicahq\Cloudflare\TrustProxies;
use Symfony\Component\Console\Output\OutputInterface;

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
     * @return mixed
     */
    public function handle()
    {
        $loader = new CloudflareProxies();

        Cache::forever('cloudflare.proxies', $loader->load());

        $this->info('Cloudflare\'s IP blocks have been reloaded.', OutputInterface::VERBOSITY_VERBOSE);
    }
}
