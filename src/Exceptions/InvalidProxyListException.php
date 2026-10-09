<?php

namespace Monicahq\Cloudflare\Exceptions;

use Illuminate\Support\Str;
use Throwable;
use UnexpectedValueException;

final class InvalidProxyListException extends UnexpectedValueException
{
    /**
     * The download of the proxy list failed.
     */
    public static function download(string $reason, ?Throwable $previous = null): self
    {
        return new self("Failed to load trust proxies from Cloudflare server: {$reason}", 1, $previous);
    }

    /**
     * The proxy list contains an invalid entry.
     */
    public static function invalidEntry(string $entry): self
    {
        return new self('Invalid proxy entry "'.Str::limit($entry, 64).'"', 2);
    }

    /**
     * The proxy list is empty.
     */
    public static function empty(): self
    {
        return new self('The proxy list is empty', 3);
    }

    /**
     * The proxy list could not be stored in the cache.
     */
    public static function storage(Throwable $previous): self
    {
        return new self("Failed to store the proxy list in the cache: {$previous->getMessage()}", 5, $previous);
    }

    /**
     * The proxy list source does not use https.
     */
    public static function insecureSource(string $url): self
    {
        return new self("The Cloudflare source must use https:// (got {$url})", 4);
    }
}
