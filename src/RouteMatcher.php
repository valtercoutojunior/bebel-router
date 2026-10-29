<?php

declare(strict_types=1);

namespace BebelRouter;

use BebelRouter\Exception\HttpException;
use BebelRouter\Exception\MethodNotAllowedException;
use BebelRouter\Exception\NotFoundHttpException;

final class RouteMatcher
{
    private const MAX_URI_LENGTH = 8192;
    private const MAX_CACHE_ENTRIES = 1024;

    /** @var array<string, string> */
    private array $cache = [];

    /**
     * @return array{route: Route, params: array<string, string>}
     */
    public function match(RouteCollection $collection, string $method, string $uri): array
    {
        $method = strtoupper($method);
        $uri = $this->normalizeUri($uri);

        $candidates = [];
        foreach ($collection->getRoutes() as $route) {
            $params = $this->matchUri($route, $uri);
            if ($params !== null) {
                $candidates[] = [
                    'route' => $route,
                    'params' => $params,
                    'static' => $this->isStatic($route),
                ];
            }
        }

        if ($candidates === []) {
            throw new NotFoundHttpException("Rota não encontrada: {$method} {$uri}");
        }

        usort($candidates, static function (array $a, array $b): int {
            if ($a['static'] === $b['static']) {
                return 0;
            }
            return $a['static'] ? -1 : 1;
        });

        foreach ($candidates as $candidate) {
            if ($this->methodAllowed($method, $candidate['route']->getMethods())) {
                return ['route' => $candidate['route'], 'params' => $candidate['params']];
            }
        }

        $allowed = [];
        foreach ($candidates as $candidate) {
            $this->collectAllowed($candidate['route']->getMethods(), $allowed);
        }

        throw new MethodNotAllowedException('Método não permitido', implode(', ', array_keys($allowed)));
    }

    /**
     * @return list<string>
     */
    public function allowedMethods(RouteCollection $collection, string $uri): array
    {
        $uri = $this->normalizeUri($uri);
        $allowed = [];

        foreach ($collection->getRoutes() as $route) {
            if ($this->matchUri($route, $uri) !== null) {
                $this->collectAllowed($route->getMethods(), $allowed);
            }
        }

        $methods = [];
        foreach ($allowed as $method => $_) {
            $methods[] = (string) $method;
        }

        return $methods;
    }

    private function normalizeUri(string $uri): string
    {
        $uri = UriNormalizer::normalize($uri);
        if (strlen($uri) > self::MAX_URI_LENGTH) {
            throw new HttpException('URI muito longa', 414);
        }

        return $uri;
    }

    /** @param list<string> $methods */
    private function methodAllowed(string $method, array $methods): bool
    {
        if (in_array($method, $methods, true)) {
            return true;
        }

        return $method === 'HEAD' && in_array('GET', $methods, true);
    }

    /**
     * @param list<string> $methods
     * @param array<string, bool> $allowed
     */
    private function collectAllowed(array $methods, array &$allowed): void
    {
        foreach ($methods as $method) {
            $allowed[$method] = true;
        }
        if (in_array('GET', $methods, true)) {
            $allowed['HEAD'] = true;
        }
    }

    private function isStatic(Route $route): bool
    {
        return !str_contains($route->getUri(), '{');
    }

    /**
     * @return array<string, string>|null
     */
    private function matchUri(Route $route, string $uri): ?array
    {
        $regex = $this->compile($route);
        if (@preg_match($regex, $uri, $matches) !== 1) {
            return null;
        }

        if (preg_match_all('#\{([a-zA-Z_][a-zA-Z0-9_-]*)(\?)?\}#', $route->getUri(), $names, PREG_SET_ORDER) === false) {
            return null;
        }

        $params = [];
        $index = 0;
        foreach ($names as $name) {
            $key = 'p' . $index;
            if (isset($matches[$key]) && $matches[$key] !== '') {
                $params[$name[1]] = $matches[$key];
            }
            $index++;
        }

        return $params;
    }

    private function compile(Route $route): string
    {
        $uri = $route->getUri();
        $wheres = $route->getWheres();
        $key = $uri . '|' . md5(serialize($wheres));

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $regex = $this->buildRegex($uri, $wheres);
        if (count($this->cache) >= self::MAX_CACHE_ENTRIES) {
            $this->cache = [];
        }
        $this->cache[$key] = $regex;

        return $regex;
    }

    /**
     * @param array<string, string> $wheres
     */
    private function buildRegex(string $uri, array $wheres): string
    {
        $parts = preg_split(
            '/(\{[a-zA-Z_][a-zA-Z0-9_-]*\??\})/',
            $uri,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );
        if ($parts === false) {
            return '#(?!)#';
        }

        $regex = '';
        $index = 0;
        foreach ($parts as $part) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_-]*)(\?)?\}$/', $part, $match) === 1) {
                $optional = ($match[2] ?? '') === '?';
                $pattern = $this->paramPattern($match[1], $wheres);
                $hasSlash = $regex !== '' && str_ends_with($regex, '/');

                if ($optional) {
                    if ($hasSlash) {
                        $regex = substr($regex, 0, -1);
                        $regex .= '(?:/(?P<p' . $index . '>' . $pattern . '))?';
                    } else {
                        $regex .= '(?:(?P<p' . $index . '>' . $pattern . '))?';
                    }
                } else {
                    $regex .= $hasSlash
                        ? '(?P<p' . $index . '>' . $pattern . ')'
                        : '/(?P<p' . $index . '>' . $pattern . ')';
                }
                $index++;
            } else {
                $regex .= preg_quote($part, '#');
            }
        }

        return '#^' . $regex . '\z#uD';
    }

    /**
     * @param array<string, string> $wheres
     */
    private function paramPattern(string $name, array $wheres): string
    {
        if (!array_key_exists($name, $wheres)) {
            return '[^/]+';
        }

        $pattern = $wheres[$name];
        if (!$this->isValidPattern($pattern)) {
            throw new \InvalidArgumentException("Regex inválida para o parâmetro '{$name}'");
        }

        return $pattern;
    }

    private function isValidPattern(string $pattern): bool
    {
        if ($pattern === '' || strlen($pattern) > 1024 || str_contains($pattern, '#')) {
            return false;
        }

        return @preg_match('#^(?:' . $pattern . ')\z#uD', '') !== false;
    }
}
