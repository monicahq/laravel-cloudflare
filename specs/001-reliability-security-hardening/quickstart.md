# Quickstart: Validating Reliability & Security Hardening

## Prerequisites

- PHP 8.2+ and Composer.
- From the repository root: `composer install`.

## Automated checks (the gate for every story)

```bash
vendor/bin/phpunit
```

```bash
vendor/bin/phpstan analyse
```

```bash
vendor/bin/psalm
```

```bash
vendor/bin/pint --test
```

```bash
composer audit
```

All must pass. They also run in CI (`tests.yml`, `static.yml`, and the new `audit.yml`).

## Scenario map: what proves each story

Each row names the test class (see [plan.md](plan.md)) that must contain at least one test per scenario.

| Story / spec item | Scenario | Test class | Expected outcome |
|---|---|---|---|
| US1-1 (FR-001) | `replace_ip=true`, `REMOTE_ADDR=203.0.113.9` (outside CF), `Cf-Connecting-Ip: 198.51.100.7` | `TrustProxiesTest` | `$request->ip() === '203.0.113.9'` |
| US1-2 | `REMOTE_ADDR=173.245.48.1` (inside CF), valid header | `TrustProxiesTest` | `$request->ip() === '198.51.100.7'` |
| US1-3 (FR-002) | Header `1.2.3.4, 5.6.7.8`, `1.2.3.4:80`, `junk` | `TrustProxiesTest` | header ignored |
| US1-4 (FR-003) | Empty cache + download fails, request with `X-Forwarded-For` | `TrustProxiesTest` | forwarded header not trusted, `ip()` is `REMOTE_ADDR` |
| US2-1..4 (FR-004–007) | Valid lists, HTML body, `0.0.0.0/0`, `::/0`, `10.0.0.0/7`, whitespace/blank lines, no trailing newline | `ProxyListValidatorTest`, `CloudflareProxiesTest` | accepted / whole-list rejected as described in [data-model.md](data-model.md) |
| FR-008 | `url=http://…`; redirect to another host | `CloudflareProxiesTest` | `InvalidProxyListException` |
| US3-1 (FR-009) | Good list cached, reload against failing / invalid source | `ReloadTest`, `ProxyCacheTest` | cache unchanged, exit 1 |
| US3-2 (FR-012) | Empty cache, `Http::fake` 500 or connection error | `TrustProxiesTest` | `$next` called, warning logged |
| US3-3 (FR-013) | Second request within `retry_after` | `ProxyCacheTest` | `Http::assertSentCount` unchanged |
| US3-4 (FR-011) | Timeout configured; `Http` client receives `timeout` option | `CloudflareProxiesTest` | option set to configured value |
| US3-5 (FR-014) | Lock already held | `ProxyCacheTest` | no second download |
| FR-010 | IPv4 OK, IPv6 fails | `CloudflareProxiesTest`, `ReloadTest` | whole reload fails |
| FR-015/016 | Cached value is a string / array with ints / store throws | `ProxyCacheTest` | treated as missing; request served |
| US4-1..5 (FR-017–021) | Reload fail / disabled / success counts; view empty | `ReloadTest`, `ViewTest` | per [contracts/commands.md](contracts/commands.md) |
| US4-6 (FR-029/030) | `refreshed_at` 8 days ago | `ProxyCacheTest`, `ViewTest` | one warning per hour; view shows STALE; list still trusted |
| Legacy list | `<cache>` set, no `.meta` | `ProxyCacheTest`, `ViewTest` | list used; meta back-filled; view shows "unknown" |
| Edge: disabled | `enabled=false`, `replace_ip=true` | `TrustProxiesTest` | no replacement, no Cloudflare trust, no HTTP |
| Edge: operator proxies | `TrustProxies::at('10.0.0.1')` + cached list | `TrustProxiesTest` | both trusted, no duplicates; static state not mutated by the package |
| Edge: callback | Callback throws / returns `['*']` | `ProxyCacheTest`, `ReloadTest` | rejected; same handling as download |

## Manual smoke test (optional, against real Cloudflare)

Use a scratch Laravel app with the package path-required:

```bash
php artisan cloudflare:reload
```

Expected: `… reloaded: 15 IPv4 and 7 IPv6 ranges.` (counts as of 2026-10-08) and exit code 0.

```bash
LARAVEL_CLOUDFLARE_TIMEOUT=1 php artisan tinker --execute="config(['laravelcloudflare.url' => 'http://www.cloudflare.com']); Artisan::call('cloudflare:reload'); echo Artisan::output();"
```

Expected: a failure message mentioning HTTPS, and `cloudflare:view` still lists the previous ranges.

```bash
php artisan cloudflare:view
```

Expected: the table, then `Last refreshed: … (0 days ago)`.

## CI / process checks (Story 5)

- `SECURITY.md` exists at the repository root and GitHub shows it under **Security → Policy**.
- `.github/workflows/audit.yml` runs on a pull request, and the job fails when `composer audit` reports an advisory.
- The README has a "Security considerations" section and an "Upgrading to 4.2" section.
