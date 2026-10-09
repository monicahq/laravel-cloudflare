---

description: "Task list for Reliability & Security Hardening"
---

# Tasks: Reliability & Security Hardening

**Input**: Design documents from `specs/001-reliability-security-hardening/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/](contracts/), [quickstart.md](quickstart.md)

**Tests**: REQUIRED. The spec mandates them (FR-022, User Story 5, SC-006). Within each story, write the tests first and confirm they fail before implementing.

**Organization**: Tasks are grouped by user story, so each story can be implemented and tested on its own.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on unfinished tasks)
- **[Story]**: Which user story the task belongs to (US1–US5)
- Paths are relative to the repository root (single Composer library: `src/`, `tests/`, `config/`)

## Conventions used by every task

- Namespace `Monicahq\Cloudflare\…`. New classes are `final` and fully typed. Add no new `@phpstan-ignore` or `@psalm-suppress`.
- `<cache>` means `config('laravelcloudflare.cache')` (default `cloudflare.proxies`). Derived keys: `<cache>.meta`, `<cache>.failed`, `<cache>.lock`, `<cache>.stale-warned` ([data-model.md](data-model.md)).
- Log messages and context keys must match [contracts/public-api.md § Log events](contracts/public-api.md) exactly. Never log a visitor IP, header or request data.
- Command output must match [contracts/commands.md](contracts/commands.md).
- Tests extend `Monicahq\Cloudflare\Tests\FeatureTestCase`, use the array cache store, `Http::fake()`, `Log::spy()` and `$this->travel()`. Use the PHPUnit `#[Test]` attribute style already in the repository.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Configuration, the exception type and test fixtures that every story uses.

- [X] T001 Run the baseline checks `vendor/bin/phpunit`, `vendor/bin/phpstan analyse`, `vendor/bin/psalm` and `vendor/bin/pint --test` (configured by `phpunit.xml`, `phpstan.neon`, `psalm.xml`) and record which pass. Note that `it_loads_real_mode` in `tests/Unit/CloudflareProxiesTest.php` needs live network access.
- [X] T002 [P] Add three keys to `config/laravelcloudflare.php`, each with a docblock in the existing style:
  - `'timeout' => (int) env('LARAVEL_CLOUDFLARE_TIMEOUT', 5)`: seconds, maximum duration of one download.
  - `'retry_after' => (int) env('LARAVEL_CLOUDFLARE_RETRY_AFTER', 60)`: seconds of back-off after a failed download during a visitor request.
  - `'stale_after' => (int) env('LARAVEL_CLOUDFLARE_STALE_AFTER', 7)`: days after which the cached list is reported stale.

  Also change the `url` docblock to state that it "must use https://". Follow [contracts/configuration.md](contracts/configuration.md).
- [X] T003 [P] Create `src/Exceptions/InvalidProxyListException.php`: a `final class InvalidProxyListException extends \UnexpectedValueException` with these named constructors:
  - `download(string $reason, ?\Throwable $previous = null): self`, message `"Failed to load trust proxies from Cloudflare server: {reason}"`.
  - `invalidEntry(string $entry): self`, message `"Invalid proxy entry \"{entry}\""`, with the entry truncated to 64 characters.
  - `empty(): self`, message `"The proxy list is empty"`.
  - `insecureSource(string $url): self`, message `"The Cloudflare source must use https:// (got {url})"`.
- [X] T004 [P] Add shared fixtures to `tests/FeatureTestCase.php`:
  - Constants `CF_IPV4 = "173.245.48.0/20\n104.16.0.0/13"` and `CF_IPV6 = "2400:cb00::/32\n2a06:98c0::/29"`.
  - A helper `fakeCloudflare(string $v4 = self::CF_IPV4, string $v6 = self::CF_IPV6, int $status = 200): void` that sets `laravelcloudflare.url` to `https://fake` and calls `Http::fake()` for `https://fake/ips-v4` and `https://fake/ips-v6`.
  - A `tearDown()` that calls `LaravelCloudflare::getProxiesUsing(null)` and `\Illuminate\Http\Middleware\TrustProxies::flushState()` before `parent::tearDown()`.
- [X] T005 [P] Tag `it_loads_real_mode` in `tests/Unit/CloudflareProxiesTest.php` with `#[Group('network')]`. In `phpunit.xml`, add `<groups><exclude><group>network</group></exclude></groups>` so the default suite needs no network. It stays runnable with `vendor/bin/phpunit --group network`.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The validator and the cache service that US1–US4 all rely on.

**⚠️ CRITICAL**: No user-story work can start until this phase is complete.

