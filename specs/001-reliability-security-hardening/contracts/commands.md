# Contract: Artisan commands

Command names and signatures are unchanged (FR-027). Output wording below is normative for tests; exact punctuation may vary.

## `cloudflare:reload`

| Situation | Output | Exit code | Cache effect | Log |
|---|---|---|---|---|
| Package disabled | `info`: "Laravel Cloudflare is disabled; nothing was reloaded." | `0` | none | none |
| Success | `info`: "Cloudflare's IP blocks have been reloaded: {n4} IPv4 and {n6} IPv6 ranges." | `0` | `<cache>`, `<cache>.meta` written; `<cache>.failed` cleared | none |
| Download error (network, timeout, HTTP ≥ 400, non-HTTPS URL, cross-host redirect) | `error`: "Failed to reload Cloudflare's IP blocks: {reason}. The previously cached list was kept." | `1` | **unchanged** | `error` `laravel-cloudflare: reload failed` `{reason, source: reload, url}` |
| Validation error (bad entry, catch-all range, empty list, one family missing) | same as above, reason names the first offending entry | `1` | **unchanged** | same |
| Custom callback throws or returns invalid list | same as above | `1` | **unchanged** | same |

- The back-off marker does not apply: reload always attempts a download.
- `{n4}` and `{n6}` are counted by address family of the stored entries. With a custom callback, they reflect what the callback returned.

## `cloudflare:view`

| Situation | Output | Exit code |
|---|---|---|
| Cached list present | Table with an `Address` column, one row per range (unchanged), followed by `Last refreshed: {ISO-8601 UTC} ({n} days ago)` | `0` |
| Cached list present and older than `stale_after` | As above, plus `warn`: "The cached list is STALE (older than {stale_after} days). Run `php artisan cloudflare:reload` and check your scheduler." | `0` |
| Legacy list without metadata | Table, then `Last refreshed: unknown (cached by an earlier version)` | `0` |
| No cached list, or the cached value is invalid | `warn`: "No Cloudflare IP ranges are cached. Run `php artisan cloudflare:reload` to load them." | `0` |

`view` is read-only. It never downloads, never writes metadata, and never logs.
