<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable or disable the middleware proxy and the reload
    |--------------------------------------------------------------------------
    |
    | If you set it to false, the middleware and the reload command will never
    | be executed.
    |
    */

    'enabled' => (bool) env('LARAVEL_CLOUDFLARE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Replace current remote addr with Cf-Connecting-Ip header
    |--------------------------------------------------------------------------
    |
    | This replace the request ip with the value of the Cf-Connecting-Ip header.
    |
    */

    'replace_ip' => (bool) env('LARAVEL_CLOUDFLARE_REPLACE_IP', false),

    /*
    |--------------------------------------------------------------------------
    | Name of the cache to store values of the proxies
    |--------------------------------------------------------------------------
    |
    | This value is the key used in the cache (table, redis, etc.) to store the
    | values.
    |
    */

    'cache' => 'cloudflare.proxies',

    /*
    |--------------------------------------------------------------------------
    | Cloudflare main url
    |--------------------------------------------------------------------------
    |
    | This is the url for the cloudflare api.
    |
    */

    'url' => 'https://www.cloudflare.com',

    /*
    |--------------------------------------------------------------------------
    | Cloudflare uri for ipv4 ips response
    |--------------------------------------------------------------------------
    |
    | This is the path to get the values of ipv4 ips from Cloudflare.
    |
    */

    'ipv4-path' => 'ips-v4',

    /*
    |--------------------------------------------------------------------------
    | Cloudflare uri for ipv6 ips response
    |--------------------------------------------------------------------------
    |
    | This is the path to get the values of ipv6 ips from Cloudflare.
    |
    */

    'ipv6-path' => 'ips-v6',

];