### Tests (write first, must fail)

- [X] T006 [P] Create `tests/Unit/ProxyListValidatorTest.php`, covering FR-004 to FR-007 (R2, R3):
  - `isValidEntry()` **accepts**:
    - `173.245.48.0/20`, `104.16.0.0/13`, `1.2.3.4`, `10.0.0.0/8`;
    - `2400:cb00::/32`, `2a06:98c0::/29`, `2001:db8::1`, `2001::/16`;
    - ` 173.245.48.0/20 ` (surrounding whitespace).
  - `isValidEntry()` **rejects**: `*`, `0.0.0.0/0`, `::/0`, `10.0.0.0/7`, `2001::/15`, `1.2.3.4:443`, `<html>`, `fe80::1%eth0`, `1.2.3.4/33`, `2001:db8::/129`, `1.2.3.4/abc`, `1.2.3.4/`, `/24`, `1.2.3.4/24/1`, and the empty string.
  - `validate()` behaviour:
    - It trims, drops blank lines, and removes duplicates while keeping order.
    - It throws `InvalidProxyListException` for the **whole list** when any entry is invalid, including non-string elements such as `123` or `null`. The message contains the first offending entry, truncated to 64 characters.
    - It throws `InvalidProxyListException::empty()` when nothing remains.
- [X] T007 [P] Create `tests/Unit/ProxyCacheTest.php` with the base `read()`/`store()` cases:
  1. `store(['173.245.48.0/20','2400:cb00::/32'])` writes `<cache>` as a **plain list** and `<cache>.meta` as `['refreshed_at' => now]`, then forgets `<cache>.failed`.
  2. `store(['*'])` throws and writes nothing, so a previously cached list stays unchanged.
  3. `read()` returns a `CachedList` with the same proxies and `refreshedAt`.
  4. `read()` returns `null` when the key is missing.
  5. `read()` returns `null` when `<cache>` is a string, an array with ints, or a list containing `0.0.0.0/0` (FR-015).
  6. `read()` returns `null` and logs `warning` `laravel-cloudflare: cache unavailable` when the cache repository throws a `RuntimeException`; mock `Illuminate\Contracts\Cache\Factory` (FR-016).
  7. A legacy list (`<cache>` set, no `.meta`) is returned with `refreshedAt === null`, and `read()` does **not** write `.meta`.

### Implementation

- [X] T008 Implement `src/ProxyListValidator.php` (`final class ProxyListValidator`, R2/R3):
  - Constants: `public const MIN_IPV4_PREFIX = 8; public const MIN_IPV6_PREFIX = 16;`.
  - `isValidEntry(string $entry): bool`:
    - `trim`, then split on `/` into at most 2 parts; a third part means invalid.
    - The address must pass `filter_var($addr, FILTER_VALIDATE_IP)`.
    - The prefix, when present, must match `/^\d{1,3}$/` and be `≤ 32` for IPv4 or `≤ 128` for IPv6.
    - A bare address counts as 32 or 128.
    - Reject if the prefix is below the family minimum.
  - `validate(array $entries): array`, returning `list<string>`:
    - Any non-string element throws `invalidEntry`.
    - Trim each entry, drop `''`, validate each in order, throwing `invalidEntry($first)`.
    - `array_values(array_unique())`; throw `empty()` if the result is empty.

  Makes T006 pass.
- [X] T009 [P] Create `src/CachedList.php` (`final readonly class CachedList`):
  - Constructor `(public array $proxies, public ?int $refreshedAt)`.
  - `ageInDays(?int $now = null): ?int` returns `null` when `refreshedAt` is null, otherwise `intdiv(now - refreshedAt, 86400)`.
  - `isStale(int $staleAfterDays, ?int $now = null): bool` returns false when `refreshedAt` is null, otherwise `(now - refreshedAt) > staleAfterDays * 86400`.
  - `countByFamily(): array{ipv4:int, ipv6:int}` counts an entry as IPv6 when its address part contains `:`.
- [X] T010 Implement `src/ProxyCache.php` core (`final class ProxyCache`), depending on T008 and T009:
  - Constructor `(Illuminate\Contracts\Cache\Factory $cache, Illuminate\Contracts\Config\Repository $config, ProxyListValidator $validator, Psr\Log\LoggerInterface $logger)`.
  - Private key helpers for `<cache>`, `.meta`, `.failed`, `.lock` and `.stale-warned`, plus a private `store()` accessor returning `$this->cache->store()`.
  - `read(): ?CachedList`:
    - Wrap all cache access in `try/catch (\Throwable)`. On a catch, log `warning` `laravel-cloudflare: cache unavailable` with `['reason' => $e->getMessage(), 'source' => 'request']` and return `null`.
    - Return `null` when the value is not an array or `validator->validate()` throws.
    - `refreshedAt` is `meta['refreshed_at']` only if it is an int, otherwise `null`.
    - Never write.
  - `store(array $proxies): CachedList`:
    - Run `$valid = validator->validate($proxies)` **before** any write.
    - Then `forever(.meta, ['refreshed_at' => time()])`, `forever(<cache>, $valid)`, `forget(.failed)`.
    - Return a new `CachedList`.

  Makes T007 pass.
