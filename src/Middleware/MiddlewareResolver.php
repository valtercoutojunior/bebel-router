<?php

declare(strict_types=1);

namespace BebelRouter\Middleware;

use BebelRouter\Router;

final class MiddlewareResolver
{
    public function __construct(private Router $router)
    {
    }

    /**
     * @param array<int, string|callable|object> $middlewares
     * @return array<int, string|callable|object>
     */
    public function resolve(array $middlewares): array
    {
        $resolved = [];
        $aliases = $this->router->getMiddlewareAliases();

        foreach ($middlewares as $middleware) {
            if (!is_string($middleware)) {
                $resolved[] = $middleware;
                continue;
            }

            $name = $middleware;
            if (str_contains($middleware, ':')) {
                [$name] = explode(':', $middleware, 2);
            }

            if (isset($aliases[$name])) {
                $resolved[] = $aliases[$name];
                continue;
            }

            if (class_exists($middleware) || is_callable($middleware)) {
                $resolved[] = $middleware;
                continue;
            }

            throw new \InvalidArgumentException("Middleware desconhecido: {$middleware}");
        }

        return array_values(array_unique($resolved, SORT_REGULAR));
    }
}
