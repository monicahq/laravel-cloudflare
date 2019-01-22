# Trust Cloudflare's Proxies for Laravel

Add Cloudflare ip addresses to trusted proxies for Laravel.

[![Latest Version](https://img.shields.io/packagist/v/monicahq/laravel-cloudflare.svg?style=flat-square)](https://github.com/monicahq/laravel-cloudflare/releases)
[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bgithub.com%2Fmonicahq%2Flaravel-cloudflare.svg?type=shield)](https://app.fossa.io/projects/git%2Bgithub.com%2Fmonicahq%2Flaravel-cloudflare?ref=badge_shield)


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


[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bgithub.com%2Fmonicahq%2Flaravel-cloudflare.svg?type=large)](https://app.fossa.io/projects/git%2Bgithub.com%2Fmonicahq%2Flaravel-cloudflare?ref=badge_large)

# Citations

This package was inspired by [lukasz-adamski/laravel-cloudflare][1] and forked from [ogunkarakus/laravel-cloudflare][2].

[1]: https://github.com/lukasz-adamski/laravel-cloudflare
[2]: https://github.com/ogunkarakus/laravel-cloudflare