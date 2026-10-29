# Segurança

## Medidas implementadas
- Sem `eval()`, `include`, `require` ou `call_user_func` com dados de usuário
- Resolução controlada de controllers/middlewares (classe instanciável, construtor público, método público)
- Apenas métodos públicos em controllers
- Normalização única de URI (`UriNormalizer`) com neutralização de traversal, NUL, CR/LF e double-encoding
- Partes estáticas de rotas escapadas com `preg_quote` e regex de `where()` validada
- Method spoofing desabilitado por padrão, restrito a POST e lista permitida
- IP apenas de proxies confiáveis (IP exato ou CIDR), com `X-Forwarded-For` ignorado para peers não confiáveis
- Cache de regex compiladas, limite de corpo (1 MB) e de URI no matching
- Headers de resposta validados contra CRLF, com headers de segurança padrão
- Redirect com validação de esquema, allowlist obrigatória para destinos absolutos e proteção contra injeção de header
- JSON com flags de escape para contexto HTML
- Fallback não é registrado como rota real
- Corpo de erro nunca expõe stack trace ou caminhos internos

## Method Spoofing (formulários HTML)
```html
<form method="POST" action="/usuarios/10">
    <input type="hidden" name="_method" value="DELETE">
    <button>Excluir</button>
</form>
```

```php
$router->enableMethodSpoofing(true); // habilitar explicitamente
```

## Recomendações para aplicação
- Configurar lista de proxies confiáveis ao usar `getIp()`
- Implementar autenticação/autorização na aplicação
- Proteger CSRF em formulários com cookies
- Configurar CORS adequadamente
- Não registrar senhas/tokens nos logs
- Limitar tamanhos de requisição conforme necessário
