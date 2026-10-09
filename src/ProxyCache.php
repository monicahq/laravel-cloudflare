<?php

namespace Monicahq\Cloudflare;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use Monicahq\Cloudflare\Exceptions\InvalidProxyListException;
use Psr\Log\LoggerInterface;
use Throwable;

final class ProxyCache
{
    /**
     * Create a new ProxyCache instance.
     */
    public function __construct(
        private CacheFactory $cache,
        private Config $config,
        private ProxyListValidator $validator,
        private LoggerInterface $logger
    ) {}

    /**
     * Get the cached list, or null if it is missing, invalid or unreadable.
     * This never writes to the cache.
     */
    public function read(): ?CachedList
    {
        try {
            return $this->lookup();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get the proxies to trust for the current request.
     * This never throws: on any failure, nothing is trusted and the failure is logged.
     *
     * @return list<string>
     */
    public function forRequest(): array
    {
        try {
            $list = $this->lookup();
        } catch (Throwable $e) {
            $this->logger->warning('laravel-cloudflare: cache unavailable', [
                'reason' => $e->getMessage(),
                'source' => 'request',
            ]);

            return [];
        }

        if ($list !== null) {
            $this->checkAge($list);

            return $list->proxies;
        }

        try {
            if ($this->repository()->has($this->key('failed'))) {
                return [];
            }

            return $this->downloadOnce();
        } catch (Throwable $e) {
            $retryAfter = max(1, (int) $this->config->get('laravelcloudflare.retry_after', 60));
            $this->markFailed($retryAfter);

            $this->logger->warning('laravel-cloudflare: could not load Cloudflare ranges; requests are served without trusting Cloudflare', [
                'reason' => $e->getMessage(),
                'source' => 'request',
                'url' => $this->config->get('laravelcloudflare.url'),
                'retry_after' => $retryAfter,
            ]);

            return [];
        }
    }

    /**
     * Download, validate and store the list, whatever the back-off state.
     * The cache is left untouched on failure.
     *
     * @throws InvalidProxyListException
     */
    public function refresh(): CachedList
    {
        try {
            $proxies = LaravelCloudflare::getProxies();
        } catch (InvalidProxyListException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw InvalidProxyListException::download($e->getMessage(), $e);
        }

        try {
            return $this->store($proxies);
        } catch (InvalidProxyListException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw InvalidProxyListException::storage($e);
        }
    }

    /**
     * Validate the list, then store it with its refresh time.
     *
     * @throws InvalidProxyListException
     */
    public function store(array $proxies): CachedList
    {
        $valid = $this->validator->validate($proxies);
        $now = Carbon::now()->getTimestamp();

        $repository = $this->repository();
        $repository->forever($this->key('meta'), ['refreshed_at' => $now]);
        $repository->forever($this->key(), $valid);
        $repository->forget($this->key('failed'));

        return new CachedList($valid, $now);
    }

    /**
     * Download the list, allowing only one download at a time.
     * When another download is running, wait for it at most the download timeout.
     *
     * @return list<string>
     *
     * @throws Throwable
     */
    private function downloadOnce(): array
    {
        $store = $this->repository()->getStore();

        if (! $store instanceof LockProvider) {
            return $this->download();
        }

        $timeout = max(1, (int) $this->config->get('laravelcloudflare.timeout', 5));

        try {
            return $store->lock($this->key('lock'), $timeout + 5)
                ->block($timeout, fn (): array => $this->lookup()->proxies ?? $this->download());
        } catch (LockTimeoutException) {
            return $this->lookup()->proxies ?? [];
        }
    }

    /**
     * Download, validate and store the list.
     *
     * @return list<string>
     *
     * @throws Throwable
     */
    private function download(): array
    {
        return $this->store(LaravelCloudflare::getProxies())->proxies;
    }

    /**
     * Start the age of a list cached by an earlier version, or warn (at most once per hour) about a stale list.
     */
    private function checkAge(CachedList $list): void
    {
        try {
            $repository = $this->repository();

            if ($list->refreshedAt === null) {
                $repository->forever($this->key('meta'), ['refreshed_at' => Carbon::now()->getTimestamp()]);

                return;
            }

            $staleAfter = max(1, (int) $this->config->get('laravelcloudflare.stale_after', 7));

            if ($list->isStale($staleAfter) && $repository->add($this->key('stale-warned'), 1, 3600)) {
                $this->logger->warning('laravel-cloudflare: cached Cloudflare ranges are stale', [
                    'age_days' => $list->ageInDays(),
                    'stale_after' => $staleAfter,
                ]);
            }
        } catch (Throwable) {
            // The list is still used: its age is informational only.
        }
    }

    /**
     * Remember a failed download, to wait before retrying.
     */
    private function markFailed(int $retryAfter): void
    {
        try {
            $this->repository()->put($this->key('failed'), Carbon::now()->getTimestamp(), $retryAfter);
        } catch (Throwable) {
            // The cache is unavailable: nothing to remember.
        }
    }

    /**
     * Read the cached list.
     *
     * @throws Throwable when the cache store is unavailable
     */
    private function lookup(): ?CachedList
    {
        $repository = $this->repository();
        $proxies = $repository->get($this->key());

        if (! is_array($proxies)) {
            return null;
        }

        try {
            $proxies = $this->validator->validate($proxies);
        } catch (InvalidProxyListException) {
            return null;
        }

        $meta = $repository->get($this->key('meta'));
        $refreshedAt = is_array($meta) && is_int($meta['refreshed_at'] ?? null) ? $meta['refreshed_at'] : null;

        return new CachedList($proxies, $refreshedAt);
    }

    /**
     * Get the cache key, optionally suffixed.
     */
    private function key(?string $suffix = null): string
    {
        $key = (string) $this->config->get('laravelcloudflare.cache', 'cloudflare.proxies');

        return $suffix === null ? $key : "{$key}.{$suffix}";
    }

    /**
     * Get the default cache store.
     */
    private function repository(): CacheRepository
    {
        return $this->cache->store();
    }
}
