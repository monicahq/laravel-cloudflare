<?php

namespace Monicahq\Cloudflare;

use Monicahq\Cloudflare\Exceptions\InvalidProxyListException;

final class ProxyListValidator
{
    /**
     * Shortest accepted IPv4 prefix: anything wider would trust too many addresses.
     *
     * @var int
     */
    public const MIN_IPV4_PREFIX = 8;

    /**
     * Shortest accepted IPv6 prefix: anything wider would trust too many addresses.
     *
     * @var int
     */
    public const MIN_IPV6_PREFIX = 16;

    /**
     * Check that an entry is a single IP address or a reasonably sized network range.
     */
    public function isValidEntry(string $entry): bool
    {
        $parts = explode('/', trim($entry));
        $address = $parts[0];
        $prefix = $parts[1] ?? null;

        if (count($parts) > 2 || filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if ($prefix === null) {
            return true;
        }

        if (preg_match('/^\d{1,3}$/', $prefix) !== 1) {
            return false;
        }

        $length = (int) $prefix;

        return str_contains($address, ':')
            ? $length >= self::MIN_IPV6_PREFIX && $length <= 128
            : $length >= self::MIN_IPV4_PREFIX && $length <= 32;
    }

    /**
     * Validate a whole list: any invalid entry rejects the list.
     *
     * @return list<string>
     *
     * @throws InvalidProxyListException
     */
    public function validate(array $entries): array
    {
        $valid = [];

        foreach ($entries as $entry) {
            if (! is_string($entry)) {
                throw InvalidProxyListException::invalidEntry(get_debug_type($entry));
            }

            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            if (! $this->isValidEntry($entry)) {
                throw InvalidProxyListException::invalidEntry($entry);
            }

            $valid[] = $entry;
        }

        if ($valid === []) {
            throw InvalidProxyListException::empty();
        }

        return array_values(array_unique($valid));
    }
}
