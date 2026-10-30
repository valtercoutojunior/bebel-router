# Guia completo: instalação e uso do BebelRouter

Este guia mostra, do zero, como instalar e usar o BebelRouter em um projeto PHP
puro. Ao final você terá:

- Composer configurado;
- Apache com URLs amigáveis (`.htaccess`) ou Nginx equivalente;
- Front controller em `public/index.php`;
- Arquivo de rotas em `routes/web.php`;
- Rotas simples sem prefixo e sem middleware;
- Grupos de rotas com prefixo e middleware;
- Comandos para executar e testar a aplicação.

## Requisitos

- PHP **8.2** ou superior;
- Composer 2;
- Apache com `mod_rewrite` habilitado **ou** Nginx.

Verifique as versões:

```bash
php -v
composer --version
```

## Passo 1: instalar o componente

### Em um projeto novo ou existente

Na raiz do projeto, execute:

```bash
composer require bebel/router
```

O Composer cria a pasta `vendor/` e o arquivo `vendor/autoload.php`.

### Para contribuir com o próprio componente

```bash
git clone https://github.com/seu-usuario/bebel-router.git
cd bebel-router
composer install
```

## Passo 2: criar a estrutura do projeto

A estrutura recomendada separa o código da aplicação da pasta pública:

```text
meu-projeto/
├── app/
│   ├── Controllers/
│   └── Middleware/
├── public/
│   ├── .htaccess
│   └── index.php
├── routes/
│   └── web.php
├── vendor/
└── composer.json
```

O document root do servidor deve apontar para `public/`. Nunca aponte para a
raiz do projeto: `vendor/`, `composer.json` e os demais arquivos internos não
devem ser acessíveis pela web.

## Passo 3: configurar o Apache com `.htaccess`

No Apache, o `.htaccess` é **obrigatório** para que as URLs limpas
(`/usuarios/10`, `/admin/dashboard`) funcionem sem o `index.php` na URL.

Antes de criar o arquivo, confirme que:

1. O módulo `mod_rewrite` está habilitado;
2. O diretório da aplicação permite `AllowOverride All`;
3. O `DocumentRoot` aponta para a pasta `public/`.

### Arquivo `public/.htaccess`

Crie o arquivo `public/.htaccess` com o conteúdo abaixo:

