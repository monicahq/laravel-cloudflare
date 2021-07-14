<?php

namespace Monicahq\Cloudflare;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
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
     * Create a new instance of CloudflareProxies.
     *
     * @param \Illuminate\Contracts\Config\Repository $config
     */
    public function __construct(Repository $config)
    {
        $this->config = $config;
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
     * @param  string  $name requet name
     * @return array
     */
    protected function retrieve($name): array
    {
        try {
            $url = Str::of($this->config->get('laravelcloudflare.url'))->finish('/').$name;

            $response = Http::get($url);
        } catch (\Exception $e) {
            throw new UnexpectedValueException('Failed to load trust proxies from Cloudflare server.', 1, $e);
        }

        if ($response->status() != 200) {
            throw new UnexpectedValueException('Failed to load trust proxies from Cloudflare server.');
        }

        return array_filter(explode("\n", $response->body()));
    }
}
