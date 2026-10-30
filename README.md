# BebelRouter

Biblioteca profissional de roteamento HTTP PHP puro, inspirada na API do Laravel, sem dependências obrigatórias.

## Recursos
- Métodos HTTP: GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD, ANY, match
- Parâmetros dinâmicos e opcionais com restrições
- Grupos com prefixo, nome, middleware, where
- Rotas nomeadas e geração de URLs
- Middlewares globais/por rota/grupo com pipeline
- Controllers com resolução segura e injeção simples
- Rotas RESTful, fallback, redirecionamentos
- Method spoofing opcional
- Tratamento 404/405 com header Allow
- Respostas JSON/text/html/redirect
- Testes automatizados

## Requisitos
- PHP >= 8.2

## Instalação
```bash
composer require bebel/router
```

## Exemplo mínimo
```php
<?php

require 'vendor/autoload.php';

use BebelRouter\Router;
use BebelRouter\Response;

$router = new Router();

$router->get('/', function() {
    return Response::json(['msg' => 'Olá, BebelRouter!']);
});

$router->get('/usuarios/{id}', [App\Controllers\UsuarioController::class, 'show'])
    ->name('usuarios.show')
    ->whereNumber('id');

$router->group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => ['auth']], function($router) {
    $router->get('/dashboard', [App\Controllers\Admin\DashboardController::class, 'index'])
        ->name('dashboard');
});

$response = $router->dispatchFromGlobals();
$response->send();
```

## Documentação
- [Guia completo: instalação e uso](docs/guia-completo.md)
- [Publicação automática no Packagist](docs/publicacao-packagist.md)
- [Referência de middlewares](docs/middleware.md)

## Testes
```bash
composer install
composer test
```

## Segurança
Consulte [SECURITY.md](SECURITY.md). Method spoofing desabilitado por padrão. Não confie em cabeçalhos de proxy sem configurar lista confiável.

## Licença
MIT
