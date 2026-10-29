# Rotas

## Métodos HTTP
```php
$router->get($uri, $action);
$router->post($uri, $action);
$router->put($uri, $action);
$router->patch($uri, $action);
$router->delete($uri, $action);
$router->options($uri, $action);
$router->head($uri, $action);
$router->any($uri, $action);      // GET,POST,PUT,PATCH,DELETE,OPTIONS,HEAD
$router->match(['GET','POST'], $uri, $action);
```

## Rotas nomeadas
```php
$router->get('/usuarios/{id}', [UsuarioController::class, 'show'])
    ->name('usuarios.show');

// Gerar URL
$url = $router->route('usuarios.show', ['id' => 10]); // /usuarios/10
```

## Fallback
```php
$router->fallback(function() {
    return Response::text('Não encontrado', 404);
});
```

## Rotas RESTful (resource)
```php
$router->resource('usuarios', UsuarioController::class);
```

Gera: index, create, store, show, edit, update, destroy.

## Redirecionamentos
```php
$router->redirect('/antigo', '/novo', 301);
```

## Rota atual e URL
```php
$route = $router->currentRoute();
$url = $router->url('/dashboard');
```

## listando rotas (básico)
```php
$routes = $router->getRoutes();
```
