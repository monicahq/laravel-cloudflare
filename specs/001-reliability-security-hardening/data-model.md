# Data Model: Reliability & Security Hardening

There is no database. All state lives in the application's default Laravel cache store. `<cache>` below is the value of the `laravelcloudflare.cache` config key (default `cloudflare.proxies`).

## Entities

### Range entry

A single trusted address or network.

| Field | Type | Rule |
|---|---|---|
| value | string | After `trim()`, either an IP address or `address/prefix` (R2) |
| family | derived: `ipv4` \| `ipv6` | From the address part |
| prefix | derived int | Explicit prefix, or 32/128 for a bare address. IPv4: `8 ≤ prefix ≤ 32`; IPv6: `16 ≤ prefix ≤ 128` (FR-005) |

Invalid examples: `*`, `0.0.0.0/0`, `::/0`, `10.0.0.0/7`, `1.2.3.4:443`, `<html>`, `fe80::1%eth0`, `1.2.3.4/33`, `1.2.3.4/abc`.

### Range list

An ordered list of range entries from one source: Cloudflare download, operator callback, or cache.

- Blank lines and surrounding whitespace are dropped before validation (FR-007).
- **Valid** if and only if it has ≥ 1 entry **and** every entry is valid (FR-006). Otherwise the whole list is rejected with a reason naming the first offending entry (truncated to 64 characters).
- For a download, both the IPv4 and IPv6 lists must be valid. The result is IPv4 followed by IPv6, with no duplicates (FR-010). A list supplied by the custom callback is validated as a single list. It may contain either family.

### Cache records

| Key | Value (synthetic example) | Lifetime | Written by | Purpose |
|---|---|---|---|---|
| `<cache>` | `["173.245.48.0/20", "2400:cb00::/32", …]` (plain list of strings) | forever | reload, request-path download | The cached range list. The format is unchanged since 4.x, so upgrades and downgrades stay safe (R6) |
| `<cache>.meta` | `{"refreshed_at": 1791446400}` (Unix seconds, UTC) | forever | same writers as `<cache>`; also back-filled by the first request-path read (`forRequest`) of a legacy list (never by `cloudflare:view`) | Refresh time for age and staleness (FR-029) |
| `<cache>.failed` | `1791446400` (Unix seconds) | `retry_after` seconds (default 60) | request-path download failure | Back-off marker (FR-013). Cleared by a successful reload or download |
| `<cache>.lock` | lock token | `timeout + 5` seconds | request-path download | At most one download at a time (FR-014) |
| `<cache>.stale-warned` | `1` | 3600 seconds | request-path staleness check | Throttles stale warnings to once per hour (FR-030) |

Write order for a refresh: validate → put `<cache>.meta` → put `<cache>` → forget `<cache>.failed`. An invalid list never reaches `put` (FR-009).

### Cached list read result (in-memory value object)

`ProxyCache::read()` returns one of:

- `CachedList { proxies: list<string>, refreshedAt: ?int, isStale(days) }`, the valid cached list;
- `null`, when the list is missing, corrupted, invalid, or the store is unavailable (FR-015, FR-016).

## State transitions (cached range list)

```text
            reload OK / download OK
  EMPTY ───────────────────────────────► FRESH
    │  ▲                                  │  ▲
    │  │ value corrupted/invalid          │  │ reload OK / download OK
    │  │ (treated as EMPTY, not deleted)  │  │
    │  └──────────────────────────────────┤  │
    │                                     ▼  │
    │                         age > stale_after days
    │                                   STALE ──► (still trusted; warn ≤ 1/h; view shows STALE)
    │
    │ request-path download fails
    ▼
  BACKING-OFF (EMPTY + `<cache>.failed` present, ≤ retry_after s)
    │ marker expires → next request may download (with lock)
    │ reload OK → FRESH
```

- A failed or invalid reload in **any** state leaves that state unchanged. The command exits 1 and logs an error.
- Disabled package (`enabled=false`): no transitions happen. The middleware does not read the cache, and reload is a no-op that reports "disabled".
