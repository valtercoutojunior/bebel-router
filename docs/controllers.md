# Controllers

## Definição
```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use BebelRouter\Request;
use BebelRouter\Response;

final class UsuarioController
{
    public function index(): Response
    {
        return Response::json(['usuarios' => []]);
    }

    public function show(string $id): Response
    {
        return Response::json(['id' => $id]);
    }

    public function store(Request $request): Response
    {
        $dados = $request->getParsedBody();
        return Response::json(['criado' => true], 201);
    }
}
```

## Registro
```php
$router->get('/usuarios', [UsuarioController::class, 'index']);
$router->get('/usuarios/{id}', [UsuarioController::class, 'show']);
$router->post('/usuarios', 'App\\Controllers\\UsuarioController@store');
```

## Injeção
- Parâmetros da rota são injetados por posição ou nome
- `Request` pode ser injetado por tipo
- Construtor pode ter dependências resolvidas com container opcional

## Regras de segurança
- Apenas métodos **públicos** são executados
- Classes/métodos inexistentes geram erros claros
- Não há execução arbitrária via input do usuário
