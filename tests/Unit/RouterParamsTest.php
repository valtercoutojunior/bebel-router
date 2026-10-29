<?php

declare(strict_types=1);

namespace BebelRouter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use BebelRouter\Request;
use BebelRouter\Response;
use BebelRouter\Router;

final class RouterParamsTest extends TestCase
{
    public function testDynamicParameter(): void
    {
        $router = new Router();
        $router->get('/usuarios/{id}', function (string $id) {
            return "Usuário: {$id}";
        });
        $req = new Request('GET', '/usuarios/10');
        $res = $router->dispatch($req);
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('Usuário: 10', $res->getContent());
    }

    public function testOptionalParameter(): void
    {
        $router = new Router();
        $router->get('/artigos/{categoria?}', function ($categoria = null) {
            return $categoria === null ? 'todos' : $categoria;
        });
        $req1 = new Request('GET', '/artigos');
        $res1 = $router->dispatch($req1);
        $this->assertSame('todos', $res1->getContent());
        $req2 = new Request('GET', '/artigos/php');
        $res2 = $router->dispatch($req2);
        $this->assertSame('php', $res2->getContent());
    }

    public function testWhereNumber(): void
    {
        $router = new Router();
        $router->get('/usuarios/{id}', function (string $id) {
            return $id;
        })->whereNumber('id');
        $req = new Request('GET', '/usuarios/123');
        $res = $router->dispatch($req);
        $this->assertSame(200, $res->getStatusCode());
        $reqBad = new Request('GET', '/usuarios/abc');
        $resBad = $router->dispatch($reqBad);
        $this->assertSame(404, $resBad->getStatusCode());
    }

    public function testWhereAlphaNumeric(): void
    {
        $router = new Router();
        $router->get('/artigos/{slug}', function (string $slug) {
            return $slug;
        })->whereAlphaNumeric('slug');
        $req = new Request('GET', '/artigos/php8');
        $res = $router->dispatch($req);
        $this->assertSame(200, $res->getStatusCode());
        $reqBad = new Request('GET', '/artigos/php-8');
        $resBad = $router->dispatch($reqBad);
        $this->assertSame(404, $resBad->getStatusCode());
    }

    public function testWhereRegex(): void
    {
        $router = new Router();
        $router->get('/documentos/{codigo}', function (string $codigo) {
            return $codigo;
        })->where('codigo', '[A-Z]{2}[0-9]{6}');
        $req = new Request('GET', '/documentos/AB123456');
        $res = $router->dispatch($req);
        $this->assertSame(200, $res->getStatusCode());
        $reqBad = new Request('GET', '/documentos/ab123456');
        $resBad = $router->dispatch($reqBad);
        $this->assertSame(404, $resBad->getStatusCode());
    }
}
