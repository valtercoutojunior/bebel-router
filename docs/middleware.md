# Middlewares

## Contrato
```php
use BebelRouter\Middleware\MiddlewareInterface;
use BebelRouter\Request;
use BebelRouter\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        // antes
        if (!$this->autenticado($request)) {
            return Response::redirect('/login');
        }
        $response = $next($request);
        // depois
        return $response;
    }
}
```

## Registrar alias
```php
$router->aliasMiddleware('auth', AuthMiddleware::class);
$router->aliasMiddleware('role', RoleMiddleware::class);
```

## Middleware global
```php
$router->middleware([CorsMiddleware::class, SecurityHeadersMiddleware::class]);
```

## Middleware por rota
```php
$router->get('/perfil', [PerfilController::class, 'index'])
    ->middleware('auth');
```

## Middleware por grupo
```php
$router->group(['middleware' => ['auth', 'verified']], function($router) {
    $router->get('/painel', [PainelController::class, 'index']);
});
```

## Parâmetros de middleware
```php
$router->get('/admin', [AdminController::class, 'index'])
    ->middleware('role:admin');
```

## Ordem de execução
Global → Grupo → Rota (pipeline onion). Middlewares podem interromper retornando uma Response diretamente.
