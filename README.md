# Trust Cloudflare's Proxies for Laravel

Add Cloudflare ip addresses to trusted proxies for Laravel.

[![Latest Version](https://img.shields.io/packagist/v/monicahq/laravel-cloudflare.svg?style=flat-square)](https://github.com/monicahq/laravel-cloudflare/releases)
[![Downloads](https://img.shields.io/packagist/dt/monicahq/laravel-cloudflare.svg?style=flat-square)](https://packagist.org/packages/monicahq/laravel-cloudflare)
[![Circle CI](https://img.shields.io/circleci/project/github/monicahq/laravel-cloudflare.svg?style=flat-square)](https://circleci.com/gh/monicahq/laravel-cloudflare/tree/master)
[![Coverage Status](https://img.shields.io/sonar/https/sonarcloud.io/monicahq_laravel-cloudflare/coverage.svg?style=flat-square)](https://sonarcloud.io/dashboard?id=monicahq_laravel-cloudflare)


# Installation

Install using composer:
```
composer require monicahq/laravel-cloudflare
```

You don't need to add this package to your service providers.

Add the middleware in `app/Http/Kernel.php`, adding a new line in the `middleware` array:

```php
\Monicahq\Cloudflare\Http\Middleware\TrustProxies::class
```

# Support

This package supports Laravel 5.6 or newer.

# Refreshing the Cache

This package basically retrieves Cloudflare's IP blocks, and stores in cache.

When request comes, loads Cloudflare's IP blocks to trusted proxies.

That's why, you'll need to every day refresh the cache.

You can use the following command for this.

```sh
php artisan cloudflare:reload
```

## Suggestion: add the command in the schedule.

Add a new line in `app/Console/Kernel.php`, in the `schedule` function:

```php
$schedule->command('cloudflare:reload')->daily();
```


# View current Cloudflare's IP blocks

You can use the following command to see the cached IP blocks.

```sh
php artisan cloudflare:view
```

# License

This repository licensed under the MIT license.

# Citations

This package was inspired by [lukasz-adamski/laravel-cloudflare][1] and forked from [ogunkarakus/laravel-cloudflare][2].

[1]: https://github.com/lukasz-adamski/laravel-cloudflare
[2]: https://github.com/ogunkarakus/laravel-cloudflare
