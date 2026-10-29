<?php

declare(strict_types=1);

namespace BebelRouter;

use BebelRouter\Exception\PayloadTooLargeException;

final class Request
{
    public const MAX_BODY_BYTES = 1048576;

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @param array<string, mixed> $parsedBody
     * @param array<string, mixed> $server
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $files
     * @param array<string, string> $routeParams
     */
    public function __construct(
        private string $method,
        private string $uri,
        private array $query = [],
        private array $headers = [],
        private string $body = '',
        private array $parsedBody = [],
        private array $server = [],
        private array $cookies = [],
        private array $files = [],
        private array $routeParams = [],
    ) {
        if (strlen($this->body) > self::MAX_BODY_BYTES) {
            throw new PayloadTooLargeException('Corpo da requisição excede o limite permitido');
        }
        $this->method = strtoupper($this->method);
        $this->uri = $this->normalizeUri($this->uri);
    }

    public function withMethod(string $method): self
    {
        $clone = clone $this;
        $clone->method = strtoupper($method);
        return $clone;
    }

    /**
     * @param array<string, mixed>|null $server
     * @param array<string, mixed>|null $get
     * @param array<string, mixed>|null $post
     * @param array<string, mixed>|null $cookies
     * @param array<string, mixed>|null $files
     */
    public static function createFromGlobals(?array $server = null, ?array $get = null, ?array $post = null, ?array $cookies = null, ?array $files = null): self
    {
        $server = $server ?? $_SERVER;
        $get = $get ?? $_GET;
        $post = $post ?? $_POST;
        $cookies = $cookies ?? $_COOKIE;
        $files = $files ?? $_FILES;

        $method = $server['REQUEST_METHOD'] ?? 'GET';
        if (!is_string($method) || $method === '') {
            $method = 'GET';
        }
        $uri = $server['REQUEST_URI'] ?? '/';
        if (!is_string($uri) || $uri === '') {
            $uri = '/';
        }
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        $headers = [];
        foreach ($server as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
                $headers[$name] = is_string($v) ? $v : (string)$v;
            } elseif (in_array($k, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $k))));
                $headers[$name] = is_string($v) ? $v : (string)$v;
            }
        }
        $body = self::readBody();
        $parsedBody = $post;
        $ct = $headers['Content-Type'] ?? ($server['CONTENT_TYPE'] ?? '');
        if (is_string($ct) && str_contains(strtolower($ct), 'application/json') && $body !== '') {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $parsedBody = $decoded;
            }
        }
        return new self($method, $uri, $get, $headers, $body, $parsedBody, $server, $cookies, $files);
    }

    private static function readBody(): string
    {
        $stream = fopen('php://input', 'rb');
        if ($stream === false) {
            return '';
        }

        $body = stream_get_contents($stream, self::MAX_BODY_BYTES + 1);
        fclose($stream);

        if ($body === false) {
            return '';
        }
        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new PayloadTooLargeException('Corpo da requisição excede o limite permitido');
        }

        return $body;
    }

    private function normalizeUri(string $uri): string
    {
        return UriNormalizer::normalize($uri);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    /** @return array<string, mixed> */
    public function getQuery(): array
    {
        return $this->query;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        $key = $this->normalizeHeaderName($name);
        return $this->headers[$key] ?? null;
    }

    private function normalizeHeaderName(string $name): string
    {
        return str_replace(' ', '-', ucwords(strtolower(str_replace(['_', '-'], ' ', $name))));
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /** @return array<string, mixed> */
    public function getParsedBody(): array
    {
        return $this->parsedBody;
    }

    public function getInput(string $key, mixed $default = null): mixed
    {
        return $this->parsedBody[$key] ?? $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->parsedBody);
    }

    /** @return array<string, mixed> */
    public function getServer(): array
    {
        return $this->server;
    }

    /** @return array<string, mixed> */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /** @return array<string, mixed> */
    public function getFiles(): array
    {
        return $this->files;
    }

    /** @return array<string, string> */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    /** @param array<string, string> $params */
    public function withRouteParams(array $params): self
    {
        $clone = clone $this;
        $clone->routeParams = $params;
        return $clone;
    }

    /**
     * @param array<int, string> $trustedProxies
     */
    public function getIp(array $trustedProxies = []): ?string
    {
        $remoteAddr = $this->server['REMOTE_ADDR'] ?? null;
        if (!is_string($remoteAddr) || filter_var($remoteAddr, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        // X-Forwarded-For is only honoured when the direct peer is a trusted proxy.
        if ($trustedProxies === [] || !$this->isTrustedProxy($remoteAddr, $trustedProxies)) {
            return $remoteAddr;
        }

        $xff = $this->getHeader('X-Forwarded-For');
        if (!is_string($xff) || $xff === '') {
            return $remoteAddr;
        }

        $client = $remoteAddr;
        foreach (array_reverse(explode(',', $xff)) as $hop) {
            $hop = trim($hop);
            if (filter_var($hop, FILTER_VALIDATE_IP) === false) {
                continue;
            }
            $client = $hop;
            if (!$this->isTrustedProxy($hop, $trustedProxies)) {
                break;
            }
        }

        return $client;
    }

    /**
     * @param array<int, string> $trustedProxies
     */
    private function isTrustedProxy(string $ip, array $trustedProxies): bool
    {
        foreach ($trustedProxies as $proxy) {
            if (!is_string($proxy) || $proxy === '') {
                continue;
            }
            if (str_contains($proxy, '/')) {
                if ($this->ipInCidr($ip, $proxy)) {
                    return true;
                }
                continue;
            }
            if ($proxy === $ip) {
                return true;
            }
        }

        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $maskLength] = array_pad(explode('/', $cidr, 2), 2, '');
        if (!is_numeric($maskLength)) {
            return false;
        }

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $maskLength = (int) $maskLength;
        $maxBits = strlen($ipBin) * 8;
        if ($maskLength < 0 || $maskLength > $maxBits) {
            return false;
        }

        $bytes = intdiv($maskLength, 8);
        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }

        $bits = $maskLength % 8;
        if ($bits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $bits)) & 0xFF;
        return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
    }
}
