# Trust Cloudflare's Proxies for Laravel

Add Cloudflare ip addresses to trusted proxies for Laravel.

# Support

This package supports Laravel 5.6 or newer.

# Usage

Include this package to your project dependencies.

Laravel "auto-discovery" feature, discovers automatically this package.

You don't need to add this package to your service providers.

Open "**app/Http/Kernel.php**" then add this line to "**middleware**" array.

`Monicahq\Cloudflare\Http\Middleware\TrustProxies`

# Refreshing the Cache

This package basically retrieves Cloudflare's IP blocks, and stores in cache.

When request comes, loads Cloudflare's IP blocks to trusted proxies.

That's why, you'll need to every day refresh the cache.

You can use the following command for this.

``php artisan cloudflare:reload``

You can use the following command to see the cached IP blocks.

``php artisan cloudflare:view``

# License

This repository licensed under the MIT license.

# Citations

This package was inspired by [the package][1] and forked from [this package][2].

[1]: https://github.com/lukasz-adamski/laravel-cloudflare
[2]: https://github.com/ogunkarakus/laravel-cloudflare