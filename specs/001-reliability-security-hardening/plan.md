# Implementation Plan: Reliability & Security Hardening

**Branch**: `001-reliability-security-hardening` | **Date**: 2026-10-08 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/001-reliability-security-hardening/spec.md`

## Summary

Harden `monicahq/laravel-cloudflare` so that:

- (a) the client IP can no longer be spoofed;
- (b) only validated, reasonably sized Cloudflare ranges are ever trusted;
- (c) a Cloudflare outage or a bad download can neither take the application down nor replace a good list;
- (d) operators get exit codes, logs and list age to act on;
- (e) CI guards these properties.

Technical approach:

- Introduce a `ProxyListValidator`, which checks entries and prefix limits.
- Introduce a `ProxyCache` service. It owns the cached list, its refresh-time metadata, the download lock, the failure back-off marker and the stale warnings.
- Harden `CloudflareProxies`: injected HTTP client, timeout, HTTPS-only, same-host redirects only, validated output.
- Rewrite the middleware to:
  - read through `ProxyCache` and fail open;
  - trust `Cf-Connecting-Ip` only from Cloudflare addresses;
  - compute trusted proxies per request instead of mutating `TrustProxies::$alwaysTrustProxies` static state.
- Give the commands real exit codes and richer output.
- Add a `composer audit` workflow, a `SECURITY.md`, and documentation.
- Ship everything as one minor release (4.2.0).

## Technical Context

**Language/Version**: PHP ^8.2 (CI matrix 8.2, 8.3, 8.4)

**Primary Dependencies**:
- `illuminate/support` ^11 || ^12 || ^13: Http client, Cache (with `LockProvider`), Console, Log.
- `symfony/http-foundation`, through Laravel: `IpUtils::checkIp()`.
- `guzzlehttp/guzzle`, which the Http client uses.

**Storage**: Laravel cache, default store. The list key stays a plain array for backward compatibility. Additional keys hold metadata, the failure marker, the lock and the warning throttle (see [data-model.md](data-model.md)).

**Testing**: PHPUnit 10–12 with Orchestra Testbench 9–11, `Http::fake()`, the array cache store (supports locks), and `Log::spy()`. Static analysis uses PHPStan (larastan, strict rules) and Psalm. Style is checked with Pint.

**Target Platform**: Laravel 11/12/13 applications behind Cloudflare, under PHP-FPM or long-running workers (Octane, queue workers).

**Project Type**: Library (Composer package).

**Performance Goals**: The per-request overhead with a warm cache is a few cache reads plus validation of about 22 entries, which is negligible (well under 1 ms). There are no network calls on the request path while the cache is warm.

**Constraints**:
- A request is never blocked longer than the download timeout (default 5 s).
- No request fails because of Cloudflare, the cache or validation.
- Public API, config keys and command names stay backward compatible.
- The release is a minor bump within major version 4.

**Scale/Scope**: About 8 source files and the matching tests. Cloudflare currently publishes 15 IPv4 and 7 IPv6 ranges, the widest being /13 for IPv4 and /29 for IPv6.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Re-checked after implementation against the ratified **Constitution v1.0.0** (2026-10-08). All principles pass:

| Principle | Status | Evidence |
|---|---|---|
| I. Trust Boundary Integrity | ✅ | Whole-list validation with /8 and /16 limits; headers honoured only from trusted peers; no trust when no ranges; HTTPS with same-host redirects only |
| II. Fail-Safe Availability | ✅ | Requests never fail; configurable timeout; back-off; single download lock; good list kept; logs and exit code 1 |
| III. Test-Covered Behavior | ✅ | 107 tests, including negative tests for spoofing, catch-all ranges and corruption. The one live-endpoint test is in the `network` group, which the default suite and CI exclude |
| IV. Backward Compatibility | ✅ | Public surface unchanged; behaviour changes documented in "Upgrading to 4.2"; minor release |
| V. Minimal Footprint | ✅ | No new runtime dependencies; safe defaults for new options |
| Technical constraints | ✅ | No new suppressions (two unused Psalm suppressions removed); `composer audit` in CI; `SECURITY.md` published |

The project's existing conventions were the de-facto gates at planning time:

| Gate (from repository conventions) | Pre-design | Post-design |
|---|---|---|
| Tests run on the full PHP × Laravel matrix (`tests.yml`) | ✅ planned | ✅ all new behaviour covered by unit tests (see quickstart) |
| PHPStan strict + Psalm clean (`static.yml`) | ✅ planned | ✅ new classes are `final`, typed, with no new suppressions |
| Pint style | ✅ | ✅ |
| Conventional commits / semantic-release (`semantic.yml`, `.releaserc`) | ✅ | ✅ `fix:`/`feat:` commits only, no `BREAKING CHANGE` footer, which yields 4.2.0 |
| No new runtime dependencies | ✅ | ✅ only framework classes are used |

## Project Structure

### Documentation (this feature)

```text
specs/001-reliability-security-hardening/
├── plan.md              # This file
├── research.md          # Phase 0 decisions
├── data-model.md        # Cache records, validation rules, state transitions
├── quickstart.md        # Validation guide
├── contracts/
│   ├── configuration.md # Config keys & env vars
│   ├── commands.md      # cloudflare:reload / cloudflare:view behaviour & exit codes
│   └── public-api.md    # PHP classes, exceptions, log events, cache keys
└── tasks.md             # Created by /speckit-tasks
```

### Source Code (repository root)

```text
config/
└── laravelcloudflare.php           # + timeout, retry_after, stale_after

