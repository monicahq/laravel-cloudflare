<?php

namespace Monicahq\Cloudflare;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use UnexpectedValueException;

class CloudflareProxies
{
    /**
     * Use IPv4 addresses.
     *
     * @var int
     */
    public const IP_VERSION_4 = 1 << 0;

    /**
     * Use IPv6 addresses.
     *
     * @var int
     */
    public const IP_VERSION_6 = 1 << 1;

    /**
     * Use any IP addresses.
     *
     * @var int
     */
    public const IP_VERSION_ANY = self::IP_VERSION_4 | self::IP_VERSION_6;

    /**
     * Create a new instance of CloudflareProxies.
     */
    public function __construct(
        protected Repository $config,
        protected HttpClient $http
    ) {}

    /**
     * Retrieve Cloudflare proxies list.
     */
    public function load(int $type = self::IP_VERSION_ANY): array
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
     */
    protected function retrieve(string $name): array
    {
        try {
            $url = Str::of($this->config->get('laravelcloudflare.url', 'https://api.cloudflare.com/client/v4/ips'))->finish('/').$name;

            $response = Http::get($url)->throw();
        } catch (\Exception $e) {
            throw new UnexpectedValueException('Failed to load trust proxies from Cloudflare server.', 1, $e);
        }

        return array_filter($this->parseIps($response->json()));
    }

    /**
     * Parses and combines IPv4 and IPv6 CIDRs from the given JSON array.
     *
     * @param array{
     *     result: array{
     *         ipv4_cidrs: string[],
     *         ipv6_cidrs: string[],
     *         etag: string,
     *     },
     *     success: bool,
     *     errors: array,
     *     messages: array
     * } $json The input array containing 'ipv4_cidrs' and 'ipv6_cidrs' keys.
     * @return array The combined array of IPv4 and IPv6 CIDRs.
     */
    protected function parseIps(array $json): array
    {
        return array_merge($json['ipv4_cidrs'], $json['ipv6_cidrs']);
    }
}
