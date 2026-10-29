# Grupos

## Prefixo
```php
$router->group(['prefix' => 'admin'], function($router) {
    $router->get('/dashboard', [DashboardController::class, 'index']); // /admin/dashboard
});
```

## Prefixo + nome + middleware
```php
$router->group([
    'prefix' => 'admin',
    'as' => 'admin.',
    'middleware' => ['auth', 'role:admin'],
], function($router) {
    $router->get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});
// nome: admin.dashboard, URL: /admin/dashboard
```

## Grupos aninhados
```php
$router->group(['prefix' => 'api'], function($router) {
    $router->group(['prefix' => 'v1'], function($router) {
        $router->get('/ping', fn() => 'pong'); // /api/v1/ping
    });
});
```

## Where em grupo
```php
$router->group(['where' => ['id' => '[0-9]+']], function($router) {
    $router->get('/usuarios/{id}', [UsuarioController::class, 'show']);
});
```
