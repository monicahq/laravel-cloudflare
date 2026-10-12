# Research: Reliability & Security Hardening

Facts below were checked on 2026-10-08 against the vendored Laravel framework (`vendor/laravel/framework`) and the live Cloudflare endpoints.

## R1: Compute trusted proxies per request instead of using the static `TrustProxies::at()`

- **Finding**: The current middleware calls `parent::at(merge($alwaysTrustProxies, $cached))` on every request. `at()` writes a **static** property. In long-running workers (Octane, queue workers serving HTTP), ranges removed from the cache stay trusted for the life of the worker. Ranges trusted on an earlier request also stay trusted after the cache is flushed, which breaks FR-003 and FR-009 in spirit.
- **Decision**: Override the protected `proxies()` method so it returns, per request, the operator's proxies merged with the Cloudflare ranges, without duplicates. The operator's proxies come from `parent::proxies()` (the `$proxies` property or `at()`), or else from `config('trustedproxy.proxies')`. The middleware never calls `at()` itself. If the operator configured `'*'` or `'**'`, the parent behaviour is kept untouched, because that is an explicit operator choice.
- **Rationale**: No shared mutable state. Behaviour is identical under PHP-FPM and long-running workers. It also matches the edge case in the spec where the operator's own proxies stay trusted alongside Cloudflare's.
- **Alternatives considered**: Keep `at()` and call `flushState()` first, which still leaks between concurrent requests in coroutine runtimes. Mutating the `$proxies` instance property was also considered, but the middleware may be a singleton under Octane.

## R2: Validating an entry

- **Decision**: An entry is valid if, after `trim()`, it is either:
  - a bare IP address accepted by `filter_var($ip, FILTER_VALIDATE_IP)`, or
  - `address/prefix`, where the address passes `FILTER_VALIDATE_IP` and the prefix is a decimal integer: 0–32 for IPv4 and 0–128 for IPv6.

  The prefix-limit rule (R3) is applied next. Any other content is invalid, including `*`, hostnames, HTML, a port, or a zone id.
- **Rationale**: This uses only PHP built-ins and needs no new dependency. Symfony's `IpUtils::checkIp()` (used later for matching) tolerates malformed input by returning false, so it cannot serve as a validator.
- **Alternatives**: Validate through `IpUtils::checkIp($probe, $entry)`, which cannot tell "invalid" from "no match". A regex-only check is weaker against edge cases such as IPv4-mapped IPv6 addresses.

## R3: Prefix limits

- **Decision**: Reject IPv4 prefixes shorter than `/8` and IPv6 prefixes shorter than `/16`. A bare address counts as /32 or /128. The limits are constants (`ProxyListValidator::MIN_IPV4_PREFIX = 8`, `MIN_IPV6_PREFIX = 16`), not configuration, so they cannot be loosened by accident.
- **Evidence**: The live lists on 2026-10-08 have 15 IPv4 ranges (widest `/13`: `104.16.0.0/13`, `172.64.0.0/13`) and 7 IPv6 ranges (widest `/29`: `2a06:98c0::/29`). That leaves a wide safety margin.
- **Spec note**: The spec's assumption mentions "/12 for IPv4". The measured widest is /13. Both are well inside the limit, and the spec assumption has been corrected.

## R4: One download at a time (FR-014)

- **Decision**: Use `Cache::lock('<cache>.lock', timeout + 5)` when the store implements `Illuminate\Contracts\Cache\LockProvider`. All built-in stores do (array, file, database, redis, memcached, dynamodb, null, failover, memoized). The request that holds the lock downloads the list. Other requests wait with `block(timeout)`, re-read the cache, and continue without trusting Cloudflare if it is still empty. If the store is not a `LockProvider`, the request downloads without a lock, so availability wins.
- **Rationale**: This bounds both the number of concurrent downloads and the visitor's wait (≤ timeout, SC-003). Lock TTL = timeout + 5 s, so a crashed holder cannot block forever.
- **Alternatives**: Non-blocking `get()`, where losers immediately go untrusted. That is simpler, but it gives wrong IPs to every concurrent visitor during a cold start (hurts SC-001). An atomic flag via `Cache::add` was also considered, but it duplicates what locks already provide.

## R5: Back-off after a failed request-path download (FR-013)

- **Decision**: On failure, `Cache::put('<cache>.failed', <timestamp>, retry_after)`. While the key exists, request-path reads skip downloading. The reload command ignores the marker (operators can always force a retry) and clears it on success.
- **Rationale**: This is a cheap, cross-process signal and it expires by itself.

## R6: Cache layout and backward compatibility

- **Decision**:
  - `<cache>` (default `cloudflare.proxies`) **stays a plain list of strings**, exactly as 4.1 writes it.
  - Metadata goes to a separate key `<cache>.meta` holding `['refreshed_at' => int unix ts]`.
  - The list and its metadata are written together with `forever()`, and the list is written last only after it has been validated.
