<?php

declare(strict_types=1);

namespace BebelRouter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use BebelRouter\Request;
use BebelRouter\Response;
use BebelRouter\Router;

final class ControllerTest extends TestCase
{
    public function testControllerArrayAction(): void
    {
        $router = new Router();
        $router->get('/users/{id}', [TestUserController::class, 'show']);
        $res = $router->dispatch(new Request('GET', '/users/5'));
        $this->assertSame(200, $res->getStatusCode());
        $this->assertStringContainsString('5', $res->getContent());
    }

    public function testControllerAtAction(): void
    {
        $router = new Router();
        $router->get('/ping', 'BebelRouter\Tests\Unit\TestPingController@ping');
        $res = $router->dispatch(new Request('GET', '/ping'));
        $this->assertSame('pong', $res->getContent());
    }

    public function testControllerWithRequestInjection(): void
    {
        $router = new Router();
        $router->get('/req', [TestReqController::class, 'check']);
        $res = $router->dispatch(new Request('GET', '/req'));
        $this->assertSame(200, $res->getStatusCode());
    }
}

final class TestUserController
{
    public function show(string $id): Response
    {
        return Response::json(['id' => $id]);
    }
}

final class TestPingController
{
    public function ping(): string
    {
        return 'pong';
    }
}

final class TestReqController
{
    public function check(Request $request): Response
    {
        return Response::json(['method' => $request->getMethod()]);
    }
}
