# Instalação

## Requisitos
- PHP >= 8.2
- Composer

## Instalação via Composer
```bash
composer require bebel/router
```

*(Ajuste o vendor `bebel` caso publique o pacote em outra conta do Packagist)*

## Instalação para desenvolvimento
```bash
git clone https://github.com/seu-usuario/bebel-router.git
cd bebel-router
composer install
```

## Configuração do servidor web

### Apache
Crie/edite `.htaccess` na pasta pública:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
```

### Nginx
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## Estrutura recomendada
```text
app/
  Controllers/
public/
  index.php
routes/
  web.php
vendor/
```

## Arquivo de entrada (public/index.php)
```php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use BebelRouter\Router;

$router = new Router();

require dirname(__DIR__) . '/routes/web.php';

$response = $router->dispatchFromGlobals();
$response->send();
```
