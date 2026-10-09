# Contract: Configuration (`config/laravelcloudflare.php`)

Existing keys keep their names, types and defaults (FR-027). New keys are additive. A published config file from 4.1 that lacks them still works, because `mergeConfigFrom` supplies the defaults.

| Key | Env var | Type | Default | Status | Meaning |
|---|---|---|---|---|---|
| `enabled` | `LARAVEL_CLOUDFLARE_ENABLED` | bool | `true` | existing | Master switch. When `false`: no download, no Cloudflare trust, **no `Cf-Connecting-Ip` replacement** (changed: header replacement used to run even when disabled) |
| `replace_ip` | `LARAVEL_CLOUDFLARE_REPLACE_IP` | bool | `false` | existing | Replace `REMOTE_ADDR` with `Cf-Connecting-Ip`, **only** for requests whose connecting address is within the trusted Cloudflare ranges and whose header is one valid IP (changed: used to apply to any sender) |
| `cache` | — | string | `cloudflare.proxies` | existing | Base cache key; derived keys are `<cache>.meta`, `.failed`, `.lock`, `.stale-warned` |
| `url` | — | string | `https://www.cloudflare.com` | existing | Source base URL. **Must be `https://`**, otherwise downloads fail with `InvalidProxyListException` |
| `ipv4-path` | — | string | `ips-v4` | existing | IPv4 list path |
| `ipv6-path` | — | string | `ips-v6` | existing | IPv6 list path |
| `timeout` | `LARAVEL_CLOUDFLARE_TIMEOUT` | int (seconds, ≥ 1) | `5` | **new** | Maximum duration of one download attempt (FR-011); values < 1 are treated as 1 |
| `retry_after` | `LARAVEL_CLOUDFLARE_RETRY_AFTER` | int (seconds, ≥ 1) | `60` | **new** | Back-off after a failed download during a visitor request (FR-013) |
| `stale_after` | `LARAVEL_CLOUDFLARE_STALE_AFTER` | int (days, ≥ 1) | `7` | **new** | Age after which the cached list is reported stale (FR-030) |

Not configurable on purpose: the minimum prefix lengths (/8 IPv4, /16 IPv6), so they cannot be loosened.
