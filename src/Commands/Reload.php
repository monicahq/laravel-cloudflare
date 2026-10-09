<?php

namespace Monicahq\Cloudflare\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Monicahq\Cloudflare\Exceptions\InvalidProxyListException;
use Monicahq\Cloudflare\ProxyCache;
use Psr\Log\LoggerInterface;

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
    public function handle(Config $config, ProxyCache $proxyCache, LoggerInterface $logger): int
    {
        if (! (bool) $config->get('laravelcloudflare.enabled')) {
            $this->info('Laravel Cloudflare is disabled; nothing was reloaded.');

            return self::SUCCESS;
        }

        try {
            $list = $proxyCache->refresh();
        } catch (InvalidProxyListException $e) {
            $logger->error('laravel-cloudflare: reload failed', [
                'reason' => $e->getMessage(),
                'source' => 'reload',
                'url' => $config->get('laravelcloudflare.url'),
            ]);

            $this->error("Failed to reload Cloudflare's IP blocks: {$e->getMessage()}. The previously cached list was kept.");

            return self::FAILURE;
        }

        ['ipv4' => $ipv4, 'ipv6' => $ipv6] = $list->countByFamily();

        $this->info("Cloudflare's IP blocks have been reloaded: {$ipv4} IPv4 and {$ipv6} IPv6 ranges.");

        return self::SUCCESS;
    }
}
