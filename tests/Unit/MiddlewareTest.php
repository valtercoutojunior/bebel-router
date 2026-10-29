<?php

declare(strict_types=1);

namespace BebelRouter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use BebelRouter\Request;
use BebelRouter\Response;
use BebelRouter\Router;
use BebelRouter\Middleware\MiddlewareInterface;

final class MiddlewareTest extends TestCase
{
    public function testGlobalMiddleware(): void
    {
        $router = new Router();
        $router->aliasMiddleware('test', TestMiddleware::class);
        $router->middleware(['test']);
        $router->get('/x', function () {
            return 'ok';
        });
        $res = $router->dispatch(new Request('GET', '/x'));
        $this->assertSame(200, $res->getStatusCode());
        $this->assertStringContainsString('ok', $res->getContent());
    }

    public function testRouteMiddlewareCanAbort(): void
    {
        $router = new Router();
        $router->aliasMiddleware('abort', AbortMiddleware::class);
        $router->get('/blocked', function () {
            return 'should-not-run';
        })->middleware('abort');
        $res = $router->dispatch(new Request('GET', '/blocked'));
        $this->assertSame(403, $res->getStatusCode());
    }

    public function testMiddlewareOrder(): void
    {
        $router = new Router();
        $router->aliasMiddleware('first', OrderMiddleware::class);
        $router->aliasMiddleware('second', OrderMiddleware2::class);
        $router->middleware(['first']);
        $router->get('/order', function () {
            return 'end';
        })->middleware('second');
        $res = $router->dispatch(new Request('GET', '/order'));
        $this->assertSame(200, $res->getStatusCode());
    }
}

final class TestMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        return $next($request);
    }
}

final class AbortMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        return Response::text('abort', 403);
    }
}

final class OrderMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $res = $next($request);
        return $res;
    }
}

final class OrderMiddleware2 implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $res = $next($request);
        return $res;
    }
}
