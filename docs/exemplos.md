# Exemplos prontos

## Basico
```php
<?php
require 'vendor/autoload.php';
use BebelRouter\Router;
use BebelRouter\Response;

$router = new Router();
$router->get('/', fn() => Response::json(['ok' => true]));
$router->dispatchFromGlobals()->send();
```

## API REST
```php
$router->get('/api/usuarios', [Api\UsuarioController::class, 'index']);
$router->post('/api/usuarios', [Api\UsuarioController::class, 'store']);
$router->resource('produtos', Api\ProdutoController::class);
```

## Com middlewares
```php
$router->aliasMiddleware('auth', AuthMiddleware::class);
$router->group(['prefix' => 'painel', 'middleware' => ['auth']], function($router) {
    $router->get('/', [PainelController::class, 'index']);
});
```
