<?php

namespace Monicahq\Cloudflare;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Contracts\Config\Repository;
use UnexpectedValueException;

class CloudflareProxies
{
    public const IP_VERSION_4 = 1 << 0;

    public const IP_VERSION_6 = 1 << 1;

    public const IP_VERSION_ANY = self::IP_VERSION_4 | self::IP_VERSION_6;

    /**
     * The config repository instance.
     *
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;

    /**
     * The GuzzleClient used to make requests.
     *
     * @var GuzzleClient
     */
    protected $client;

    /**
     * Create a new instance of CloudflareProxies.
     *
     * @param \Illuminate\Contracts\Config\Repository $config
     * @param GuzzleClient  $client  Client used for http request
     */
    public function __construct(Repository $config, GuzzleClient $client = null)
    {
        $this->config = $config;
        $this->client = $client ?: new GuzzleClient();
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

        if ($type & self::IP_VERSION_4) {
            $proxies = $this->retrieve($this->config->get('laravelcloudflare.ipv4-path'));
        }

        if ($type & self::IP_VERSION_6) {
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
            $url = $this->config->get('laravelcloudflare.url').'/'.$name;

            $response = $this->client->request('GET', $url);
        } catch (\Exception $e) {
            throw new UnexpectedValueException('Failed to load trust proxies from Cloudflare server.', 1, $e);
        }

        if ($response->getStatusCode() != 200) {
            throw new UnexpectedValueException('Failed to load trust proxies from Cloudflare server.');
        }

        return array_filter(explode("\n", (string) $response->getBody()));
    }
}
