<?php

namespace Monicahq\Cloudflare\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array load(int $type = \Monicahq\Cloudflare\CloudflareProxies::IP_VERSION_ANY)
 *
 * @see \Monicahq\Cloudflare\CloudflareProxies
 */
final class CloudflareProxies extends Facade
{
    /**
     * Get the registered name of the component.
     */
    #[\Override]
    protected static function getFacadeAccessor(): string
    {
        return \Monicahq\Cloudflare\CloudflareProxies::class;
    }
}
