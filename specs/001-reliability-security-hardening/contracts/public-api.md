# Contract: Public PHP API

## Unchanged (FR-027)

| Symbol | Contract |
|---|---|
| `Monicahq\Cloudflare\LaravelCloudflare::getProxies(): array` | Returns the callback's result if set, otherwise `CloudflareProxies::load()`. The result is **not** validated here; validation happens where it is stored or trusted (`ProxyCache`) |
| `Monicahq\Cloudflare\LaravelCloudflare::getProxiesUsing(?Closure): void` | Unchanged |
| `Monicahq\Cloudflare\Facades\CloudflareProxies::load(int $type = IP_VERSION_ANY): array` | Same signature |
| `CloudflareProxies::IP_VERSION_4 / IP_VERSION_6 / IP_VERSION_ANY` | Unchanged |
| `Monicahq\Cloudflare\Http\Middleware\TrustProxies` | Same class name and parent. Protected `setRemoteAddr()`, `setTrustedProxyIpAddresses()` and `setTrustedProxyCloudflare()` are kept as extension points. Their bodies change, and they no longer call `static::at()` |
| `Monicahq\Cloudflare\TrustedProxyServiceProvider` | Unchanged registration surface; adds singleton bindings |

## Changed behaviour of existing symbols

- `CloudflareProxies::load()` now:
  - returns trimmed, validated entries (IPv4 then IPv6, no duplicates);
  - throws `InvalidProxyListException` (a subclass of `UnexpectedValueException`, so existing `catch (UnexpectedValueException)` keeps working) on network error, HTTP error, timeout, non-HTTPS source, cross-host redirect, or validation failure;
  - uses the injected `Illuminate\Http\Client\Factory`, so `Http::fake()` keeps working in tests.

## New symbols

### `Monicahq\Cloudflare\Exceptions\InvalidProxyListException extends \UnexpectedValueException`

- Named constructors: `::download(string $reason, ?\Throwable $previous = null)` and `::invalidEntry(string $entry)` (the entry is truncated to 64 characters), plus `::empty()`, `::insecureSource(string $url)` and `::storage(\Throwable $previous)` (the validated list could not be written to the cache; used by `ProxyCache::refresh()` so the reload command reports a clean failure).

### `final class Monicahq\Cloudflare\ProxyListValidator`

- `public const MIN_IPV4_PREFIX = 8; public const MIN_IPV6_PREFIX = 16;`
- `public function isValidEntry(string $entry): bool`
- `public function validate(array $entries): array` returns the trimmed, non-blank, unique list, or throws `InvalidProxyListException` (whole-list reject).

### `final class Monicahq\Cloudflare\ProxyCache`

- `public function read(): ?CachedList`: never throws and never writes. It returns `null` when the list is missing, invalid or the store is unavailable. A legacy list (no `.meta`) is returned with `refreshedAt = null`.
- `public function store(array $proxies): CachedList`: validates first, then writes `.meta` (`refreshed_at = now`), the list, and forgets `.failed`. It throws `InvalidProxyListException` without writing anything if validation fails.
- `public function forRequest(): array`: request-path entry point. It does read → (if missing, not backing off) a locked download → returns the list or `[]`. It back-fills `.meta` with the current time for a legacy list, so the staleness clock starts at upgrade. It never throws, logs failures, and emits the stale warning at most once per hour.
- `public function refresh(): CachedList`: reload entry point. It downloads through `LaravelCloudflare::getProxies()`, validates, then writes. It throws `InvalidProxyListException` on any failure and leaves the cache untouched.
- `CachedList` is a small readonly value object: `proxies`, `refreshedAt` (`?int`, where `null` means legacy/unknown), `isStale(int $staleAfterDays): bool`, `countByFamily(): array{ipv4:int, ipv6:int}`.

## Log events (FR-021)

| Level | Message | Context keys |
|---|---|---|
| `error` | `laravel-cloudflare: reload failed` | `reason`, `source=reload`, `url` |
| `warning` | `laravel-cloudflare: could not load Cloudflare ranges; requests are served without trusting Cloudflare` | `reason`, `source=request`, `url`, `retry_after` |
| `warning` | `laravel-cloudflare: cache unavailable` | `reason`, `source` |
| `warning` | `laravel-cloudflare: cached Cloudflare ranges are stale` | `age_days`, `stale_after` |

No visitor IP, header or request data is ever placed in log context.
