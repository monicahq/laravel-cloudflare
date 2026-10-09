<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable or disable the middleware proxy and the reload
    |--------------------------------------------------------------------------
    |
    | If you set it to false, the middleware and the reload command will never
    | be executed: nothing is downloaded, Cloudflare is not trusted and the
    | Cf-Connecting-Ip header is never used.
    |
    */

    'enabled' => (bool) env('LARAVEL_CLOUDFLARE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Replace current remote addr with Cf-Connecting-Ip header
    |--------------------------------------------------------------------------
    |
    | This replace the request ip with the value of the Cf-Connecting-Ip header.
    | The header is only used when the request comes from Cloudflare's IP
    | blocks and holds a single valid IP address.
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
    | This is the url for the cloudflare api. It must use https://, otherwise
    | the IP blocks will not be downloaded.
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

    /*
    |--------------------------------------------------------------------------
    | Download timeout
    |--------------------------------------------------------------------------
    |
    | Maximum number of seconds a single download of the IP blocks may take.
    |
    */

    'timeout' => (int) env('LARAVEL_CLOUDFLARE_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Retry delay after a failed download
    |--------------------------------------------------------------------------
    |
    | When the IP blocks cannot be downloaded while serving a request, wait
    | this number of seconds before trying again.
    |
    */

    'retry_after' => (int) env('LARAVEL_CLOUDFLARE_RETRY_AFTER', 60),

    /*
    |--------------------------------------------------------------------------
    | Stale list warning
    |--------------------------------------------------------------------------
    |
    | Number of days after which the cached IP blocks are reported as stale.
    | A stale list is still used, but a warning is logged.
    |
    */

    'stale_after' => (int) env('LARAVEL_CLOUDFLARE_STALE_AFTER', 7),

];
