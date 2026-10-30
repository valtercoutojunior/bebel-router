<?php

declare(strict_types=1);

/**
 * Rotas da aplicação.
 *
 * Este arquivo é carregado por public/index.php, que já criou a variável
 * $router e carregou o autoload do Composer.
 *
 * @var BebelRouter\Router $router
 */

use BebelRouter\Middleware\MiddlewareInterface;
use BebelRouter\Request;
use BebelRouter\Response;
use BebelRouter\Router;

if (!isset($router) || !$router instanceof Router) {
    throw new RuntimeException(
        'routes/web.php deve ser carregado por public/index.php após a criação de $router.'
    );
}

/*
|--------------------------------------------------------------------------
| Rotas simples (sem prefixo e sem middleware)
|--------------------------------------------------------------------------
|
| Aqui ficam as rotas públicas da aplicação. Elas são acessadas diretamente
| pela raiz do domínio e não passam por nenhum middleware.
|
*/

$router->get('/', function (): Response {
    return Response::json([
        'name'    => 'BebelRouter',
        'status'  => 'online',
        'version' => '0.0.1',
    ]);
})->name('home');

$router->get('/health', function (): Response {
    return Response::json(['status' => 'ok']);
})->name('health');

$router->get('/hello/{name}', function (string $name): Response {
    return Response::json([
        'message' => "Olá, {$name}!",
    ]);
})->whereAlpha('name')->name('hello');

$router->get('/usuarios/{id}', function (string $id): Response {
    return Response::json([
        'id'   => (int) $id,
        'nome' => 'Usuário de exemplo',
    ]);
})->whereNumber('id')->name('usuarios.show');

$router->post('/contato', function (): Response {
    return Response::json([
        'message' => 'Mensagem recebida com sucesso.',
    ], 201);
})->name('contato.store');

$router->match(['PUT', 'PATCH'], '/usuarios/{id}', function (string $id): Response {
    return Response::json([
        'id'      => (int) $id,
        'updated' => true,
    ]);
})->whereNumber('id')->name('usuarios.update');

$router->delete('/usuarios/{id}', function (string $id): Response {
    return Response::empty(204);
})->whereNumber('id')->name('usuarios.destroy');

/*
|--------------------------------------------------------------------------
| Middlewares da aplicação
|--------------------------------------------------------------------------
|
| Para manter este exemplo autocontido, os middlewares abaixo são classes
| anônimas. Em um projeto real, prefira classes em app/Middleware e registre
| aliases com $router->aliasMiddleware('auth', AuthMiddleware::class).
|
*/

$authMiddleware = new class implements MiddlewareInterface {
    public function process(Request $request, callable $next): Response
    {
        $authorization = $request->getHeader('Authorization');

        if ($authorization === null || $authorization === '') {
            return Response::json([
                'error' => 'Não autenticado',
                'hint'  => 'Envie o cabeçalho Authorization: Bearer <token>.',
            ], 401);
        }

        return $next($request);
    }
};

$adminMiddleware = new class implements MiddlewareInterface {
    public function process(Request $request, callable $next): Response
    {
        if ($request->getHeader('X-Role') !== 'admin') {
            return Response::json([
                'error' => 'Acesso restrito a administradores',
            ], 403);
        }

        return $next($request);
    }
};

$responseHeadersMiddleware = new class implements MiddlewareInterface {
    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        return $response
            ->setHeader('X-App-Name', 'BebelRouter-Demo')
            ->setHeader('X-Api-Version', 'v1');
    }
};

/*
|--------------------------------------------------------------------------
| Grupo com prefixo e middleware
|--------------------------------------------------------------------------
|
| Todas as rotas abaixo respondem em /admin/... e passam, nesta ordem,
| pelo middleware de autenticação e pelo middleware de perfil de acesso.
|
*/

$router->group([
    'prefix'     => 'admin',
    'as'         => 'admin.',
    'middleware' => [$authMiddleware, $adminMiddleware],
], function (Router $router): void {
    $router->get('/dashboard', function (): Response {
        return Response::json([
            'area' => 'admin',
            'page' => 'dashboard',
        ]);
    })->name('dashboard');

    $router->get('/usuarios', function (): Response {
        return Response::json([
            'usuarios' => [],
        ]);
    })->name('usuarios.index');

    $router->get('/usuarios/{id}', function (string $id): Response {
        return Response::json([
            'id'   => (int) $id,
            'nome' => 'Usuário administrado',
        ]);
    })->whereNumber('id')->name('usuarios.show');
});

/*
|--------------------------------------------------------------------------
| Grupos aninhados com prefixo e middleware
|--------------------------------------------------------------------------
|
| O grupo externo aplica o prefixo /api e o middleware de headers. O grupo
| interno adiciona o prefixo /v1 e exige autenticação. O resultado final é
| /api/v1/... com os três middlewares acumulados.
|
*/

$router->group([
    'prefix'     => 'api',
    'as'         => 'api.',
    'middleware' => [$responseHeadersMiddleware],
], function (Router $router) use ($authMiddleware): void {
    $router->get('/status', function (): Response {
        return Response::json(['status' => 'ok']);
    })->name('status');

    $router->group([
        'prefix'     => 'v1',
        'as'         => 'v1.',
        'middleware' => [$authMiddleware],
    ], function (Router $router): void {
        $router->get('/produtos', function (): Response {
            return Response::json([
                'produtos' => [],
            ]);
        })->name('produtos.index');

        $router->get('/produtos/{id}', function (string $id): Response {
            return Response::json([
                'id'    => (int) $id,
                'nome'  => 'Produto de exemplo',
                'preco' => 99.90,
            ]);
        })->whereNumber('id')->name('produtos.show');

        $router->post('/produtos', function (): Response {
            return Response::json([
                'created' => true,
            ], 201);
        })->name('produtos.store');
    });
});

/*
|--------------------------------------------------------------------------
| Fallback
|--------------------------------------------------------------------------
|
| Executado quando nenhuma rota corresponde à requisição.
|
*/

$router->fallback(function (Request $request): Response {
    return Response::json([
        'error' => 'Rota não encontrada',
        'path'  => $request->getUri(),
    ], 404);
});
