# Feature Specification: Reliability & Security Hardening

**Feature Branch**: `001-reliability-security-hardening` (spec directory; no git branch created)

**Created**: 2026-10-08

**Status**: Draft

**Input**: User description: "improve and check reliability and security"

## Context

The package lets an application that sits behind Cloudflare recognise Cloudflare's edge servers as trusted proxies, so the application sees each visitor's real IP address and protocol. It downloads Cloudflare's published address ranges, keeps them in the application cache, and offers two operator commands: reload the ranges and view the stored ranges.

Because the package decides **whose forwarded headers the application believes**, a mistake can let any visitor fake their IP address. Rate limiting, audit logs, IP allow-lists and geo rules all rely on that address. A failure can also take down every request. A review of the current behaviour found these gaps:

1. **Spoofable client IP**: when the "replace IP" option is on, the `Cf-Connecting-Ip` header is believed from **any** sender, not only from Cloudflare. A visitor who reaches the origin directly can pose as any address.
2. **Downloaded ranges are not checked**: every non-empty line of the download is trusted as-is. If an error page, a captive portal or a tampered response comes back, junk entries or an overly broad range (for example "trust everyone") can end up in the trusted list.
3. **A good list can be replaced by a bad one**: a reload that returns nothing usable overwrites the last good list in the cache, and the empty result is then kept indefinitely.
4. **Visitor requests depend on Cloudflare being reachable**: when the cache is empty, the first request downloads the ranges while the visitor waits. If Cloudflare is slow or unreachable, that request, and every request after it until the cache is filled, fails with a server error or waits a long time.
5. **Many requests can download at once**: when the cache is empty, every request arriving at the same moment starts its own download.
6. **Failures are hard to see**: the reload command reports success the same way whether it reloaded, was disabled or produced an unusable result. The view command shows an empty table with no explanation, and nothing is logged when the ranges cannot be loaded.
7. **The check process is incomplete**: the package's dependencies are not scanned for known vulnerabilities, there is no published way to report a vulnerability, and the failure paths above have no automated tests.

## Clarifications

### Session 2026-10-08

- Q: When the stored Cloudflare ranges are missing, should a visitor's request ever download them on the spot, or should only the reload command do the downloading? → A: Keep on-the-spot downloads during visitor requests when no ranges are cached, protected by a bounded timeout, spaced-out retries after a failure, and at most one download at a time (Option B).
- Q: If the stored Cloudflare list stops being refreshed, what should the package do as the list gets old? → A: Record when the list was last refreshed, show its age in the view command, and log a warning once it is older than a configurable limit (default 7 days), while continuing to use it (Option B).
- Q: How should this work be released, given that a few behaviours change? → A: As one minor release; every behaviour change is treated as a security or reliability fix and documented with upgrade notes in the changelog (Option A).
- Q: If the Cloudflare ranges can't be loaded at all (nothing cached and the download fails), what should happen to visitor requests? → A: Serve the request normally without trusting Cloudflare, and log the failure; never refuse requests because ranges are unavailable (Option A).

## User Scenarios & Testing *(mandatory)*

Actors:
- **Application operator**: installs and runs the package in a Laravel application behind Cloudflare.
- **Visitor**: an end user whose request passes through the application.
- **Attacker**: someone who sends requests directly to the origin or interferes with the range download.
- **Package maintainer**: releases the package and responds to security reports.

### User Story 1 - Visitor IP cannot be spoofed (Priority: P1)

As an application operator, I need the client IP the application sees to be the real one. Someone who bypasses Cloudflare must not be able to impersonate another address, whatever options I enable.

**Why this priority**: Spoofing the client IP defeats rate limiting, IP allow-lists and audit trails. It is the highest-impact security flaw in the package today.

**Independent Test**: Send requests that carry a fake `Cf-Connecting-Ip` or forwarded header, once from an address inside Cloudflare's ranges and once from an address outside them. Check the client IP the application reports in each case.

**Acceptance Scenarios**:

1. **Given** the "replace IP" option is on, **When** a request from an address outside Cloudflare's ranges carries a `Cf-Connecting-Ip` header, **Then** the header is ignored and the application sees the real connecting address.
2. **Given** the "replace IP" option is on, **When** a request from an address inside Cloudflare's ranges carries a valid `Cf-Connecting-Ip` header, **Then** the application sees the header's address as the client IP.
3. **Given** the request comes from Cloudflare, **When** the `Cf-Connecting-Ip` header is not a valid IP address, **Then** the header is ignored and the connecting address is kept.
4. **Given** no Cloudflare ranges are available (download failed, cache empty), **When** a request carries forwarded or `Cf-Connecting-Ip` headers, **Then** none of them is believed.