- [X] T011 Register `ProxyListValidator::class` and `ProxyCache::class` as singletons in `register()` of `src/TrustedProxyServiceProvider.php`. Resolve `ProxyCache`'s `LoggerInterface` from the container's `log` binding. Keep the existing `Facades\CloudflareProxies::class` binding and command registration unchanged.

**Checkpoint**: The validator and the cache service are tested and green. User stories can start.

---

## Phase 3: User Story 1 - Visitor IP cannot be spoofed (Priority: P1) 🎯 MVP

**Goal**: `Cf-Connecting-Ip` and forwarded headers are believed only from Cloudflare addresses. The package never mutates `TrustProxies` static state (R1, R8).

**Independent Test**: Run `vendor/bin/phpunit --filter TrustProxiesTest`. Spoofed headers from outside Cloudflare's ranges are ignored, and genuine Cloudflare requests yield the visitor IP (SC-001).

### Tests for User Story 1 (write first, must fail)

- [X] T012 [US1] Rewrite `tests/Unit/Http/Middleware/TrustProxiesTest.php`:
  - Remove every `Cache::shouldReceive('rememberForever')` mock and every `'expect'` entry. Seed real data with `$this->app->make(ProxyCache::class)->store([...])` using the `CF_IPV4`/`CF_IPV6` entries.
  - Cases:
    - (a) trusted proxies equal the cached ranges;
    - (b) US1-1: `replace_ip=true`, `REMOTE_ADDR=203.0.113.9`, header `198.51.100.7` gives `ip() === '203.0.113.9'`;
    - (c) US1-2: `REMOTE_ADDR=173.245.48.1` with the same header gives `ip() === '198.51.100.7'`;
    - (d) US1-3: the headers `1.2.3.4, 5.6.7.8`, `1.2.3.4:80`, `junk` and `''` are ignored; ` 198.51.100.7 ` (padded) and `2001:db8::1` are accepted;
    - (e) US1-4: empty cache plus `fakeCloudflare(status: 500)`, `REMOTE_ADDR=173.245.48.1`, `X-Forwarded-For: 198.51.100.7` gives `ip() === '173.245.48.1'`, `$next` is still called and returns its response;
    - (f) `enabled=false` with `replace_ip=true` does no replacement, sends no HTTP (`Http::assertNothingSent()`), and gives `getTrustedProxies() === []`;
    - (g) operator proxies: `TrustProxies::at('10.0.0.1')` plus the cached list means both are trusted without duplicates, and after two requests the protected static `$alwaysTrustProxies` (read via reflection) still equals `['10.0.0.1']`;
    - (h) `config(['trustedproxy.proxies' => '*'])` keeps the parent behaviour, trusting the calling IP.

### Implementation for User Story 1

- [X] T013 [US1] Add a minimal `forRequest(): array` to `src/ProxyCache.php`:
  - Return `read()?->proxies` when present.
  - Otherwise `try { return $this->store(LaravelCloudflare::getProxies())->proxies; } catch (\Throwable $e)`. On a catch, log `warning` `laravel-cloudflare: could not load Cloudflare ranges; requests are served without trusting Cloudflare` with `['reason' => …, 'source' => 'request', 'url' => config('laravelcloudflare.url')]` and return `[]`.
  - It never throws. Locking and back-off come in US3.
- [X] T014 [US1] Rewrite `handle()` in `src/Http/Middleware/TrustProxies.php`:
  - If `! (bool) Config::get('laravelcloudflare.enabled')`, return `parent::handle($request, $next)`, with no replacement and no cache access.
  - Otherwise compute `$ranges = app(ProxyCache::class)->forRequest()` **once** and store it in `$request->attributes->set('laravelcloudflare.ranges', $ranges)`. Never put it in a static or instance property.
  - Then, if `replace_ip === true`, call `$this->setRemoteAddr($request)`.
  - Then `return parent::handle($request, $next)`.
