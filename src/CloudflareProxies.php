<?php

namespace Monicahq\Cloudflare;

use GuzzleHttp\Client;
use UnexpectedValueException;

class CloudflareProxies
{
    public const IP_VERSION_4 = 1 << 0;

    public const IP_VERSION_6 = 1 << 1;

    public const IP_VERSION_ANY = self::IP_VERSION_4 | self::IP_VERSION_6;

    /**
     * Retrieve Cloudflare proxies list.
     *
     * @param  int  $type
     *
     * @return array
     */
    public function load($type = self::IP_VERSION_ANY) : array
    {
        $proxies = [];

        if ($type & self::IP_VERSION_4) {
            $proxies = $this->retrieve('ips-v4');
        }

        if ($type & self::IP_VERSION_6) {
            $proxies = array_merge($proxies, $this->retrieve('ips-v6'));
        }

        return $proxies;
    }

    /**
     * Retrieve requested proxy list by name.
     *
     * @param  string  $name requet name
     *
     * @return array
     */
    protected function retrieve($name) : array
    {
        try {
            $client = new Client(['base_uri' => 'https://www.cloudflare.com/']);

            $response = $client->request('GET', $name);
        } catch (\Exception $e) {
            throw new UnexpectedValueException('Failed to load trust proxies from Cloudflare server.', $e);
        }

        if ($response->getStatusCode() != 200) {
            throw new UnexpectedValueException('Failed to load trust proxies from Cloudflare server.');
        }

        return array_filter(explode("\n", $response->getBody()));
    }
}
