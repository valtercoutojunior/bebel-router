# Parâmetros

## Parâmetros dinâmicos
```php
$router->get('/usuarios/{id}', function(string $id) {
    return "Usuário: {$id}";
});
```

## Parâmetros opcionais
```php
$router->get('/artigos/{categoria?}', [ArtigoController::class, 'index']);
```

## Restrições
```php
$router->get('/usuarios/{id}', [UsuarioController::class, 'show'])
    ->whereNumber('id');

$router->get('/artigos/{slug}', [ArtigoController::class, 'show'])
    ->whereAlphaNumeric('slug');

$router->get('/documentos/{codigo}', [DocumentoController::class, 'show'])
    ->where('codigo', '[A-Z]{2}[0-9]{6}');
```

As restrições são aplicadas via expressão regular. Rotas estáticas têm prioridade sobre dinâmicas quando há ambiguidade.