- [X] T015 [US1] Rewrite `setRemoteAddr(Request $request): void` in `src/Http/Middleware/TrustProxies.php`, keeping the signature as an extension point:
  - Read `$ranges` from the request attribute.
  - Return early if it is empty.
  - Return early if `! IpUtils::checkIp((string) $request->server->get('REMOTE_ADDR'), $ranges)`.
  - Set `$ip = trim((string) $request->header('Cf-Connecting-Ip'))`. Only if `filter_var($ip, FILTER_VALIDATE_IP) !== false`, call `$request->server->set('REMOTE_ADDR', $ip)` (FR-001, FR-002).
- [X] T016 [US1] Rewrite `setTrustedProxyIpAddresses()` and `setTrustedProxyCloudflare()` in `src/Http/Middleware/TrustProxies.php` (R1):
  - `setTrustedProxyIpAddresses()`: when enabled, delegate to `setTrustedProxyCloudflare($request)`; otherwise call the parent.
  - `setTrustedProxyCloudflare()`:
    - `$operator = $this->proxies() ?: config('trustedproxy.proxies')`.
    - If `$operator` is `'*'` or `'**'`, call `parent::setTrustedProxyIpAddresses($request)` and return.
    - Normalise a string to an array with `array_map('trim', explode(',', …))`, and treat null as `[]`.
    - Merge with the request-attribute ranges (fall back to `ProxyCache::forRequest()` if the attribute is absent), using `array_values(array_unique())`.
    - If the merged list is empty, call `parent::setTrustedProxyIpAddresses($request)`; otherwise call `$this->setTrustedProxyIpAddressesToSpecificIps($request, $merged)`.
    - **Never** call `static::at()` or `parent::at()`.

  Makes T012 pass.

**Checkpoint**: Spoofing is closed. MVP deliverable.

---

## Phase 4: User Story 2 - Only valid Cloudflare ranges are ever trusted (Priority: P1)

**Goal**: The downloaded list is HTTPS-only, follows same-host redirects only, and is validated per family. Callback output is validated too (FR-004 to FR-008, FR-010).

**Independent Test**: Run `vendor/bin/phpunit --filter 'CloudflareProxiesTest|ProxyCacheTest'`. Malformed, HTML or catch-all downloads and invalid callback output never reach the cache (SC-002).

### Tests for User Story 2 (write first, must fail)

- [X] T017 [P] [US2] Extend `tests/Unit/CloudflareProxiesTest.php`:
  - Expect `InvalidProxyListException` everywhere, and assert it is `instanceof \UnexpectedValueException` for backward compatibility.
  - Cases:
    - a body with `\r\n` line endings, surrounding spaces, blank lines and no trailing newline returns the trimmed list;
    - an HTML body (`<html><body>Error</body></html>`) throws;
    - a body containing `0.0.0.0/0` throws;
    - IPv4 OK with IPv6 returning 500 throws (FR-010);
    - an empty IPv6 body throws;
    - an entry present in both bodies appears once;
    - `url = 'http://fake'` throws with `Http::assertNothingSent()`;
    - `load(0)` returns `[]` with no request sent;
    - a 302 to `https://evil.example/ips-v4` throws, while a 302 to `https://fake/ips-v4/` (same host) is followed. If `Http::fake()` cannot drive Guzzle redirects, unit-test the `on_redirect` host-check closure directly.
  - Update the existing tests `it_loads_ipv4` and `it_loads_ipv6` to keep passing: `0.0.0.0/20` and `::1/32` remain valid.
- [X] T018 [P] [US2] Add callback-validation cases to `tests/Unit/ProxyCacheTest.php`:
  - `LaravelCloudflare::getProxiesUsing(fn () => ['*'])`, or `['0.0.0.0/0']`, makes `forRequest()` return `[]`, writes nothing to `<cache>`, and logs the request warning.
  - A valid callback list is cached and returned.

### Implementation for User Story 2

- [X] T019 [US2] Harden `src/CloudflareProxies.php` (R7):
  - Constructor: add a third parameter `?ProxyListValidator $validator = null`, stored as `$validator ?? new ProxyListValidator()`.
  - Use the injected `$this->http`, not the `Http` facade. Resolve `CloudflareProxies` in tests only after `Http::fake()`.
  - `load()`: return `[]` immediately when `$type` selects no family. Otherwise collect the IPv4 list, then the IPv6 list, and return `array_values(array_unique(array_merge(...)))`.
  - `retrieve(string $name)`:
    - Build the URL as today.
    - If `parse_url($url, PHP_URL_SCHEME) !== 'https'`, throw `InvalidProxyListException::insecureSource($url)`.
    - Request with `$this->http->withOptions(['allow_redirects' => ['max' => 3, 'strict' => true, 'protocols' => ['https'], 'on_redirect' => <closure throwing if the redirect URI host !== configured host>]])->get($url)->throw()`.
    - Wrap any `\Throwable` that is not already an `InvalidProxyListException` into `InvalidProxyListException::download($e->getMessage(), $e)`.
    - Parse with `preg_split('/\r?\n/', $body)` and return `$this->validator->validate($lines)`.
  - Remove the `@phpstan-ignore` and `@psalm-suppress` comments that are no longer needed.

  Makes T017 and T018 pass.

