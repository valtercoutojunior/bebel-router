<?php

declare(strict_types=1);

namespace BebelRouter\Middleware;

use BebelRouter\ContainerInterface;
use BebelRouter\Request;
use BebelRouter\Response;

final class MiddlewarePipeline
{
    public function __construct(private ?ContainerInterface $container = null)
    {
    }

    /**
     * @param array<int, string|callable|object> $middlewares
     */
    public function handle(Request $request, array $middlewares, callable $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($middlewares),
            function (callable $next, mixed $middleware): callable {
                return function (Request $request) use ($next, $middleware): Response {
                    $resolved = $this->resolve($middleware);

                    if ($resolved instanceof MiddlewareInterface) {
                        return $resolved->process($request, $next);
                    }

                    if (is_object($resolved)) {
                        if (method_exists($resolved, 'process')) {
                            return $resolved->process($request, $next);
                        }
                        if (method_exists($resolved, 'handle')) {
                            return $resolved->handle($request, $next);
                        }
                    }

                    if (is_callable($resolved)) {
                        return $resolved($request, $next);
                    }

                    throw new \InvalidArgumentException('Middleware inválido');
                };
            },
            $destination
        );

        return $pipeline($request);
    }

    private function resolve(mixed $middleware): mixed
    {
        if (is_string($middleware)) {
            if ($this->container !== null && $this->container->has($middleware)) {
                $instance = $this->container->get($middleware);
                if (!is_object($instance)) {
                    throw new \RuntimeException("Container retornou um valor inválido para o middleware {$middleware}");
                }

                return $instance;
            }

            if (!class_exists($middleware)) {
                if (is_callable($middleware)) {
                    return $middleware;
                }

                throw new \InvalidArgumentException("Middleware desconhecido: {$middleware}");
            }

            if (
                !is_subclass_of($middleware, MiddlewareInterface::class)
                && !method_exists($middleware, 'process')
                && !method_exists($middleware, 'handle')
            ) {
                throw new \InvalidArgumentException("Middleware inválido: {$middleware}");
            }

            return new $middleware();
        }

        return $middleware;
    }
}
