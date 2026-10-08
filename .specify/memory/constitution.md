# Laravel Cloudflare Constitution

## Core Principles

### I. Trust Boundary Integrity (NON-NEGOTIABLE)

The package exists to decide which upstream addresses an application trusts. Getting this wrong
lets any visitor forge their IP address. Therefore:

- The package MUST trust only address ranges that Cloudflare publishes, plus ranges the host
  application explicitly configures (`TrustProxies::at()`, `getProxiesUsing()` callback).
- Every range, whether downloaded, cached or returned by a callback, MUST be validated as a
  well-formed IPv4/IPv6 address or CIDR block before it is trusted. Catch-all or implausibly broad
  ranges MUST be rejected.
- A partially invalid list MUST be rejected as a whole. It MUST NOT be trimmed to its valid lines.
- Client-identity headers (`Cf-Connecting-Ip`, `X-Forwarded-*`) MUST be honored only when the
  direct connecting peer is inside the currently trusted ranges.
- When no valid ranges are available, the package MUST fail closed: it trusts no forwarded
  headers on Cloudflare's behalf.
- Ranges MUST be fetched over HTTPS only, without cross-host redirects.

Rationale: a trusted-proxy library that can be tricked into trusting the wrong peer is worse than
no library. Rate limiting, audit logs and access rules downstream all depend on this decision.

### II. Fail-Safe Availability

The package runs on every HTTP request of the host application, so it MUST NOT become a single
point of failure:

- A failure to download, validate, read or write the cached ranges MUST NOT cause a visitor
  request to fail. The request proceeds with Cloudflare ranges untrusted (Principle I).
- Every outbound network call MUST have a bounded, operator-configurable timeout.
- Retries after a failure on the request path MUST be spaced out, not repeated on every request.
  Concurrent cache-miss downloads MUST be limited to one at a time.
- A failed or invalid reload MUST leave the previously cached list unchanged.
- Failures MUST be observable: a log entry with the reason (and no request contents or
  credentials), and a non-zero exit status from Artisan commands.

Rationale: when Cloudflare's endpoint is down or slow, the host application must stay up and
correct, not hang or return 500s.

### III. Test-Covered Behavior

- Every behavior change and bug fix MUST include automated tests (PHPUnit + Orchestra Testbench)
  that fail without the change.
- Security-relevant paths (header handling, range validation, fail-closed behavior, cache
  corruption) MUST each have explicit negative tests that show an attack or a bad input is refused.
- Tests MUST NOT reach the real Cloudflare endpoint. Use `Http::fake()` and in-memory cache and
  config.
- The full suite MUST pass on every supported PHP × Laravel combination in the CI matrix.

Rationale: the package's value lies in subtle edge cases that are easy to break unnoticed. Tests
are the only durable guarantee across Laravel releases.

### IV. Backward Compatibility & Semantic Versioning

- The public surface is: config keys and their env variables, Artisan command names
  (`cloudflare:reload`, `cloudflare:view`), the `TrustProxies` middleware, the `CloudflareProxies`
  facade and class, and `LaravelCloudflare::getProxies()` / `getProxiesUsing()`. It MUST keep its
  meaning within a major version.
- Releases follow Semantic Versioning, derived automatically from Conventional Commits by
  semantic-release. A breaking change to the public surface MUST use a `!` / `BREAKING CHANGE`
  commit and ship only in a major release.
- A security hardening that alters observable behavior for existing installs MAY ship in a minor or
  patch release only if it is documented in the changelog with upgrade notes.
- Support for a Laravel or PHP version MUST NOT be dropped outside a major release. The
  README compatibility table MUST be updated whenever support changes.

Rationale: the package is a drop-in dependency in many applications. Silent behavior changes in
the trust path are unacceptable.

### V. Minimal, Laravel-Native Footprint

- The package MUST build on Laravel's own primitives: it extends
  `Illuminate\Http\Middleware\TrustProxies` and uses the HTTP client, cache, config and console
  facilities. It MUST NOT reimplement them.
- Runtime dependencies MUST stay limited to `php` and `illuminate/*`. Adding any other runtime
  dependency requires written justification in the plan's Complexity Tracking.
- New configuration options MUST have safe defaults, so that a fresh install works securely with
  zero configuration beyond replacing the middleware.
- Features outside "trust Cloudflare's proxies correctly" (for example general Cloudflare API
  clients, firewall management or analytics) are out of scope.

Rationale: a small, focused package is easier to audit, which matters for a security component.

## Technical Constraints

- **Language & frameworks**: PHP `^8.2`, `illuminate/support` `^11.0 || ^12.0 || ^13.0`. The
  supported matrix is the one defined in `.github/workflows/tests.yml` and MUST match
  `composer.json`.
- **Code style**: Laravel Pint (enforced by the pre-commit hook and the lint workflow).
- **Static analysis**: PHPStan with Larastan plus strict, deprecation and phpunit rules (current
  level in `phpstan.neon`), and Psalm. New code MUST NOT add new baseline entries or blanket
  suppressions. A targeted suppression requires an inline justification.
- **Namespace**: `Monicahq\Cloudflare\` → `src/`, tests under `Monicahq\Cloudflare\Tests\`.
- **Security hygiene**: dependency advisories MUST be checked in CI. The repository MUST
  publish a security policy for private vulnerability reporting.

## Development Workflow & Quality Gates

- All changes land on `main` (or a release branch such as `next`) through a pull request.
- PR titles and commits MUST follow Conventional Commits. The `semantic.yml` workflow enforces
  this, and it drives release versioning.
- A PR MUST NOT be merged until every gate is green: unit tests across the full matrix, static
  analysis (PHPStan, Psalm), lint (Pint) and the SonarCloud quality gate.
- Reviews MUST explicitly check Principle I for any change that touches request headers, the
  trusted-range list, the cache, or the download path.
- User-facing behavior or configuration changes MUST update `README.md` in the same PR.
  `CHANGELOG.md` is generated by semantic-release and MUST NOT be hand-edited, except for upgrade
  notes requested by Principle IV.

## Governance

- This constitution supersedes other practice guides for this repository. Spec Kit plans MUST
  pass a "Constitution Check" against these principles. Any justified exception MUST be recorded
  in the plan's Complexity Tracking table.
- **Amendments** are made by pull request to `.specify/memory/constitution.md`. The PR states the
  change, the rationale and the impact on in-flight specs, and is approved by a maintainer.
- **Versioning** of this document follows SemVer: MAJOR for removing or redefining a principle,
  MINOR for adding a principle or section or materially expanding guidance, PATCH for
  clarifications and wording.
- **Compliance review**: every PR review verifies compliance with the principles above. Code that
  is found to diverge from a principle MUST be tracked as a spec or issue until it is resolved.

**Version**: 1.0.0 | **Ratified**: 2026-10-08 | **Last Amended**: 2026-10-08