**Checkpoint**: The trusted set can only ever contain valid, bounded ranges.

---

## Phase 5: User Story 3 - The application stays up when Cloudflare's list cannot be fetched (Priority: P2)

**Goal**: Bounded download time, back-off, a single download at a time, and a reload that never overwrites a good list (FR-009 to FR-016).

**Independent Test**: Run `vendor/bin/phpunit --filter 'ProxyCacheTest|CloudflareProxiesTest'`. With the source down, slow or invalid, requests are served, retries are spaced, and a good list survives a bad reload (SC-003, SC-004).

### Tests for User Story 3 (write first, must fail)

- [X] T020 [P] [US3] Add resilience cases to `tests/Unit/ProxyCacheTest.php`:
  1. **Back-off**: with `fakeCloudflare(status: 500)` and `retry_after=60`, two `forRequest()` calls send only the first attempt's requests (`Http::assertSentCount(1)`, because IPv4 fails first). `<cache>.failed` exists. After `$this->travel(61)->seconds()`, a new attempt is made.
  2. **Lock held**: acquire `Cache::lock('<cache>.lock', 10)->get()` in the test and set `timeout=1`. `forRequest()` sends no HTTP and returns `[]` within about 1 s.
  3. **Lock released mid-wait**: a cached list written by another holder is returned. Simulate it by storing the list before calling.
  4. **`refresh()` with a good list already cached**: when the source returns 500, or the body is `0.0.0.0/0`, or the callback throws, it throws `InvalidProxyListException`, and `<cache>` and `<cache>.meta` are byte-for-byte unchanged.
  5. **`refresh()` while backing off**: it ignores `<cache>.failed`, downloads, succeeds, and clears `.failed`.
- [X] T021 [P] [US3] Add timeout cases to `tests/Unit/CloudflareProxiesTest.php`:
  - With `timeout=2`, use a `Http::fake(function ($request, $options) { … })` callback to assert `$options['timeout'] === 2` and `$options['connect_timeout'] <= 2`.
  - With `timeout=0`, the option is clamped to `1`.
  - A `ConnectionException` thrown from the fake becomes `InvalidProxyListException`.

### Implementation for User Story 3

- [X] T022 [US3] Apply the download timeout in `retrieve()` of `src/CloudflareProxies.php`, after T019 (same file): `$timeout = max(1, (int) $this->config->get('laravelcloudflare.timeout', 5))`, then chain `->timeout($timeout)->connectTimeout(min(3, $timeout))` before `withOptions(...)`. Makes T021 pass.
- [X] T023 [US3] Add `refresh(): CachedList` to `src/ProxyCache.php`:
  - `try { $proxies = LaravelCloudflare::getProxies(); } catch (InvalidProxyListException $e) { throw $e; } catch (\Throwable $e) { throw InvalidProxyListException::download($e->getMessage(), $e); }`
  - `return $this->store($proxies);`. Validation happens before any write, so a failure leaves the cache untouched.
  - It ignores `.failed`; `store()` already clears it.
- [X] T024 [US3] Harden `forRequest()` in `src/ProxyCache.php` (R4, R5):
  - Return the cached list when `read()` succeeds.
  - Return `[]` when `.failed` is present (wrap the check in try/catch, treating an exception as "not failed").
  - Otherwise, if `$this->store()->getStore() instanceof Illuminate\Contracts\Cache\LockProvider`:
    - `$this->store()->lock('<cache>.lock', $timeout + 5)->block($timeout, fn () => $this->read()?->proxies ?? $this->downloadAndStore())`.
    - On `Illuminate\Contracts\Cache\LockTimeoutException`, return `$this->read()?->proxies ?? []`.
  - Without a lock provider, call `downloadAndStore()` directly.
  - Private `downloadAndStore(): array`: `return $this->store(LaravelCloudflare::getProxies())->proxies;`.
  - Wrap the whole thing in `catch (\Throwable)`. On a catch:
    - `put('<cache>.failed', time(), max(1, retry_after))`, itself in try/catch;
    - log the request warning with context `reason`, `source=request`, `url`, `retry_after`;
    - return `[]`.

  Makes T020 pass without breaking T012 or T018.

