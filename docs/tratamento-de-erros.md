# Tratamento de Erros

## Códigos suportados
400, 401, 403, 404, 405, 406, 413, 415, 422, 429, 500

## 405 Method Not Allowed
Quando a rota existe mas o método não é permitido, o cabeçalho `Allow` é adicionado com os métodos aceitos.

## Handler central
O corpo da resposta nunca expõe mensagem interna, stack trace ou caminhos de arquivo. Em modo debug, os detalhes técnicos são gravados apenas no log do servidor (`error_log`). Em produção, mensagens genéricas são retornadas.

```php
$router = new Router(debug: false); // produção
$router = new Router(debug: true);  // desenvolvimento
```

## Exceções HTTP
Use exceções que implementem `getStatusCode()` quando apropriado. O handler central trata-as de forma consistente.
