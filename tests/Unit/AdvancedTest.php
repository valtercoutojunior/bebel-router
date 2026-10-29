<?php

declare(strict_types=1);

namespace BebelRouter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use BebelRouter\Request;
use BebelRouter\Response;
use BebelRouter\Router;

final class AdvancedTest extends TestCase
{
    public function testResource(): void
    {
        $router = new Router();
        $router->resource('usuarios', TestResourceController::class);
        $this->assertSame(200, $router->dispatch(new Request('GET', '/usuarios'))->getStatusCode());
        $this->assertSame(200, $router->dispatch(new Request('POST', '/usuarios'))->getStatusCode());
        $this->assertSame(200, $router->dispatch(new Request('GET', '/usuarios/1'))->getStatusCode());
    }

    public function testFallback(): void
    {
        $router = new Router();
        $router->fallback(function () {
            return Response::text('fallback', 404);
        });
        $res = $router->dispatch(new Request('GET', '/nao-existe'));
        $this->assertSame(404, $res->getStatusCode());
        $this->assertSame('fallback', $res->getContent());
    }

    public function testRedirectHelper(): void
    {
        $router = new Router();
        $router->redirect('/old', '/new');
        $res = $router->dispatch(new Request('GET', '/old'));
        $this->assertSame(302, $res->getStatusCode());
        $this->assertSame('/new', $res->getHeaders()['Location'] ?? '');
    }

    public function testMethodSpoofing(): void
    {
        $router = new Router();
        $router->enableMethodSpoofing(true);
        $router->delete('/usuarios/1', function () {
            return 'deleted';
        });
        $req = new Request('POST', '/usuarios/1', [], [], '', ['_method' => 'DELETE']);
        $res = $router->dispatch($req);
        $this->assertSame('deleted', $res->getContent());
    }
}

final class TestResourceController
{
    public function index(): string
    {
        return 'index';
    }

    public function create(): string
    {
        return 'create';
    }

    public function store(): string
    {
        return 'store';
    }

    public function show(string $id): string
    {
        return 'show';
    }

    public function edit(string $id): string
    {
        return 'edit';
    }

    public function update(string $id): string
    {
        return 'update';
    }

    public function destroy(string $id): string
    {
        return 'destroy';
    }
}
