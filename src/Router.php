<?php

declare(strict_types=1);

namespace BebelRouter;

use BebelRouter\Controller\ControllerResolver;
use BebelRouter\Exception\Handler as ExceptionHandler;
use BebelRouter\Exception\HttpException;
use BebelRouter\Exception\MethodNotAllowedException;
use BebelRouter\Exception\NotFoundHttpException;
use BebelRouter\Middleware\MiddlewarePipeline;
use BebelRouter\Middleware\MiddlewareResolver;

final class Router
{
    private RouteCollection $routes;
    private RouteMatcher $matcher;
    /** @var array<int, array<string, mixed>> */
    private array $groupStack = [];
    /** @var array<int, array<string, mixed>> */
    private array $currentGroupStack = [];
    private ?RouteUrlGenerator $urlGenerator = null;
    /** @var array<int, string|callable|object> */
    private array $globalMiddleware = [];
    /** @var array<string, string> */
    private array $middlewareAliases = [];
    private bool $debug = false;
    private ?Route $currentRoute = null;
    private ?\Closure $fallback = null;
    private bool $methodSpoofing = false;
    /** @var array<int, string> */
    private array $allowedSpoofMethods = ['PUT', 'PATCH', 'DELETE'];
    private ?ContainerInterface $container = null;

    public function __construct(bool $debug = false)
    {
        $this->routes = new RouteCollection();
        $this->matcher = new RouteMatcher();
        $this->debug = $debug;
    }

    public function setContainer(ContainerInterface $container): self
    {
        $this->container = $container;
        return $this;
    }

    /** @param array<int, string> $allowed */
    public function enableMethodSpoofing(bool $enable = true, array $allowed = ['PUT', 'PATCH', 'DELETE']): self
    {
        $this->methodSpoofing = $enable;

        if ($allowed !== []) {
            $normalized = [];
            foreach ($allowed as $method) {
                if (is_string($method) && $method !== '') {
                    $normalized[] = strtoupper($method);
                }
            }
            $this->allowedSpoofMethods = $normalized;
        }

        return $this;
    }

    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getCurrentGroupStack(): array
    {
        return $this->currentGroupStack;
    }

    /** @return array<string, string> */
    public function getMiddlewareAliases(): array
    {
        return $this->middlewareAliases;
    }

    public function get(string $uri, mixed $action): Route
    {
        return $this->addRoute(['GET'], $uri, $action);
    }

    public function post(string $uri, mixed $action): Route
    {
        return $this->addRoute(['POST'], $uri, $action);
    }

    public function put(string $uri, mixed $action): Route
    {
        return $this->addRoute(['PUT'], $uri, $action);
    }

    public function patch(string $uri, mixed $action): Route
    {
        return $this->addRoute(['PATCH'], $uri, $action);
    }

    public function delete(string $uri, mixed $action): Route
    {
        return $this->addRoute(['DELETE'], $uri, $action);
    }

    public function options(string $uri, mixed $action): Route
    {
        return $this->addRoute(['OPTIONS'], $uri, $action);
    }

    public function head(string $uri, mixed $action): Route
    {
        return $this->addRoute(['HEAD'], $uri, $action);
    }

