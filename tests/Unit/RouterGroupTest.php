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

    public function testGroupNamePrefixDoesNotConflictWithRouteOutsideGroup(): void
    {
        $router = new Router();
        $router->get('/usuarios/{id}', function () {
            return 'public';
        })->name('usuarios.show');

        $router->group(['prefix' => 'admin', 'as' => 'admin.'], function ($router) {
            $router->get('/usuarios/{id}', function () {
                return 'admin';
            })->name('usuarios.show');
        });

        $this->assertSame('/usuarios/1', $router->route('usuarios.show', ['id' => '1']));
        $this->assertSame('/admin/usuarios/1', $router->route('admin.usuarios.show', ['id' => '1']));
    }

    public function testNestedGroupNamePrefix(): void
    {
        $router = new Router();
        $router->group(['prefix' => 'api', 'as' => 'api.'], function ($router) {
            $router->group(['prefix' => 'v1', 'as' => 'v1.'], function ($router) {
                $router->get('/ping', function () {
                    return 'pong';
                })->name('ping');
            });
        });

        $this->assertSame('/api/v1/ping', $router->route('api.v1.ping'));
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
