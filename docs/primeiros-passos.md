# Primeiros Passos

## Criando o roteador
```php
<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use BebelRouter\Router;

$router = new Router();
```

## Registrando rotas
```php
$router->get('/', function() {
    return 'Olá mundo!';
});

$router->post('/login', function() {
    return 'Login';
});
```

## Despachando requisições
```php
use BebelRouter\Request;

$request = Request::createFromGlobals();
$response = $router->dispatch($request);
$response->send();
```

Ou simplesmente:
```php
$response = $router->dispatchFromGlobals();
$response->send();
```

## Respostas
```php
use BebelRouter\Response;

// JSON
return Response::json(['status' => 'ok']);

// Texto
return Response::text('Olá', 200);

// HTML
return Response::html('<h1>Olá</h1>');

// Redirecionamento
return Response::redirect('/login', 302);

// Vazio
return Response::empty(204);
```

## Closures, arrays ou string
```php
$router->get('/closure', fn() => 'ok');
$router->get('/array', [UsuarioController::class, 'index']);
$router->get('/string', 'UsuarioController@index');
```
