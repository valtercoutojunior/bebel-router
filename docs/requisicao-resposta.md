# Requisição e Resposta

## Request
```php
$request->getMethod();
$request->getUri();
$request->getQuery();
$request->getParsedBody();
$request->getInput('email');
$request->all();
$request->getHeader('Authorization');
$request->getRouteParams();
$request->getIp($trustedProxies = []);
```

## Response
```php
Response::json($data, $status = 200, $headers = []);
Response::text($content, $status = 200, $headers = []);
Response::html($content, $status = 200, $headers = []);
Response::redirect($to, $status = 302, $headers = [], $allowedHosts = []);
Response::empty($status = 204, $headers = []);
Response::make($content, $status = 200, $headers = []);
```

## Envio
```php
$response->send();
```

A criação da resposta é separada de sua emissão.
