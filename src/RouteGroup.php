<?php

declare(strict_types=1);

namespace BebelRouter;

final class RouteGroup
{
    /**
     * @param array<int, array<string, mixed>> $groupStack
     */
    public function merge(Route $route, array $groupStack): void
    {
        $prefix = '';
        $middleware = [];
        $wheres = [];

        foreach ($groupStack as $group) {
            if (isset($group['prefix']) && is_string($group['prefix']) && $group['prefix'] !== '') {
                $prefix .= '/' . ltrim(rtrim($group['prefix'], '/'), '/');
            }
            if (isset($group['middleware'])) {
                $m = is_array($group['middleware']) ? $group['middleware'] : [$group['middleware']];
                $middleware = array_merge($middleware, $m);
            }
            if (isset($group['where']) && is_array($group['where'])) {
                $wheres = array_merge($wheres, $group['where']);
            }
        }

        if ($prefix !== '') {
            $uri = $route->getUri();
            if ($uri === '/') {
                $newUri = rtrim($prefix, '/') === '' ? '/' : $prefix;
            } else {
                $newUri = rtrim($prefix, '/') . $uri;
            }
            $route->setUri($newUri);
        }

        if (!empty($middleware)) {
            $route->middleware($middleware);
        }

        if (!empty($wheres)) {
            foreach ($wheres as $param => $regex) {
                if (is_string($param) && is_string($regex)) {
                    $route->where($param, $regex);
                }
            }
        }

    }
}
