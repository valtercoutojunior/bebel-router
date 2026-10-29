# Testes

## Executar testes
```bash
composer install
composer test
```

## Executar análise estática
```bash
composer stan
```

## Estrutura de testes
- `tests/Unit/` - Testes unitários
- `tests/Integration/` - Testes de integração
- `tests/Security/` - Testes de segurança

## Escrever testes
```php
<?php

use PHPUnit\Framework\TestCase;
use BebelRouter\Request;
use BebelRouter\Router;

final class MeuTeste extends TestCase
{
    public function testExemplo(): void
    {
        $router = new Router();
        $router->get('/teste', fn() => 'ok');
        $res = $router->dispatch(new Request('GET', '/teste'));
        $this->assertSame('ok', $res->getContent());
    }
}
```