**Checkpoint**: A Cloudflare outage or a bad list cannot take the site down or erase a good list.

---

## Phase 6: User Story 4 - Operators can see and act on failures (Priority: P2)

**Goal**: Meaningful exit codes, messages, logs, list age and stale warnings (FR-017 to FR-021, FR-029, FR-030).

**Independent Test**: Run `vendor/bin/phpunit --filter 'ReloadTest|ViewTest|ProxyCacheTest'`. Every failure path yields exit code 1 or a log entry, and the view command reports empty, legacy and stale states (SC-005).

**Depends on**: T023 (`ProxyCache::refresh()`) from US3 for the reload command.

### Tests for User Story 4 (write first, must fail)

- [X] T025 [P] [US4] Rewrite `tests/Unit/Commands/ReloadTest.php`:
  - Replace `'expect'` with valid ranges; the facade mock returns `['173.245.48.0/20', '2400:cb00::/32']`.
  - Cases:
    - success via `fakeCloudflare()` prints `Cloudflare's IP blocks have been reloaded: 2 IPv4 and 2 IPv6 ranges.`, exits `0`, and writes `<cache>.meta`;
    - a source returning 500 with a good list pre-stored exits `1`, prints `Failed to reload Cloudflare's IP blocks: … The previously cached list was kept.`, leaves the cache unchanged, and `Log::spy()` sees `error('laravel-cloudflare: reload failed', ['reason'=>…, 'source'=>'reload', 'url'=>'https://fake'])`;
    - a body of `0.0.0.0/0` exits `1`;
    - a callback that throws exits `1`;
    - `enabled=false` prints `Laravel Cloudflare is disabled; nothing was reloaded.`, exits `0`, sends no HTTP and caches nothing.
- [X] T026 [P] [US4] Rewrite `tests/Unit/Commands/ViewTest.php`:
  - A stored list shows the table `['Address']` with one row per range, followed by `Last refreshed: {gmdate('c')} (0 days ago)`.
  - After `$this->travel(8)->days()`, the output also contains `STALE (older than 7 days)`.
  - A legacy list (no `.meta`) prints `Last refreshed: unknown (cached by an earlier version)`, and `.meta` is still absent afterwards because `view` is read-only.
  - An empty cache, or a cached value of `'garbage'`, prints `No Cloudflare IP ranges are cached. Run \`php artisan cloudflare:reload\` to load them.` and exits `0`.
- [X] T027 [P] [US4] Add staleness cases to `tests/Unit/ProxyCacheTest.php`:
  - With a list stored and `$this->travel(8)->days()`, three `forRequest()` calls still return the list and log `warning('laravel-cloudflare: cached Cloudflare ranges are stale', ['age_days' => 8, 'stale_after' => 7])` exactly once.
  - After `$this->travel(61)->minutes()`, it is logged once more.
  - A legacy list read through `forRequest()` back-fills `.meta` with the current time and logs no stale warning.

### Implementation for User Story 4

- [X] T028 [US4] Rewrite `src/Commands/Reload.php` to `handle(Config $config, ProxyCache $proxyCache, LoggerInterface $logger): int`:
  - Disabled: `$this->info('Laravel Cloudflare is disabled; nothing was reloaded.')` and return `self::SUCCESS`.
  - Otherwise `try { $list = $proxyCache->refresh(); }`.
    - On success: `['ipv4' => $n4, 'ipv6' => $n6] = $list->countByFamily()`, print the success message from [contracts/commands.md](contracts/commands.md), and return `self::SUCCESS`.
    - `catch (InvalidProxyListException $e)`: `$logger->error('laravel-cloudflare: reload failed', ['reason' => $e->getMessage(), 'source' => 'reload', 'url' => $config->get('laravelcloudflare.url')])`, then `$this->error("Failed to reload Cloudflare's IP blocks: {$e->getMessage()}. The previously cached list was kept.")`, and return `self::FAILURE`.
- [X] T029 [US4] Rewrite `src/Commands/View.php` to `handle(ProxyCache $proxyCache, Config $config): int`:
  - `$list = $proxyCache->read()`. If it is `null`, print the empty-state warning and return `self::SUCCESS`.
  - `$this->table(['Address'], array_map(fn ($v) => [$v], $list->proxies))`.
  - If `refreshedAt` is null, print `Last refreshed: unknown (cached by an earlier version)`. Otherwise print `Last refreshed: '.gmdate('c', $list->refreshedAt).' ('.$list->ageInDays().' days ago)'`.
  - If `$list->isStale((int) $config->get('laravelcloudflare.stale_after', 7))`, print the STALE warning from [contracts/commands.md](contracts/commands.md).
  - Never write to the cache.
