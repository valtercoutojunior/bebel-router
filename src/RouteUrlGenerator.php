<?php

declare(strict_types=1);

namespace BebelRouter;

final class RouteUrlGenerator
{
    public function __construct(private RouteCollection $routes)
    {
    }

    /**
     * @param array<string, mixed> $params
     * @throws \InvalidArgumentException
     */
    public function route(string $name, array $params = []): string
    {
        $route = $this->routes->getByName($name);
        if ($route === null) {
            throw new \InvalidArgumentException("Rota com nome não encontrada: {$name}");
        }
        $uri = $route->getUri();
        // substituir parâmetros obrigatórios e opcionais
        $uri = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_-]*)(\?)?\}#', function ($m) use ($params, $name) {
            $pname = $m[1];
            $optional = isset($m[2]) && $m[2] === '?';
            if (array_key_exists($pname, $params)) {
                $value = $params[$pname];
                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                } elseif ($value === null) {
                    $value = '';
                } elseif (!is_scalar($value) && !$value instanceof \Stringable) {
                    throw new \InvalidArgumentException("Parâmetro inválido para rota '{$name}': {$pname}");
                }

                return rawurlencode((string) $value);
            }
            if ($optional) {
                return '';
            }
            throw new \InvalidArgumentException("Parâmetro ausente para rota '{$name}': {$pname}");
        }, $uri);
        if ($uri === null) {
            throw new \InvalidArgumentException("Falha ao gerar URL para rota '{$name}'");
        }
        if ($uri === '') {
            $uri = '/';
        }
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }
        if (!str_starts_with($uri, '/')) {
            $uri = '/' . $uri;
        }
        return $uri;
    }
}
