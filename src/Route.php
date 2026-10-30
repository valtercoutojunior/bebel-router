<?php

declare(strict_types=1);

namespace BebelRouter;

final class Route
{
    /** @var array<int, string> */
    private array $methods = [];
    private string $uri;
    private mixed $action;
    /** @var array<int, string|callable|object> */
    private array $middleware = [];
    private ?string $name = null;
    private string $namePrefix = '';
    /** @var array<string, string> */
    private array $wheres = [];
    /** @var null|callable(Route, string): void */
    private $onNameChange = null;

    /** @param array<int, string>|string $methods */
    public function __construct(array|string $methods, string $uri, mixed $action)
    {
        $this->methods = is_array($methods) ? array_map('strtoupper', $methods) : [strtoupper($methods)];
        $this->uri = $this->normalizeUri($uri);
        $this->action = $action;
    }

    /**
     * @param string $uri
     *  @return string
     */
    private function normalizeUri(string $uri): string
    {
        return UriNormalizer::normalizePattern($uri);
    }

    public function setUri(string $uri): self
    {
        $this->uri = $this->normalizeUri($uri);
        return $this;
    }

    /**
     * @return string[]
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * @return mixed
     */
    public function getAction(): mixed
    {
        return $this->action;
    }

    /**
     * @param array<int, string|callable|object>|string $middleware
     * @return $this
     */
    public function middleware(array|string $middleware): self
    {
        $m = is_array($middleware) ? $middleware : [$middleware];
        $this->middleware = array_merge($this->middleware, $m);
        return $this;
    }

    /**
     * @return array<int, string|callable|object>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function setNamePrefix(string $prefix): self
    {
        $this->namePrefix = $prefix;
        return $this;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function name(string $name): self
    {
        $fullName = $this->namePrefix . $name;

        if ($this->onNameChange !== null) {
            ($this->onNameChange)($this, $fullName);
        }
        $this->name = $fullName;
        return $this;
    }

    /** @param callable(Route, string): void $listener */
    public function onNameChange(callable $listener): void
    {
        $this->onNameChange = $listener;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string $param
     * @param string $regex
     * @return $this
     */
    public function where(string $param, string $regex): self
    {
        if (
            $regex === ''
            || strlen($regex) > 1024
            || str_contains($regex, '#')
            || @preg_match('#^(?:' . $regex . ')\z#uD', '') === false
        ) {
            throw new \InvalidArgumentException("Regex inválida para o parâmetro '{$param}'");
        }

        $this->wheres[$param] = $regex;
        return $this;
    }

    /**
     * @param string $param
     */
    public function whereAlpha(string $param): self
    {
        return $this->where($param, '[A-Za-z]+');
    }

    /**
     * @param string $param
     */
    public function whereNumber(string $param): self
    {
        return $this->where($param, '[0-9]+');
    }

    /**
     * @param string $param
     */
    public function whereAlphaNumeric(string $param): self
    {
        return $this->where($param, '[A-Za-z0-9]+');
    }

    /**
     * @return array<string, string>
     */
    public function getWheres(): array
    {
        return $this->wheres;
    }
}
