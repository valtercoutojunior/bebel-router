<?php

declare(strict_types=1);

namespace BebelRouter;

use BebelRouter\Exception\DuplicateRouteException;
use BebelRouter\Exception\RouteNameConflictException;

final class RouteCollection
{
    /** @var list<Route> */
    private array $routes = [];
    /** @var array<string, Route> */
    private array $nameIndex = [];

    public function add(Route $route): void
    {
        $this->assertNoDuplicate($route);

        $route->onNameChange(function (Route $changed, string $newName): void {
            $this->registerName($changed, $newName);
        });

        $current = $route->getName();
        if ($current !== null && $current !== '') {
            $this->registerName($route, $current);
        }

        $this->routes[] = $route;
    }

    public function count(): int
    {
        return count($this->routes);
    }

    /** @return list<Route> */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function getByName(string $name): ?Route
    {
        return $this->nameIndex[$name] ?? null;
    }

    /** @return array<string, Route> */
    public function allNamed(): array
    {
        return $this->nameIndex;
    }

    private function assertNoDuplicate(Route $route): void
    {
        foreach ($this->routes as $existing) {
            if ($existing->getUri() !== $route->getUri()) {
                continue;
            }
            if ($existing->getWheres() !== $route->getWheres()) {
                continue;
            }
            if (array_intersect($existing->getMethods(), $route->getMethods()) === []) {
                continue;
            }

            throw new DuplicateRouteException(
                "Rota duplicada para '{$route->getUri()}' com os métodos " . implode(', ', $route->getMethods())
            );
        }
    }

    private function registerName(Route $route, string $name): void
    {
        $old = $route->getName();

        if ($name !== '' && isset($this->nameIndex[$name]) && $this->nameIndex[$name] !== $route) {
            throw new RouteNameConflictException("Nome de rota duplicado: {$name}");
        }

        if ($old !== null && $old !== '' && $old !== $name && isset($this->nameIndex[$old]) && $this->nameIndex[$old] === $route) {
            unset($this->nameIndex[$old]);
        }

        if ($name !== '') {
            $this->nameIndex[$name] = $route;
        }
    }
}