src/
├── CloudflareProxies.php           # MODIFY: injected Http client, timeout, HTTPS-only, same-host redirects, validation
├── ProxyListValidator.php          # NEW: entry & prefix validation, whole-list reject
├── ProxyCache.php                  # NEW: cached list + metadata, lock, back-off marker, stale warnings
├── CachedList.php                  # NEW: readonly value object (proxies, refreshedAt, isStale, countByFamily)
├── Exceptions/
│   └── InvalidProxyListException.php  # NEW: extends UnexpectedValueException
├── LaravelCloudflare.php           # UNCHANGED API (callback result now validated by ProxyCache)
├── TrustedProxyServiceProvider.php # UNCHANGED: ProxyCache/ProxyListValidator are auto-resolved (no singleton, so no stale logger/config in long-running workers)
├── Commands/
│   ├── Reload.php                  # MODIFY: exit codes, messages, counts, keep-good-list
│   └── View.php                    # MODIFY: empty-state, refresh time, age, stale flag
├── Facades/
│   └── CloudflareProxies.php       # UNCHANGED
└── Http/Middleware/
    └── TrustProxies.php            # MODIFY: ProxyCache read, guarded Cf-Connecting-Ip, per-request proxies()

tests/
├── FeatureTestCase.php
└── Unit/
    ├── ProxyListValidatorTest.php  # NEW
    ├── ProxyCacheTest.php          # NEW
    ├── CloudflareProxiesTest.php   # EXTEND
    ├── LaravelCloudflareTest.php
    ├── Commands/ReloadTest.php     # EXTEND
    ├── Commands/ViewTest.php       # EXTEND
    └── Http/Middleware/TrustProxiesTest.php  # REWRITE (valid ranges, spoofing, fail-open)

.github/workflows/audit.yml         # NEW: composer audit on PRs + weekly schedule
SECURITY.md                         # NEW: private reporting & supported versions
README.md                           # MODIFY: security notes, new options, failure behaviour, upgrade notes
```

**Structure Decision**: Single-library layout, kept as it is. Two focused classes are added: `ProxyListValidator`, which is pure and has no I/O, and `ProxyCache`, which owns every cache interaction. This keeps the middleware and the commands thin, and lets the security-critical logic be tested without HTTP or framework plumbing.

## Phase 0 → research.md

All Technical Context items are known, so there are no NEEDS CLARIFICATION entries. Design decisions are recorded in [research.md](research.md):

- R1: per-request proxies vs. static `at()`
- R2: CIDR validation
- R3: prefix limits
- R4: lock strategy
- R5: back-off marker
- R6: cache layout and backward compatibility
- R7: HTTP hardening
- R8: `Cf-Connecting-Ip` guard
- R9: staleness warnings
- R10: CI audit
- R11: release and upgrade notes

## Phase 1 → design

- [data-model.md](data-model.md): range entry, range list, cache records, state machine.
- [contracts/](contracts/): configuration, commands and public PHP API.
- [quickstart.md](quickstart.md): how to validate every user story.

## Complexity Tracking

No constitution violations; nothing to justify.
