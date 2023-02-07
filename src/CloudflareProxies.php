<?php

namespace Monicahq\Cloudflare;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use UnexpectedValueException;

class CloudflareProxies
{
    public const IP_VERSION_4 = 1 << 0;

    public const IP_VERSION_6 = 1 << 1;

    public const IP_VERSION_ANY = self::IP_VERSION_4 | self::IP_VERSION_6;

    /**
     * The config repository instance.
     *
     * @var Repository
     */
    protected $config;

    /**
     * The http factory instance.
     *
     * @var HttpClient
     */
    protected $http;

    /**
     * Create a new instance of CloudflareProxies.
     *
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @param  \Illuminate\Http\Client\Factory  $http
     */
    public function __construct(Repository $config, HttpClient $http)
    {
        $this->config = $config;
        $this->http = $http;
    }

    /**
     * Retrieve Cloudflare proxies list.
     *
     * @param  int  $type
     * @return array
     */
    public function load($type = self::IP_VERSION_ANY): array
    {
        $proxies = [];

        if ((bool) ($type & self::IP_VERSION_4)) {
            $proxies = $this->retrieve($this->config->get('laravelcloudflare.ipv4-path'));
        }

        if ((bool) ($type & self::IP_VERSION_6)) {
            $proxies6 = $this->retrieve($this->config->get('laravelcloudflare.ipv6-path'));
            $proxies = array_merge($proxies, $proxies6);
        }

        return $proxies;
    }

    /**
     * Retrieve requested proxy list by name.
     *
     * @param  string  $name  requet name
     * @return array
     */
    protected function retrieve($name): array
    {
        try {
            $url = Str::of($this->config->get('laravelcloudflare.url'))->finish('/').$name;

            $response = Http::get($url)->throw();
        } catch (\Exception $e) {
            throw new UnexpectedValueException('Failed to load trust proxies from Cloudflare server.', 1, $e);
        }

        return array_filter(explode("\n", $response->body()));
    }
}