    public function any(string $uri, mixed $action): Route
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'], $uri, $action);
    }

    /** @param array<int, string> $methods */
    public function match(array $methods, string $uri, mixed $action): Route
    {
        $normalized = [];
        foreach ($methods as $method) {
            if (is_string($method) && $method !== '') {
                $normalized[] = strtoupper($method);
            }
        }

        return $this->addRoute($normalized, $uri, $action);
    }

    public function fallback(callable $action): void
    {
        $this->fallback = \Closure::fromCallable($action);
    }

    /** @param array<int, string> $allowedHosts */
    public function redirect(string $from, string $to, int $status = 302, array $allowedHosts = []): Route
    {
        return $this->get($from, function () use ($to, $status, $allowedHosts) {
            return Response::redirect($to, $status, [], $allowedHosts);
        });
    }

    public function resource(string $name, string $controller): void
    {
        $name = trim($name, '/');
        $base = '/' . $name;
        $param = $name . '/{id}';
        $this->get($base, [$controller, 'index']);
        $this->get($base . '/create', [$controller, 'create']);
        $this->post($base, [$controller, 'store']);
        $this->get('/' . $param, [$controller, 'show']);
        $this->get('/' . $param . '/edit', [$controller, 'edit']);
        $this->match(['PUT', 'PATCH'], '/' . $param, [$controller, 'update']);
        $this->delete('/' . $param, [$controller, 'destroy']);
    }

    public function currentRoute(): ?Route
    {
        return $this->currentRoute;
    }

    public function url(string $path): string
    {
        if (str_starts_with($path, 'http')) {
            return $path;
        }
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        return $path;
    }

    /** @return list<Route> */
    public function getRoutes(): array
    {
        return $this->routes->getRoutes();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $this->currentGroupStack[] = $attributes;
        $start = $this->routes->count();

        try {
            $callback($this);
        } finally {
            array_pop($this->groupStack);
            array_pop($this->currentGroupStack);
        }

        if (isset($attributes['as']) && is_string($attributes['as']) && $attributes['as'] !== '') {
            $prefix = $attributes['as'];
            $routes = $this->routes->getRoutes();
            $total = count($routes);

            for ($i = $start; $i < $total; $i++) {
                $route = $routes[$i];
                $currentName = $route->getName();
                if (is_string($currentName) && $currentName !== '') {
                    $route->name($prefix . $currentName);
                }
            }
        }
    }

    /**
     * @param array<int, string> $methods
     */
    private function addRoute(array $methods, string $uri, mixed $action): Route
    {
        $route = new Route($methods, $uri, $action);

        if ($this->groupStack !== []) {
            $group = new RouteGroup();
            $group->merge($route, $this->groupStack);
        }

        $this->routes->add($route);

        return $route;
    }

    /**
     * @param array<string, string> $params
     */
    public function route(string $name, array $params = []): string
    {
        if ($this->urlGenerator === null) {
            $this->urlGenerator = new RouteUrlGenerator($this->routes);
        }

        return $this->urlGenerator->route($name, $params);
    }

    /** @param array<int, string|callable|object>|string $middleware */
    public function middleware(array|string $middleware): self
    {
        $m = is_array($middleware) ? $middleware : [$middleware];
        $this->globalMiddleware = array_merge($this->globalMiddleware, $m);

        return $this;
    }

    public function aliasMiddleware(string $alias, string $class): self
    {
        $this->middlewareAliases[$alias] = $class;

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $originalMethod = $request->getMethod();
        $response = $this->handle($request);

        if ($originalMethod === 'HEAD') {
            return $response->withContent('');
        }

        return $response;
    }

    private function handle(Request $request): Response
    {
        try {
            $method = $request->getMethod();
            $uri = $request->getUri();

            if ($this->methodSpoofing && $method === 'POST') {
                $parsed = $request->getParsedBody();
                if (isset($parsed['_method']) && is_string($parsed['_method'])) {
                    $spoof = strtoupper($parsed['_method']);
                    if (in_array($spoof, $this->allowedSpoofMethods, true)) {
                        $method = $spoof;
                        $request = $request->withMethod($method);
                    }
                }
            }

            $matched = $this->matcher->match($this->routes, $method, $uri);
            $route = $matched['route'];
            $this->currentRoute = $route;
            $params = $matched['params'];
            $req = $request->withRouteParams($params);
            $action = $route->getAction();

            $resolver = new MiddlewareResolver($this);
            $global = $resolver->resolve($this->globalMiddleware);
            $routeMw = $resolver->resolve($route->getMiddleware());
            $middlewares = array_values(array_unique(array_merge($global, $routeMw), SORT_REGULAR));

            $pipeline = new MiddlewarePipeline($this->container);
            $destination = fn (Request $r): Response => $this->runAction($action, $params, $r);

            if ($middlewares !== []) {
                return $pipeline->handle($req, $middlewares, $destination);
            }

            return $destination($req);
        } catch (MethodNotAllowedException $e) {
            if ($request->getMethod() === 'OPTIONS') {
                $allow = $e->getAllow();
                return Response::empty(204, $allow !== null && $allow !== '' ? ['Allow' => $allow] : []);
            }

            $headers = [];
            if ($e->getAllow() !== null && $e->getAllow() !== '') {
                $headers['Allow'] = $e->getAllow();
            }

            return Response::text($e->getMessage(), 405, $headers);
        } catch (NotFoundHttpException $e) {
            if ($request->getMethod() === 'OPTIONS') {
                $allowed = $this->matcher->allowedMethods($this->routes, $request->getUri());
                if ($allowed !== []) {
                    return Response::empty(204, ['Allow' => implode(', ', $allowed)]);
                }
            }

            if ($this->fallback !== null) {
                return $this->toResponse(($this->fallback)($request));
            }

            return (new ExceptionHandler())->handle($e, $this->debug);
        } catch (\Throwable $e) {
            return (new ExceptionHandler())->handle($e, $this->debug);
        }
    }

    /**
     * @param array<string, string> $params
     */
    private function runAction(mixed $action, array $params, Request $request): Response
    {
        if ($action instanceof \Closure) {
            $result = $action(...array_values($params));
        } elseif (is_array($action) && count($action) === 2) {
            $result = (new ControllerResolver($this->container))->resolve($action, $params, $request);
        } elseif (is_string($action) && str_contains($action, '@')) {
            $result = (new ControllerResolver($this->container))->resolve($action, $params, $request);
        } else {
            throw new \RuntimeException('Tipo de ação não suportado');
        }

        return $this->toResponse($result);
    }

    private function toResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result)) {
            return Response::json($result);
        }
        if (is_string($result)) {
            return Response::text($result);
        }
        if (is_int($result) || is_float($result)) {
            return Response::text((string) $result);
        }
        if (is_bool($result)) {
            return Response::text($result ? '1' : '0');
        }
        if ($result instanceof \Stringable) {
            return Response::text((string) $result);
        }
        if ($result === null) {
            return Response::empty(204);
        }

        throw new \RuntimeException('Tipo de retorno de ação não suportado');
    }

    public function dispatchFromGlobals(): Response
    {
        try {
            $req = Request::createFromGlobals();
        } catch (HttpException $e) {
            return (new ExceptionHandler())->handle($e, $this->debug);
        }

        return $this->dispatch($req);
    }
}
