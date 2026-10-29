<?php

declare(strict_types=1);

namespace BebelRouter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use BebelRouter\Request;
use BebelRouter\Router;

final class RouterGroupTest extends TestCase
{
    public function testGroupPrefix(): void
    {
        $router = new Router();
        $router->group(['prefix' => 'admin'], function ($router) {
            $router->get('/dashboard', function () {
                return 'dash';
            });
            $router->get('/usuarios', function () {
                return 'users';
            });
        });
        $this->assertSame(200, $router->dispatch(new Request('GET', '/admin/dashboard'))->getStatusCode());
        $this->assertSame('dash', $router->dispatch(new Request('GET', '/admin/dashboard'))->getContent());
        $this->assertSame(200, $router->dispatch(new Request('GET', '/admin/usuarios'))->getStatusCode());
    }

    public function testGroupNameAndMiddleware(): void
    {
        $router = new Router();
        $router->group([
            'prefix' => 'admin',
            'as' => 'admin.',
            'middleware' => ['auth'],
        ], function ($router) {
            $router->get('/dashboard', function () {
                return 'dash';
            })->name('dashboard');
        });
        $this->assertSame('/admin/dashboard', $router->route('admin.dashboard'));
    }

    public function testNestedGroups(): void
    {
        $router = new Router();
        $router->group(['prefix' => 'api'], function ($router) {
            $router->group(['prefix' => 'v1'], function ($router) {
                $router->get('/ping', function () {
                    return 'pong';
                });
            });
        });
        $this->assertSame(200, $router->dispatch(new Request('GET', '/api/v1/ping'))->getStatusCode());
        $this->assertSame('pong', $router->dispatch(new Request('GET', '/api/v1/ping'))->getContent());
    }
}