---

### User Story 2 - Only valid Cloudflare ranges are ever trusted (Priority: P1)

As an application operator, I need assurance that the trusted-proxy list holds only valid, reasonably sized network ranges. A broken or tampered download must never widen who my application trusts.

**Why this priority**: A single bad entry, such as a catch-all range, would make every forwarded header trusted from any sender. That is as severe as Story 1.

**Independent Test**: Make the download return a mix of malformed lines, HTML, a catch-all range and valid ranges. Check what is accepted and what the cache contains afterwards.

**Acceptance Scenarios**:

1. **Given** the download returns valid IPv4 and IPv6 ranges, **When** the ranges are loaded, **Then** all of them are accepted unchanged.
2. **Given** the download returns an HTML page or other non-range content, **When** the ranges are loaded, **Then** the load is rejected as a whole and nothing is trusted from it.
3. **Given** the download contains a range broader than the accepted limit (for example a "trust everything" range), **When** the ranges are loaded, **Then** the load is rejected and the reason is reported.
4. **Given** the download contains blank lines or surrounding whitespace, **When** the ranges are loaded, **Then** these are tolerated and the valid ranges are accepted.

---

### User Story 3 - The application stays up when Cloudflare's list cannot be fetched (Priority: P2)

As an application operator, I need my application to keep serving visitors when Cloudflare's list endpoint is slow, unreachable or returns garbage. A bad reload must not destroy a good list I already have.

**Why this priority**: Today an empty cache combined with a Cloudflare outage turns every request into an error. That is a full outage caused by a dependency that is not needed to serve the page.

**Independent Test**: With the list endpoint simulated as down, slow or invalid, (a) send visitor requests with an empty cache and (b) run a reload when a good list is already cached. Check that requests succeed quickly and the good list is kept.

**Acceptance Scenarios**:

1. **Given** a good list is cached, **When** a reload fails or returns an unusable list, **Then** the cached list is left unchanged and the operator is told the reload failed.
2. **Given** the cache is empty and Cloudflare is unreachable, **When** a visitor request arrives, **Then** the request completes normally without trusting any Cloudflare range, and the failure is logged.
3. **Given** the cache is empty and Cloudflare is unreachable, **When** further visitor requests arrive shortly after, **Then** they do not each retry the download. Retries are spaced out instead.
4. **Given** the list endpoint does not respond, **When** a download is attempted, **Then** the attempt is abandoned within a bounded time and is treated as a failure.
5. **Given** the cache is empty, **When** many visitor requests arrive at once, **Then** at most one download runs at a time.

---

### User Story 4 - Operators can see and act on failures (Priority: P2)

As an application operator, I need clear signals when the ranges cannot be loaded or are missing. I want to catch problems through my scheduler, my logs and the view command, not through user reports.

**Why this priority**: Silent failures leave the application either distrusting Cloudflare, which gives wrong IPs everywhere, or running on a very old list, and nobody notices.

**Independent Test**: Run the reload and view commands in success, failure, disabled and empty-cache situations. Check exit statuses, messages and log entries.

**Acceptance Scenarios**:

1. **Given** the reload fails, **When** the reload command finishes, **Then** it exits with a failure status and prints a human-readable reason.
2. **Given** the package is disabled, **When** the reload command runs, **Then** it says nothing was reloaded because the package is disabled, and it exits successfully.
3. **Given** the reload succeeds, **When** the command finishes, **Then** it reports how many IPv4 and IPv6 ranges were stored.
4. **Given** the cache holds no ranges, **When** the view command runs, **Then** it says no ranges are cached and tells the operator how to load them.
5. **Given** a download or validation fails during a visitor request or a reload, **When** the failure happens, **Then** an entry is written to the application log with the reason. No secrets or request data are included.
6. **Given** the cached list was last refreshed longer ago than the staleness limit, **When** requests are served or the view command runs, **Then** a warning is logged (at most once per hour), the view command shows the list's age and marks it stale, and the list is still used.

---

### User Story 5 - Ongoing security and reliability checks (Priority: P3)

As a package maintainer, I need automated checks that catch regressions and vulnerable dependencies. I also need a published process for receiving vulnerability reports, so the hardening holds over time.

**Why this priority**: The fixes above only stay fixed if they are protected by tests and checks. Users also need a responsible way to report problems.

**Independent Test**: Open a change that reintroduces one of the flaws above, or that adds a dependency with a known vulnerability. Check that the automated checks fail. Check that the repository shows a vulnerability reporting policy.

**Acceptance Scenarios**:

1. **Given** a change reintroduces trust of `Cf-Connecting-Ip` from outside Cloudflare, **When** the automated checks run, **Then** they fail.
2. **Given** a dependency with a known security advisory is required, **When** the automated checks run, **Then** they flag it.
3. **Given** a security researcher visits the repository, **When** they look for how to report a vulnerability, **Then** they find a policy with a private reporting channel and the supported versions.
4. **Given** each failure scenario in Stories 1–4, **When** the test suite runs, **Then** each one is covered by at least one automated test.

---

### Edge Cases

- The IPv4 list downloads successfully but the IPv6 list fails, or the reverse. The whole reload is treated as failed and the previous list is kept, so the application never trusts only half of Cloudflare's ranges.
- The download redirects to another host, or the configured source address is not secure (plain HTTP). Unsafe sources are refused.
- The `Cf-Connecting-Ip` header holds several values, an IPv6 address, an address with a port, or surrounding whitespace.
- The operator also trusts their own proxies (for example a load balancer in front of the app). Those stay trusted alongside Cloudflare's ranges without duplicates.
- The cache store is unavailable (for example the cache server is down). Requests still complete and the failure is logged.
- The cached value is corrupted or in an unexpected shape, for example written by an older package version. It is treated as missing, not trusted.
- The custom "get proxies" callback set by the operator returns invalid entries or throws. The same validation and failure handling applies as for downloaded lists.
- Cloudflare legitimately publishes a new range. The next successful reload picks it up with no code change.
- The package is disabled. No download, no header replacement and no trust changes happen.

## Requirements *(mandatory)*

### Functional Requirements

**Client IP integrity**

- **FR-001**: The package MUST use the `Cf-Connecting-Ip` header as the client address only when the request's direct connecting address is inside the currently trusted Cloudflare ranges.
- **FR-002**: The package MUST ignore a `Cf-Connecting-Ip` header whose value is not a single valid IPv4 or IPv6 address.
- **FR-003**: When no valid Cloudflare ranges are available, the package MUST NOT believe forwarded or `Cf-Connecting-Ip` headers from any sender on Cloudflare's behalf.

**List validation**

- **FR-004**: The package MUST accept a downloaded or callback-supplied entry only if it is a valid IPv4 or IPv6 address or network range.
- **FR-005**: The package MUST reject any range broader than a defined minimum prefix length: no IPv4 range wider than /8 and no IPv6 range wider than /16. This rules out catch-all entries.
- **FR-006**: The package MUST reject a whole load, not just the bad lines, when any non-blank entry fails validation or when the result has no valid entries. A partially corrupted list is never trusted.
- **FR-007**: The package MUST tolerate blank lines and surrounding whitespace in the downloaded lists.
- **FR-008**: The package MUST download ranges only from a secure (HTTPS) source and MUST NOT follow redirects to a different host.

**Resilience**

- **FR-009**: A reload that fails or produces an invalid list MUST leave the previously cached list unchanged.
- **FR-010**: A reload MUST succeed only when both the IPv4 and IPv6 lists load and validate. Otherwise neither is stored.
- **FR-011**: Each download attempt MUST time out after a bounded time (default: 5 seconds), and the operator MUST be able to change that limit.
- **FR-012**: When no valid ranges are cached, a visitor request MAY download them on the spot, subject to FR-011, FR-013 and FR-014. A failure to obtain ranges during a visitor request MUST NOT cause that request to fail. The request continues without Cloudflare ranges being trusted.
- **FR-013**: After a failed download during visitor requests, the package MUST wait before trying again (default: 1 minute, operator-configurable), not retry on every request.
- **FR-014**: When the cache is empty, the package MUST allow at most one download at a time across concurrent requests.
- **FR-015**: An unreadable, corrupted or unexpectedly shaped cached value MUST be treated as missing, never as a trusted list. A list stored by an earlier package version is a recognised shape: its entries are re-validated and, if valid, it is used without a refresh time (see Key Entities).
- **FR-016**: Unavailability of the cache store MUST NOT cause visitor requests to fail.

**Observability**

- **FR-017**: The reload command MUST exit with a failure status, and print the reason, when ranges cannot be downloaded or validated.
- **FR-018**: The reload command MUST state clearly when it did nothing because the package is disabled.
- **FR-019**: A successful reload MUST report the number of IPv4 and IPv6 ranges stored.
- **FR-020**: The view command MUST state clearly when no ranges are cached and point to the reload command.
- **FR-021**: The package MUST write a log entry for each failed download or validation, with the reason and without request contents or credentials.
- **FR-029**: The package MUST record when the cached range list was last successfully refreshed, and the view command MUST show that time and the list's age.
- **FR-030**: When the cached range list is older than a staleness limit (default: 7 days, operator-configurable), the package MUST log a warning, at most once per hour, and the view command MUST flag the list as stale. A stale list MUST still be used; staleness alone never removes trust.