```apache
# public/.htaccess

<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Encaminha o cabeçalho Authorization para o PHP (CGI/FastCGI).
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Remove a barra final de URLs que não são diretórios.
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Envia todas as requisições para o front controller.
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

A regra do cabeçalho `Authorization` é importante: sem ela, o Apache pode
remover esse cabeçalho antes de entregar a requisição ao PHP, e o middleware de
autenticação do exemplo deixaria de funcionar.

### Alternativa para Nginx

O Nginx não lê `.htaccess`. Use a configuração equivalente:

```nginx
server {
    listen 80;
    server_name bebel-router.test;
    root /var/www/meu-projeto/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Passo 4: criar o front controller

Crie `public/index.php`:

```php
<?php

declare(strict_types=1);

use BebelRouter\Router;

require dirname(__DIR__) . '/vendor/autoload.php';

$router = new Router(debug: false);

require dirname(__DIR__) . '/routes/web.php';

$router->dispatchFromGlobals()->send();
```

Em desenvolvimento, use `new Router(debug: true)` para receber detalhes das
exceções. Em produção, mantenha `debug: false`.

## Passo 5: criar o arquivo de rotas

Crie `routes/web.php` com o conteúdo abaixo. Ele contém rotas simples,
middlewares e grupos com prefixo.

```php
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
```

## Entendendo as rotas simples

As rotas abaixo não usam prefixo nem middleware:

| Método      | URL              | Nome               | Retorno                    |
|-------------|------------------|--------------------|----------------------------|
| `GET`       | `/`              | `home`             | JSON de status da API      |
| `GET`       | `/health`        | `health`           | JSON `{"status":"ok"}`     |
| `GET`       | `/hello/{name}`  | `hello`            | Saudação com parâmetro     |
| `GET`       | `/usuarios/{id}` | `usuarios.show`    | Usuário de exemplo         |
| `POST`      | `/contato`       | `contato.store`    | JSON `201 Created`         |
| `PUT/PATCH` | `/usuarios/{id}` | `usuarios.update`  | Atualização simulada       |
| `DELETE`    | `/usuarios/{id}` | `usuarios.destroy` | Resposta `204 No Content`  |

As restrições `whereAlpha` e `whereNumber` impedem que valores inválidos
cheguem à ação. Por exemplo, `/usuarios/abc` retorna `404`.

## Entendendo os grupos com prefixo e middleware

### Grupo `/admin`

```php
$router->group([
    'prefix'     => 'admin',
    'as'         => 'admin.',
    'middleware' => [$authMiddleware, $adminMiddleware],
], function (Router $router): void {
    // ...
});
```

| Método | URL                     | Nome                 | Middleware      |
|--------|-------------------------|----------------------|-----------------|
| `GET`  | `/admin/dashboard`      | `admin.dashboard`    | auth + admin    |
| `GET`  | `/admin/usuarios`       | `admin.usuarios.index` | auth + admin  |
| `GET`  | `/admin/usuarios/{id}`  | `admin.usuarios.show`  | auth + admin  |

O prefixo `admin` é aplicado à URL e o prefixo `admin.` é aplicado ao nome das
rotas por causa da chave `as`.

### Grupos aninhados `/api/v1`

```php
$router->group([
    'prefix'     => 'api',
    'as'         => 'api.',
    'middleware' => [$responseHeadersMiddleware],
], function (Router $router): void {
    $router->group([
        'prefix'     => 'v1',
        'as'         => 'v1.',
        'middleware' => [$authMiddleware],
    ], function (Router $router): void {
        // ...
    });
});
```

| Método | URL                    | Nome                    | Middleware            |
|--------|------------------------|-------------------------|-----------------------|
| `GET`  | `/api/status`          | `api.status`            | headers               |
| `GET`  | `/api/v1/produtos`     | `api.v1.produtos.index` | headers + auth        |
| `GET`  | `/api/v1/produtos/{id}`| `api.v1.produtos.show`  | headers + auth        |
| `POST` | `/api/v1/produtos`     | `api.v1.produtos.store` | headers + auth        |

Os middlewares são executados na ordem em que aparecem: primeiro os grupos
externos, depois os grupos internos e, por fim, os middlewares da rota.

## Usando classes de middleware (recomendado)

Em produção, evite classes anônimas no arquivo de rotas. Crie classes em
`app/Middleware/`.

### `app/Middleware/AuthMiddleware.php`

```php
<?php

declare(strict_types=1);

namespace App\Middleware;

use BebelRouter\Middleware\MiddlewareInterface;
use BebelRouter\Request;
use BebelRouter\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $authorization = $request->getHeader('Authorization');

        if ($authorization === null || $authorization === '') {
            return Response::json([
                'error' => 'Não autenticado',
            ], 401);
        }

        return $next($request);
    }
}
```

### `app/Middleware/AdminMiddleware.php`

```php
<?php

declare(strict_types=1);

namespace App\Middleware;

use BebelRouter\Middleware\MiddlewareInterface;
use BebelRouter\Request;
use BebelRouter\Response;

final class AdminMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        if ($request->getHeader('X-Role') !== 'admin') {
            return Response::json([
                'error' => 'Acesso restrito a administradores',
            ], 403);
        }

        return $next($request);
    }
}
```

### Configurar o autoload da aplicação

No `composer.json` do seu projeto, adicione o namespace `App\`:

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    }
}
```

Depois, atualize o autoload:

```bash
composer dump-autoload
```

### Registrar aliases no `routes/web.php`

```php
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;

$router->aliasMiddleware('auth', AuthMiddleware::class);
$router->aliasMiddleware('admin', AdminMiddleware::class);
```

Agora use os aliases nos grupos:

```php
$router->group([
    'prefix'     => 'admin',
    'as'         => 'admin.',
    'middleware' => ['auth', 'admin'],
], function (Router $router): void {
    $router->get('/dashboard', fn (): Response => Response::json([
        'area' => 'admin',
    ]))->name('dashboard');
});
```

> **Atenção:** nesta versão, a sintaxe `role:admin` é aceita pelo resolver,
> mas o parâmetro após `:` não é repassado ao middleware. Para regras com
> parâmetros, crie um middleware específico ou valide o cabeçalho dentro do
> próprio middleware.

## Rodando a aplicação

### Com Apache (XAMPP, WAMP, Laragon)

1. Habilite o `mod_rewrite`.
2. Configure o `DocumentRoot` para a pasta `public/`.
3. Garanta `AllowOverride All` no diretório.
4. Reinicie o Apache.
5. Acesse `http://localhost/` ou o host configurado.

### Com o servidor embutido do PHP

Na raiz do projeto:

```bash
php -S localhost:8000 -t public public/index.php
```

O `public/index.php` é usado como router: todas as requisições passam pelo
front controller, inclusive as URLs amigáveis.

## Testando com curl

Com o servidor em execução em `http://localhost:8000`:

```bash
# Rota simples
curl -i http://localhost:8000/

# Parâmetro validado por whereAlpha
curl -i http://localhost:8000/hello/Ana

# Parâmetro validado por whereNumber
curl -i http://localhost:8000/usuarios/10

# Sem autenticação: 401
curl -i http://localhost:8000/admin/dashboard

# Autenticado, mas sem perfil de administrador: 403
curl -i -H "Authorization: Bearer token" -H "X-Role: user" http://localhost:8000/admin/dashboard

# Autenticado e administrador: 200
curl -i -H "Authorization: Bearer token" -H "X-Role: admin" http://localhost:8000/admin/dashboard

# Grupo aninhado: headers + autenticação
curl -i -H "Authorization: Bearer token" http://localhost:8000/api/v1/produtos

# Rota inexistente: 404 pelo fallback
curl -i http://localhost:8000/nao-existe
```

No Windows PowerShell, use `curl.exe` para evitar o alias do
`Invoke-WebRequest`.

## Solução de problemas

| Sintoma                                | Causa provável                          | Solução                                                        |
|----------------------------------------|-----------------------------------------|----------------------------------------------------------------|
| Todas as rotas retornam `404`          | `mod_rewrite` desabilitado              | Habilite o módulo e reinicie o Apache                          |
| Todas as rotas retornam `404`          | `AllowOverride` diferente de `All`      | Ajuste a configuração do diretório no Apache                   |
| `404` apenas em URLs sem `index.php`   | `DocumentRoot` fora de `public/`        | Aponte o `DocumentRoot` para a pasta `public/`                 |
| `Authorization` chega vazio            | Cabeçalho removido pelo servidor        | Adicione a regra `HTTP_AUTHORIZATION` no `.htaccess` ou Nginx  |
| Erro `Class not found`                 | Autoload não atualizado                 | Execute `composer dump-autoload`                               |
| Erro de sintaxe com `Router`           | Namespace/import ausente                | Importe `BebelRouter\Router` no topo do arquivo                |
| `500` ao chamar um controller          | Dependência não resolvida               | Verifique o construtor ou registre a dependência no container  |

## Checklist para produção

- [ ] `debug: false` no `Router`;
- [ ] `DocumentRoot` apontando para `public/`;
- [ ] `vendor/` fora do acesso público;
- [ ] HTTPS habilitado;
- [ ] `mod_rewrite` habilitado no Apache;
- [ ] `.htaccess` versionado junto da aplicação;
- [ ] `Authorization` disponível no PHP;
- [ ] method spoofing mantido desabilitado, salvo necessidade real;
- [ ] limites de corpo de requisição adequados ao seu caso de uso;
- [ ] logs de erro e exceções monitorados.

## Próximos passos

- [Rotas](rotas.md)
- [Parâmetros](parametros.md)
- [Grupos](grupos.md)
- [Middlewares](middleware.md)
- [Controllers](controllers.md)
- [Requisição e resposta](requisicao-resposta.md)
- [Tratamento de erros](tratamento-de-erros.md)
- [Segurança](seguranca.md)