- [X] T030 [US4] Add a staleness check and legacy back-fill to `forRequest()` in `src/ProxyCache.php`, on the cached-hit path and inside try/catch:
  - If `refreshedAt === null`, write `forever('<cache>.meta', ['refreshed_at' => time()])`.
  - Otherwise, if `isStale(stale_after)` and `$this->store()->add('<cache>.stale-warned', 1, 3600)` returns true, log `warning('laravel-cloudflare: cached Cloudflare ranges are stale', ['age_days' => $list->ageInDays(), 'stale_after' => $staleAfter])`.
  - Always return the list (FR-030).

  Makes T025, T026 and T027 pass.

**Checkpoint**: Operators can alert on exit codes and logs, and the view command tells them the list's state.

---

## Phase 7: User Story 5 - Ongoing security and reliability checks (Priority: P3)

**Goal**: CI catches vulnerable dependencies and regressions, and a private reporting channel is published (FR-022 to FR-024).

**Independent Test**: The `audit.yml` job runs on a pull request and fails on an advisory. `SECURITY.md` is visible under GitHub **Security → Policy**. Removing a guard makes the suite fail.

- [X] T031 [P] [US5] Create `.github/workflows/audit.yml`:
  - `name: Security audit`.
  - `on:` pull_request (types opened, synchronize, reopened); push to `main`; `schedule: - cron: '0 6 * * 1'`; and `workflow_dispatch`.
  - `permissions: contents: read`.
  - Job `audit` on `ubuntu-latest` with these steps:
    - `actions/checkout@v5`;
    - `shivammathur/setup-php@v2` with `php-version: '8.4'`, `tools: composer:v2`, `coverage: none`;
    - `composer update --prefer-dist --no-progress --no-interaction`;
    - `composer audit --no-interaction`, where a non-zero exit fails the job.
- [X] T032 [P] [US5] Create `SECURITY.md` at the repository root:
  - A supported-versions table: `4.x`, security fixes; `< 4.0`, not supported.
  - How to report: privately through GitHub's "Report a vulnerability" (`https://github.com/monicahq/laravel-cloudflare/security/advisories/new`), never through public issues.
  - What to include in a report.
  - In-scope examples: client-IP spoofing, widening of trusted proxies, availability problems caused by list fetching.
  - Do not invent an email address or response-time commitment. Leave a clearly marked placeholder for the maintainer to confirm a response time.
- [ ] T033 [US5] **Manual, maintainer only**: enable **Settings → Code security → Private vulnerability reporting** on GitHub, so the link in `SECURITY.md` works. Also confirm or fill in the response-time placeholder in `SECURITY.md`.
- [X] T034 [US5] Mutation check for the regression guard (US5-1). Temporarily remove the `IpUtils::checkIp` guard in `src/Http/Middleware/TrustProxies.php`, run `vendor/bin/phpunit --filter TrustProxiesTest`, and confirm that cases (b) and (d) of T012 fail. Then temporarily remove the minimum-prefix check in `src/ProxyListValidator.php`, run `vendor/bin/phpunit --filter ProxyListValidatorTest`, and confirm the catch-all cases fail. Restore both changes and confirm the suite is green again.

---

## Phase 8: Polish & Cross-Cutting Concerns

- [X] T035 [P] Update `README.md`:
  - (1) A new **Security considerations** section:
    - `replace_ip` is honoured only from Cloudflare ranges;
    - lock the origin to Cloudflare traffic (firewall allow-list or Authenticated Origin Pulls);
    - never set `trustedproxy.proxies` to `*` behind Cloudflare.
  - (2) A **Configuration** table for `timeout`, `retry_after` and `stale_after`, copied from [contracts/configuration.md](contracts/configuration.md).
  - (3) A **Failure behaviour** section:
    - visitor requests fail open and the failure is logged;
    - back-off and timeout;
    - a failed reload keeps the previous list and exits with code 1, so suggest `->onFailure(...)` or `->emailOutputOnFailure(...)` on the schedule;
    - the stale warning after 7 days.
  - (4) Extend **View current Cloudflare's IP blocks** with the "Last refreshed" and STALE output.
  - (5) A new **Upgrading to 4.2** section listing every behaviour change from research R11.
  - (6) A link to `SECURITY.md`.
