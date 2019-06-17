<?php

return [

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
