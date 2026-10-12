<?php

namespace Monicahq\Cloudflare;

use Illuminate\Support\Carbon;

final readonly class CachedList
{
    /**
     * Create a new cached list.
     *
     * @param  list<string>  $proxies
     * @param  int|null  $refreshedAt  Unix timestamp of the last refresh, null if unknown (cached by an earlier version).
     */
    public function __construct(
        public array $proxies,
        public ?int $refreshedAt
    ) {}

    /**
     * Get the age of the list in full days.
     */
    public function ageInDays(?int $now = null): ?int
    {
        if ($this->refreshedAt === null) {
            return null;
        }

        return intdiv(max(0, ($now ?? Carbon::now()->getTimestamp()) - $this->refreshedAt), 86400);
    }

    /**
     * Determine if the list is older than the given number of days.
     */
    public function isStale(int $staleAfterDays, ?int $now = null): bool
    {
        if ($this->refreshedAt === null) {
            return false;
        }

        return ($now ?? Carbon::now()->getTimestamp()) - $this->refreshedAt > $staleAfterDays * 86400;
    }

    /**
     * Count the entries per address family.
     *
     * @return array{ipv4: int, ipv6: int}
     */
    public function countByFamily(): array
    {
        $ipv6 = count(array_filter($this->proxies, fn (string $proxy): bool => str_contains($proxy, ':')));

        return [
            'ipv4' => count($this->proxies) - $ipv6,
            'ipv6' => $ipv6,
        ];
    }
}