- [X] T036 Rewrite the `enabled` and `replace_ip` docblocks in `config/laravelcloudflare.php` to describe the new semantics: disabled means no header replacement; `replace_ip` is honoured only for requests from Cloudflare ranges with a single valid IP. Follow [contracts/configuration.md](contracts/configuration.md).
- [X] T037 Run `vendor/bin/pint`, `vendor/bin/phpstan analyse` and `vendor/bin/psalm`, and fix every new finding in `src/` and `tests/` without adding suppressions to `phpstan.neon` or `psalm.xml`.
- [X] T038 Run the full [quickstart.md](quickstart.md) validation:
  - `vendor/bin/phpunit` (all green);
  - `vendor/bin/phpunit --group network` (live Cloudflare; expect 15 IPv4 and 7 IPv6 ranges or the current counts);
  - `composer audit`.

  Confirm every row of the quickstart scenario map has a passing test, and add any missing case to the named test file.
- [X] T039 Prepare conventional commits for the release, such as `fix(security): only honour Cf-Connecting-Ip from Cloudflare ranges`, `fix: validate Cloudflare ranges and keep last good list` and `feat: list age, stale warnings and reload exit codes`. Each commit body should quote the relevant "Upgrading to 4.2" bullet from `README.md`. Use **no** `BREAKING CHANGE` footer, so semantic-release publishes 4.2.0.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: none. T002–T005 are parallel.
- **Foundational (Phase 2)**: needs Setup. It blocks every story.
- **US1 (Phase 3)** and **US2 (Phase 4)**: each needs Foundational only. They are independent of each other.
- **US3 (Phase 5)**: needs Foundational. T022 edits `src/CloudflareProxies.php` after T019 (US2), and T024 extends the `forRequest()` from T013 (US1).
- **US4 (Phase 6)**: needs Foundational, and T023 from US3 for the reload command. The view command (T026, T029) needs Foundational only.
- **US5 (Phase 7)**: T031–T033 can start at any time. T034 needs US1 and Foundational.
- **Polish (Phase 8)**: after the desired stories.

### Story dependency graph

```text
Setup ─► Foundational ─┬─► US1 (P1, MVP) ─┐
                       ├─► US2 (P1) ──────┼─► US3 (P2) ─► US4 (P2) ─► Polish
                       └─► US5 docs/CI (P3, any time) ─────────────────┘
```

### Within each story

- Write the tests first and watch them fail, then implement, then run the story's filter until green.
- Tasks that edit the same file run in ID order: `ProxyCache.php` is T010 → T013 → T023 → T024 → T030, and `TrustProxies.php` is T014 → T015 → T016.

### Parallel Opportunities

- Setup: T002, T003, T004 and T005.
- Foundational: T006 and T007 (tests), then T009 alongside T008.
- After Foundational, US1 (T012–T016) and US2 (T017–T019) on different files, plus US5 T031 and T032.
- US3 tests T020 and T021; US4 tests T025, T026 and T027.
- Polish T035 alongside T036.

---

## Parallel Example: after Foundational

```text
Task: "T012 [US1] Rewrite tests/Unit/Http/Middleware/TrustProxiesTest.php …"
Task: "T017 [P] [US2] Extend tests/Unit/CloudflareProxiesTest.php …"
Task: "T031 [P] [US5] Create .github/workflows/audit.yml …"
Task: "T032 [P] [US5] Create SECURITY.md …"
```

## Parallel Example: User Story 4 tests

```text
Task: "T025 [P] [US4] Rewrite tests/Unit/Commands/ReloadTest.php …"
Task: "T026 [P] [US4] Rewrite tests/Unit/Commands/ViewTest.php …"
Task: "T027 [P] [US4] Add staleness cases to tests/Unit/ProxyCacheTest.php …"
```

---

## Implementation Strategy

### MVP first (User Story 1)

1. Phase 1 Setup, then Phase 2 Foundational.
2. Phase 3 (US1): the spoofing fix, the most severe issue.
3. **Stop and validate**: `vendor/bin/phpunit --filter TrustProxiesTest` plus the whole suite. This could ship alone as a security patch.

### Incremental delivery

1. US1, the spoofing fix.
2. US2, list validation. The two P1 stories together close both trust-widening holes.
3. US3, resilience.
4. US4, operator visibility.
5. US5, CI and policy, then Polish. Everything goes out as one 4.2.0 release (FR-028).

## Notes

- 39 tasks. Each story is independently testable through its PHPUnit filter.
- Never weaken a test to make it pass. A failing guard test means the implementation is wrong.
- Commit after each task or logical group, using conventional commits.