- **Rationale**: Upgrading lets 4.2 read 4.1's list. It re-validates the list and treats the missing metadata as "refreshed at upgrade" (see the Key Entities section of the spec). On first read it writes `<cache>.meta` with the current time. Downgrading lets 4.1 read the same plain list. A wrapped structure would have been read by 4.1 as a list containing garbage keys.
- **Corrupted value** (not an array, or containing non-strings, or failing validation): treated as missing (FR-015). On the request path this triggers the normal download path, subject to the lock and back-off.

## R7: HTTP hardening (FR-008, FR-011)

- **Decision**:
  - Use the injected `Illuminate\Http\Client\Factory`, not the static facade, for testability.
  - Requests use:
    - `->timeout($timeout)->connectTimeout(min(3, $timeout))`;
    - `->withOptions(['allow_redirects' => ['max' => 3, 'strict' => true, 'protocols' => ['https'], 'on_redirect' => <host check>]])`, where the host check throws if the redirect host differs from the configured host;
    - `->throw()`.
  - The configured `laravelcloudflare.url` must use the `https` scheme. Otherwise the download fails with `InvalidProxyListException` before any network call.
- **Evidence**: Today `https://www.cloudflare.com/ips-v4` and `/ips-v6` answer `200 text/plain` directly, with no redirects. The body has no trailing newline. The lists must be split on `\r?\n`, trimmed, and blank lines dropped (FR-007).
- **Implementation note (2026-10-09)**: `Http::fake()` returns redirect responses without running Guzzle's redirect middleware, so an `on_redirect` callback could not be tested. Automatic redirects are disabled (`allow_redirects => false`) and `CloudflareProxies` follows at most 3 redirects itself, only to `https://` on the configured host. The behaviour is the same, and fully covered by tests.
- **Alternatives**: Disabling redirects entirely is stricter, but a future same-host redirect by Cloudflare would break every installation. Moving to the JSON API (`api.cloudflare.com/client/v4/ips`) is out of scope per the spec.

## R8: Guarding `Cf-Connecting-Ip` (FR-001–FR-003)

- **Decision**: In `handle()`, before calling the parent, when `replace_ip` is true and the package is enabled:
  1. Load the validated Cloudflare list through `ProxyCache`. If it is empty, do nothing.
  2. Take `REMOTE_ADDR`. Replace it only if `IpUtils::checkIp(REMOTE_ADDR, $cloudflareRanges)` is true and the header, trimmed, passes `filter_var(FILTER_VALIDATE_IP)`. A comma-separated list, an address with a port, or junk all fail this check, so the header is ignored.
- **Note**: After replacement, `REMOTE_ADDR` is the visitor, which is not a trusted proxy, so `X-Forwarded-*` from the visitor are not trusted either. That is correct.
- When disabled (`enabled=false`), there is no header replacement and no Cloudflare trust (edge case in the spec). This changes the current code, which replaced the IP even when disabled.

## R9: Staleness warnings (FR-029, FR-030)

- **Decision**: `stale_after` is a number of days (default 7). On each request-path read, if `now - refreshed_at > stale_after days`, the package does `Cache::add('<cache>.stale-warned', 1, 3600)`. Only when that `add` succeeds (once per hour across processes) does it log `warning`. The list is still used. `cloudflare:view` prints `Last refreshed: <ISO-8601> (<n> days ago)` and `STALE` when over the limit.
- **Rationale**: `Cache::add` is atomic on every built-in store, which keeps log volume bounded.

## R10: Dependency vulnerability checks (FR-023)

- **Decision**: Add a new workflow `.github/workflows/audit.yml` that runs on pull requests, on pushes to `main`, and weekly (cron). It runs `composer update --prefer-dist --no-progress` then `composer audit` (it audits the installed packages; the package has no committed lock file), on PHP 8.4. Exit status ≠ 0 fails the job. Dependabot already exists for version bumps.
- **Alternatives**: Adding the step to the shared `monicahq/workflows` is outside this repository's control. Roave/SecurityAdvisories only blocks installs, not CI reporting.

## R11: Release and upgrade notes (FR-028)

- **Decision**: Use conventional commits (`fix(security): …`, `feat: …`) with no `BREAKING CHANGE` footer. semantic-release then publishes **4.2.0** (the latest tag is 4.1.0). Upgrade notes go in a new README section, "Upgrading to 4.2", and in the commit bodies that semantic-release copies into the release notes and CHANGELOG. The notes cover:
  - `Cf-Connecting-Ip` is now honoured only from Cloudflare.
  - The reload command now exits 1 on failure.
  - Invalid lists are rejected.
  - New config keys and their defaults.
  - Visitor requests fail open.
  - Header replacement no longer happens while the package is disabled.

## R12: Logging

- **Decision**: Use the injected `Psr\Log\LoggerInterface` (the application's default channel). Messages use a fixed prefix, `laravel-cloudflare:`, and context keys only from this set: `reason`, `source` (`request`|`reload`), `url` (the configured endpoint, which is public), `age_days`. Request data, headers and IPs of visitors are never logged (FR-021).