**Verification & process**

- **FR-022**: The automated test suite MUST cover every acceptance scenario and edge case in this specification.
- **FR-023**: The continuous checks MUST include a scan of dependencies for known security advisories, and MUST fail when one is found.
- **FR-024**: The repository MUST publish a security policy describing how to report a vulnerability privately and which versions get security fixes.
- **FR-025**: The documentation MUST explain the security implications of the "replace IP" option and of trusting forwarded headers. It MUST state that origin servers should accept traffic only from Cloudflare where possible.
- **FR-026**: The documentation MUST describe the new failure behaviour, defaults and configuration options introduced here.

**Compatibility**

- **FR-027**: Existing operator-facing configuration options, command names and the custom "get proxies" callback MUST keep working with the same meaning, except that they now gain the protections above.
- **FR-028**: All changes in this feature MUST ship together in a single minor release within the current major version. No existing option, command or public entry point may be removed or renamed. Every behaviour change that could affect an existing installation MUST be listed in the changelog as a security or reliability fix, with upgrade notes. These include ignoring `Cf-Connecting-Ip` from non-Cloudflare senders, the new reload failure status, and new defaults.

### Key Entities

- **Cloudflare range list**: the set of IPv4 and IPv6 network ranges Cloudflare publishes for its edge servers. Attributes: entries, address family per entry, time last refreshed. Valid only if every entry passes validation.
- **Cached range list**: the last validated range list kept by the application, together with the time it was last refreshed. It is replaced only by another fully validated list. It becomes "stale" once its age passes the staleness limit, which triggers warnings but does not remove trust. A cached list from an older package version has no refresh time; it is treated as refreshed at the moment of upgrade, so no false stale warnings appear.
- **Trusted proxy set**: the ranges the application believes when reading forwarded headers. It combines the cached Cloudflare ranges with any proxies the operator trusts explicitly.
- **Download failure marker**: a short-lived record that a recent download failed, used to space out retries during visitor requests.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of spoofing attempts in the test suite fail, meaning requests from outside Cloudflare's ranges with forged client-IP headers. Visitors who really come through Cloudflare still get their correct IP in 100% of cases.
- **SC-002**: 0 malformed, non-range or catch-all entries reach the trusted set across all invalid-download test cases.
- **SC-003**: With Cloudflare's list unreachable and an empty cache, 100% of visitor requests still complete successfully. None is delayed by more than the configured download time limit, and at most one download attempt is made per retry interval.
- **SC-004**: A failed or invalid reload never replaces a previously good list (verified in 100% of failure test cases).
- **SC-005**: Every failure path produces a non-success command status or a log entry an operator can alert on. No silent failures remain in the scenarios above.
- **SC-006**: Every acceptance scenario and edge case in this spec maps to at least one automated test, and the suite passes on every supported PHP and Laravel version.
- **SC-007**: Dependency vulnerability scanning runs on every proposed change. A security reporting policy is visible on the repository's main page.
- **SC-008**: Existing installations that use the documented setup keep working after upgrading, with no configuration change, unless they relied on the insecure behaviour.

## Assumptions

- "Improve and check" covers both hardening the package's behaviour (Stories 1–4) and adding ongoing verification (Story 5). Story 5 includes automated tests, dependency scanning and a vulnerability reporting policy.
- Failing open is the chosen behaviour for visitor requests (confirmed in Clarifications), and it is not configurable. When ranges are unavailable, the request proceeds without trusting Cloudflare (client IP shows as a Cloudflare edge address) instead of returning an error. This degrades IP accuracy but cannot create a spoofing hole.
- Ignoring `Cf-Connecting-Ip` from non-Cloudflare senders is treated as a security fix, not a breaking change. Legitimate traffic through Cloudflare is unaffected.
- A reload is all-or-nothing across IPv4 and IPv6, to avoid partially trusting Cloudflare.
- Minimum prefix limits (/8 IPv4, /16 IPv6) are well below anything Cloudflare publishes, so they will not reject legitimate lists. The current largest published ranges are /13 for IPv4 and /29 for IPv6 (checked 2026-10-08).
- Default limits (5-second download timeout, 1-minute retry spacing) are reasonable starting values and can be configured.
- The package will not ship a built-in copy of Cloudflare's ranges as a fallback, because a stale bundled list is itself a risk. Operators who want one can use the existing custom callback.
- Cloudflare's public plain-text range lists remain the default source. Switching to another Cloudflare source is out of scope.
- Supported platform versions stay as currently declared by the package.
- Hosting-level protections, such as firewalling the origin to Cloudflare-only traffic or authenticated origin pulls, are outside the package's control. They are addressed through documentation only.
