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

You don't need to add this package to your service providers.


2. Configure Middleware
- _Option 1_: **Add Cloudflare TrustProxies middleware and remove default one**

Replace `TrustProxies` middleware in `app/Http/Kernel.php`, by modifying the `middleware` array:

```diff
  protected $middleware = [
-     \App\Http\Middleware\TrustProxies::class,
+     \Monicahq\Cloudflare\Http\Middleware\TrustProxies::class
  ...

```

- _Option 2_: **Extend current middleware to use Cloudflare TrustProxies middleware**

Another option is to extend the `App\Http\Middleware\TrustProxies` class to `Monicahq\Cloudflare\Http\Middleware\TrustProxies`:

```diff
  namespace App\Http\Middleware;

  use Illuminate\Http\Request;
- use Illuminate\Http\Middleware\TrustProxies as Middleware;
+ use Monicahq\Cloudflare\Http\Middleware\TrustProxies as Middleware;

  class TrustProxies extends Middleware
  {
      ...
```

## Custom proxies callback

You can define your own proxies callback by calling the `LaravelCloudflare::getProxiesUsing()` to change the behavior of the `LaravelCloudflare::getProxies()` method.
This method should typically be called in the `boot` method of your `AppServiceProvider` class:

```php
use Monicahq\Cloudflare\LaravelCloudflare;
use Monicahq\Cloudflare\Facades\CloudflareProxies;

/**
 * Bootstrap any application services.
 *
 * @return void
 */
public function boot()
{
    LaravelCloudflare::getProxiesUsing(fn() => CloudflareProxies::load());
}
```


# How it works

The middleware uses [Illuminate\Http\Middleware\TrustProxies](https://github.com/laravel/framework/blob/8.x/src/Illuminate/Http/Middleware/TrustProxies.php) as a backend.

When the cloudflare ips are detected, they are used as trusted proxies.


# Refreshing the Cache

This package retrieves Cloudflare's IP blocks, and stores them in cache.
When request comes, the middleware will get Cloudflare's IP blocks from cache, and load them as trusted proxies.

You'll need to refresh the cloudflare cache regularely to always have up to date proxy.

Use the `cloudflare:reload` artisan command to refresh the IP blocks:

```sh
php artisan cloudflare:reload
```

## Suggestion: add the command in the schedule

Add a new line in `app/Console/Kernel.php`, in the `schedule` function:

```php
$schedule->command('cloudflare:reload')->daily();
```

# View current Cloudflare's IP blocks

You can use the `cloudflare:view` artisan command to see the cached IP blocks:

```sh
php artisan cloudflare:view
```

# Option: publish the package config file

If you want, you can publish the package config file to `config/laravelcloudflare.php`:

```sh
php artisan vendor:publish --provider="Monicahq\Cloudflare\TrustedProxyServiceProvider"
```

This file contains some configurations, but you may not need to change them normally.

## Running tests for your package

When running tests for your package, you generally don't need to get Cloudflare's proxy addresses.
You can deactivate the Laravel Cloudflare middleware by adding the following environment variable in
your `.env` or `phpunit.xml` file:

```
LARAVEL_CLOUDFLARE_ENABLED=false
```


# Compatibility

| Laravel  | [monicahq/laravel-cloudflare](https://github.com/monicahq/laravel-cloudflare) |
|----------|----------|
| 5.x-6.x  | <= 1.8.0 |
| 7.x-8.53 |  2.0.0   |
| 8.54-9.x | >= 3.0.0 |


# Citations

This package was inspired by [lukasz-adamski/laravel-cloudflare](https://github.com/lukasz-adamski/laravel-cloudflare) and forked from [ogunkarakus/laravel-cloudflare](https://github.com/ogunkarakus/laravel-cloudflare).


# License

Author: [Alexis Saettler](https://github.com/asbiin)

This project is part of [MonicaHQ](https://github.com/monicahq/).

Copyright © 2019–2022.

Licensed under the MIT License. [View license](LICENSE.md).
