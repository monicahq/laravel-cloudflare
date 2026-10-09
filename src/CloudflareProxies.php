<?php

namespace Monicahq\Cloudflare;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Monicahq\Cloudflare\Exceptions\InvalidProxyListException;
use Throwable;

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
     * Maximum number of redirects followed, on the same host only.
     *
     * @var int
     */
    private const MAX_REDIRECTS = 3;

    /**
     * The proxy list validator.
     */
    protected ProxyListValidator $validator;

    /**
     * Create a new instance of CloudflareProxies.
     */
    public function __construct(
        protected Repository $config,
        protected HttpClient $http,
        ?ProxyListValidator $validator = null
    ) {
        $this->validator = $validator ?? new ProxyListValidator;
    }

    /**
     * Retrieve Cloudflare proxies list.
     *
     * @return list<string>
     *
     * @throws InvalidProxyListException
     */
    public function load(int $type = self::IP_VERSION_ANY): array
    {
        $proxies = [];

        if ((bool) ($type & self::IP_VERSION_4)) {
            $proxies = $this->retrieve($this->config->get('laravelcloudflare.ipv4-path'));
        }

        if ((bool) ($type & self::IP_VERSION_6)) {
            $proxies = array_merge($proxies, $this->retrieve($this->config->get('laravelcloudflare.ipv6-path')));
        }

        return array_values(array_unique($proxies));
    }

    /**
     * Retrieve requested proxy list by name.
     *
     * @return list<string>
     *
     * @throws InvalidProxyListException
     */
    protected function retrieve(string $name): array
    {
        $url = ((string) Str::of($this->config->get('laravelcloudflare.url', 'https://www.cloudflare.com/'))->finish('/')).$name;

        try {
            $response = $this->get($url);
        } catch (InvalidProxyListException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw InvalidProxyListException::download($e->getMessage(), $e);
        }

        $lines = preg_split('/\r?\n/', $response->body());

        return $this->validator->validate($lines === false ? [] : $lines);
    }

    /**
     * Get the url, following redirects on the same https host only.
     *
     * @throws Throwable
     */
    private function get(string $url): Response
    {
        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw InvalidProxyListException::insecureSource($url);
        }

        $host = parse_url($url, PHP_URL_HOST);
        $timeout = max(1, (int) $this->config->get('laravelcloudflare.timeout', 5));

        for ($redirects = 0; ; $redirects++) {
            $response = $this->ensureResponse($this->http
                ->createPendingRequest()
                ->withOptions(['allow_redirects' => false])
                ->timeout($timeout)
                ->connectTimeout(min(3, $timeout))
                ->get($url));

            if (! $response->redirect()) {
                return $response->throw();
            }

            if ($redirects >= self::MAX_REDIRECTS) {
                throw InvalidProxyListException::download('too many redirects');
            }

            $location = $response->header('Location');
            $url = str_starts_with($location, '/') ? "https://{$host}{$location}" : $location;

            if (parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== $host) {
                throw InvalidProxyListException::download('redirect to another host or to plain http is not allowed');
            }
        }
    }

    /**
     * Ensure the request was sent synchronously.
     *
     * @throws InvalidProxyListException
     */
    private function ensureResponse(mixed $response): Response
    {
        if (! $response instanceof Response) {
            throw InvalidProxyListException::download('unexpected asynchronous response');
        }

        return $response;
    }
}
