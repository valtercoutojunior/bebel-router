<?php
declare(strict_types=1);

namespace BebelRouter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use BebelRouter\Request;
use BebelRouter\Response;
use BebelRouter\Router;
use Closure;

final class RouterBasicTest extends TestCase
{
    public function testGetRouteWithClosure(): void
    {
        $router = new Router();
        $router->get('/hello', function () {
            return 'hello';
        });
        $req = new Request('GET', '/hello');
        $res = $router->dispatch($req);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('hello', $res->getContent());
    }

    public function testPostRoute(): void
    {
        $router = new Router();
        $router->post('/login', function () {
            return Response::json(['ok' => true]);
        });
        $req = new Request('POST', '/login');
        $res = $router->dispatch($req);
        $this->assertSame(200, $res->getStatusCode());
        $this->assertStringContainsString('ok', $res->getContent());
    }

    public function testNotFound(): void
    {
        $router = new Router();
        $req = new Request('GET', '/nao-existe');
        $res = $router->dispatch($req);
        $this->assertSame(404, $res->getStatusCode());
    }

    public function testMethodNotAllowedWithAllow(): void
    {
        $router = new Router();
        $router->get('/only-get', function () {
            return 'ok';
        });
        $req = new Request('POST', '/only-get');
        $res = $router->dispatch($req);
        $this->assertSame(405, $res->getStatusCode());
        $this->assertSame('GET, HEAD', $res->getHeaders()['Allow'] ?? '');
    }
}
