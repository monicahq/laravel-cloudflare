<?php

namespace Monicahq\Cloudflare\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Monicahq\Cloudflare\ProxyCache;

final class View extends Command
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
    public function handle(ProxyCache $proxyCache, Config $config): int
    {
        $list = $proxyCache->read();

        if ($list === null) {
            $this->warn('No Cloudflare IP ranges are cached. Run `php artisan cloudflare:reload` to load them.');

            return self::SUCCESS;
        }

        $this->table(['Address'], array_map(fn (string $value): array => [$value], $list->proxies));

        $age = $list->ageInDays();

        if ($list->refreshedAt === null || $age === null) {
            $this->line('Last refreshed: unknown (cached by an earlier version)');
        } else {
            $this->line('Last refreshed: '.gmdate('c', $list->refreshedAt)." ({$age} days ago)");
        }

        $staleAfter = max(1, (int) $config->get('laravelcloudflare.stale_after', 7));

        if ($list->isStale($staleAfter)) {
            $this->warn("The cached list is STALE (older than {$staleAfter} days). Run `php artisan cloudflare:reload` and check your scheduler.");
        }

        return self::SUCCESS;
    }
}
