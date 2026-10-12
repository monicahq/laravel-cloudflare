# Trust Cloudflare's Proxies for Laravel

Add Cloudflare ip addresses to trusted proxies for Laravel.

[![Latest Version](https://img.shields.io/packagist/v/monicahq/laravel-cloudflare?style=flat-square&label=Latest%20Version)](https://github.com/monicahq/laravel-cloudflare/releases)
[![Downloads](https://img.shields.io/packagist/dt/monicahq/laravel-cloudflare?style=flat-square&label=Downloads)](https://packagist.org/packages/monicahq/laravel-cloudflare)
[![Workflow Status](https://img.shields.io/github/workflow/status/monicahq/laravel-cloudflare/Unit%20tests?style=flat-square&label=Workflow%20Status)](https://github.com/monicahq/laravel-cloudflare/actions?query=branch%3Amain)
[![Quality Gate](https://img.shields.io/sonar/quality_gate/monicahq_laravel-cloudflare?server=https%3A%2F%2Fsonarcloud.io&style=flat-square&label=Quality%20Gate)](https://sonarcloud.io/dashboard?id=monicahq_laravel-cloudflare)
[![Coverage Status](https://img.shields.io/sonar/coverage/monicahq_laravel-cloudflare?server=https%3A%2F%2Fsonarcloud.io&style=flat-square&label=Coverage%20Status)](https://sonarcloud.io/dashboard?id=monicahq_laravel-cloudflare)


# Installation

1. Install package using composer:
```
composer require monicahq/laravel-cloudflare
```


1. Configure Middleware

Replace `TrustProxies` middleware in your `bootstrap/app.php` file:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->replace(
        \Illuminate\Http\Middleware\TrustProxies::class,
        \Monicahq\Cloudflare\Http\Middleware\TrustProxies::class
    );
})
```

## Custom proxies callback

You can define your own proxies callback by calling the `LaravelCloudflare::getProxiesUsing()` to change the behavior of the `LaravelCloudflare::getProxies()` method.
This method should typically be called in the `boot` method of your `AppServiceProvider` class:

```php
use Illuminate\Support\ServiceProvider;
use Monicahq\Cloudflare\LaravelCloudflare;
use Monicahq\Cloudflare\Facades\CloudflareProxies;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        LaravelCloudflare::getProxiesUsing(fn() => CloudflareProxies::load());
    }
}
```


# How it works

The middleware uses [Illuminate\Http\Middleware\TrustProxies](https://github.com/laravel/framework/blob/8.x/src/Illuminate/Http/Middleware/TrustProxies.php) as a backend.

When the cloudflare ips are detected, they are used as trusted proxies.

- Only valid IP addresses and network ranges are trusted. A downloaded list containing anything else (an error page, a catch-all range such as `0.0.0.0/0`, …) is rejected as a whole.
- The trusted proxies are computed for each request: they are your own trusted proxies (`TrustProxies::at()` or `trustedproxy.proxies`) plus Cloudflare's IP blocks.
- If Cloudflare's IP blocks cannot be loaded, the request is still served, but without trusting Cloudflare, and a warning is logged.


# Refreshing the Cache

This package retrieves Cloudflare's IP blocks, and stores them in cache.
When request comes, the middleware will get Cloudflare's IP blocks from cache, and load them as trusted proxies.

You'll need to refresh the cloudflare cache regularely to always have up to date proxy.

Use the `cloudflare:reload` artisan command to refresh the IP blocks:

```sh
php artisan cloudflare:reload
```

## Suggestion: add the reload command in the schedule

Add a schedule to your `routes/console.php` file to refresh the cache, for instance:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('cloudflare:reload')->daily();
```

## Failure behaviour

- `cloudflare:reload` exits with code `1` when the IP blocks cannot be downloaded or are invalid. The previously cached list is **kept**, and an error is logged. Use it to be alerted, for instance:

  ```php
  Schedule::command('cloudflare:reload')->daily()->emailOutputOnFailure('ops@example.com');
  ```

- When no list is cached yet, the first request downloads it (at most one download at a time, limited to `timeout` seconds). If that download fails, requests are served without trusting Cloudflare, and no new download is attempted for `retry_after` seconds.
- When the cached list is older than `stale_after` days, a warning is logged (at most once per hour). The list is still used.

# View current Cloudflare's IP blocks

You can use the `cloudflare:view` artisan command to see the cached IP blocks:

```sh
php artisan cloudflare:view
```

The command also shows when the list was last refreshed, and flags it as `STALE` when it is older than `stale_after` days.

# Option: publish the package config file

If you want, you can publish the package config file to `config/laravelcloudflare.php`:

```sh
php artisan vendor:publish --provider="Monicahq\Cloudflare\TrustedProxyServiceProvider"
```

This file contains some configurations, but you may not need to change them normally.

| Key | Environment variable | Default | Description |
|-----|----------------------|---------|-------------|
| `enabled` | `LARAVEL_CLOUDFLARE_ENABLED` | `true` | Enable the middleware and the reload command. When disabled, nothing is downloaded and no header is replaced. |
| `replace_ip` | `LARAVEL_CLOUDFLARE_REPLACE_IP` | `false` | Replace the request IP with the `Cf-Connecting-Ip` header, only for requests coming from Cloudflare. |
| `url` | | `https://www.cloudflare.com` | Source of Cloudflare's IP blocks. Must use `https://`. |
| `timeout` | `LARAVEL_CLOUDFLARE_TIMEOUT` | `5` | Maximum duration of a download, in seconds. |
| `retry_after` | `LARAVEL_CLOUDFLARE_RETRY_AFTER` | `60` | Delay before retrying a failed download during a request, in seconds. |
| `stale_after` | `LARAVEL_CLOUDFLARE_STALE_AFTER` | `7` | Age of the cached list, in days, after which a warning is logged. |

## Running tests for your package

When running tests for your package, you generally don't need to get Cloudflare's proxy addresses.
You can deactivate the Laravel Cloudflare middleware by adding the following environment variable in
your `.env` or `phpunit.xml` file:

```
LARAVEL_CLOUDFLARE_ENABLED=false
```


# Security considerations

- The `Cf-Connecting-Ip` header (`replace_ip` option) and the `X-Forwarded-*` headers are only trusted when the request comes from one of Cloudflare's IP blocks. Anyone reaching your server directly cannot spoof their IP address this way.
- Still, restrict your origin server to Cloudflare's traffic when you can: firewall allow-list of Cloudflare's IP blocks, or [Authenticated Origin Pulls](https://developers.cloudflare.com/ssl/origin-configuration/authenticated-origin-pull/).
- Never set `trustedproxy.proxies` (or `TrustProxies::at()`) to `*` behind Cloudflare: it trusts forwarded headers from anyone.
- Schedule `cloudflare:reload` and watch for its failures, so the list follows Cloudflare's changes.

To report a vulnerability, see [SECURITY.md](SECURITY.md).


# Upgrading to 4.2

Version 4.2 fixes security and reliability issues. Most applications need no change, but note:

- `Cf-Connecting-Ip` is now only used when the request comes from Cloudflare's IP blocks, and only if it holds a single valid IP address.
- When `enabled` is `false`, the `Cf-Connecting-Ip` header is no longer used either.
- Downloaded lists, and lists returned by `LaravelCloudflare::getProxiesUsing()`, are validated: entries must be IP addresses or ranges no wider than `/8` (IPv4) or `/16` (IPv6). An invalid list is rejected.
- The `url` option must use `https://`.
- `cloudflare:reload` exits with code `1` on failure and keeps the previously cached list.
- Requests are served without trusting Cloudflare when the list cannot be loaded, instead of failing.
- New options: `timeout`, `retry_after`, `stale_after`.


# Compatibility

| Laravel  | [monicahq/laravel-cloudflare](https://github.com/monicahq/laravel-cloudflare) |
|----------|----------|
| 5.x-6.x  | <= 1.8 |
| 7.x-8.53 |  2.x   |
| 8.54-12.0 | 3.x |
| >= 11.0 | >= 4.x |


# Citations

This package was inspired by [lukasz-adamski/laravel-cloudflare](https://github.com/lukasz-adamski/laravel-cloudflare) and forked from [ogunkarakus/laravel-cloudflare](https://github.com/ogunkarakus/laravel-cloudflare).


# License

Author: [Alexis Saettler](https://github.com/asbiin)

This project is part of [MonicaHQ](https://github.com/monicahq/).

Copyright © 2019–2025.

Licensed under the MIT License. [View license](LICENSE.md).
