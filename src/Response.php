<?php

declare(strict_types=1);

namespace BebelRouter;

final class Response
{
    /** @var array<string, string> */
    private const DEFAULT_HEADERS = [
        'Content-Type' => 'text/html; charset=utf-8',
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'no-referrer',
    ];

    /** @var array<string, string> */
    private array $headers;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private string $content = '',
        private int $statusCode = 200,
        array $headers = [],
    ) {
        $this->headers = self::mergeHeaders(self::DEFAULT_HEADERS, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function make(string $content = '', int $status = 200, array $headers = []): self
    {
        return new self($content, $status, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        $headers = array_merge(['Content-Type' => 'application/json; charset=utf-8'], $headers);
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
        $content = json_encode($data, $flags);
        if ($content === false) {
            $content = '{"error":"json_encode_failed"}';
        }

        return new self($content, $status, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function text(string $content, int $status = 200, array $headers = []): self
    {
        $headers = array_merge(['Content-Type' => 'text/plain; charset=utf-8'], $headers);

        return new self($content, $status, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function html(string $content, int $status = 200, array $headers = []): self
    {
        $headers = array_merge(['Content-Type' => 'text/html; charset=utf-8'], $headers);

        return new self($content, $status, $headers);
    }

    /**
     * @param array<string, string> $headers
     * @param array<int, string> $allowedHosts
     */
    public static function redirect(string $to, int $status = 302, array $headers = [], array $allowedHosts = []): self
    {
        $to = self::validateRedirectTarget($to, $allowedHosts);
        $headers = array_merge(['Location' => $to], $headers);

        return new self('', $status, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function empty(int $status = 204, array $headers = []): self
    {
        return new self('', $status, $headers);
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeader(string $name, string $value): self
    {
        self::assertHeaderName($name);
        self::assertHeaderValue($name, $value);
        $this->headers[$name] = $value;

        return $this;
    }

    public function withStatus(int $status): self
    {
        $clone = clone $this;
        $clone->statusCode = $status;

        return $clone;
    }

    public function withContent(string $content): self
    {
        $clone = clone $this;
        $clone->content = $content;

        return $clone;
    }

    public function send(): void
    {
        if (headers_sent()) {
            echo $this->content;
            return;
        }

        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            self::assertHeaderName($name);
            self::assertHeaderValue($name, $value);
            header($name . ': ' . $value, true);
        }
        echo $this->content;
    }

    /**
     * @param array<string, string> $defaults
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private static function mergeHeaders(array $defaults, array $headers): array
    {
        $result = [];
        $overridden = [];

        foreach ($headers as $name => $value) {
            self::assertHeaderName($name);
            self::assertHeaderValue($name, $value);
            $overridden[strtolower($name)] = true;
            $result[$name] = $value;
        }

        foreach ($defaults as $name => $value) {
            if (!isset($overridden[strtolower($name)])) {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    private static function assertHeaderName(string $name): void
    {
        if (preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/', $name) !== 1) {
            throw new \InvalidArgumentException('Nome de header inválido');
        }
    }

    private static function assertHeaderValue(string $name, string $value): void
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new \InvalidArgumentException("Valor inválido para o header '{$name}'");
        }
    }

    /** @param array<int, string> $allowedHosts */
    private static function validateRedirectTarget(string $to, array $allowedHosts): string
    {
        if ($to === '' || preg_match('/[\x00-\x1F\x7F]/', $to) === 1) {
            throw new \InvalidArgumentException('Destino de redirecionamento inválido');
        }

        $to = str_replace('\\', '/', $to);

        if (str_starts_with($to, '/') && !str_starts_with($to, '//')) {
            return $to;
        }

        $parts = parse_url($to);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('Destino de redirecionamento inválido');
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Esquema de redirecionamento não permitido');
        }

        if ($allowedHosts === []) {
            throw new \InvalidArgumentException('Redirecionamento absoluto exige allowlist de hosts');
        }

        $host = strtolower($parts['host']);
        $allowed = array_map('strtolower', $allowedHosts);
        if (!in_array($host, $allowed, true)) {
            throw new \InvalidArgumentException('Host de redirecionamento não permitido');
        }

        return $to;
    }
}
