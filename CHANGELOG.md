# CHANGELOG

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Versionamento Semântico](https://semver.org/lang/pt-BR/).

## [Não lançado]

### Adicionado
- Núcleo do roteador (GET/POST/PUT/PATCH/DELETE/OPTIONS/HEAD/ANY/match)
- Parâmetros dinâmicos, opcionais, restrições where/whereNumber/whereAlphaNumeric
- Grupos com prefix/as/middleware/where
- Rotas nomeadas e geração de URLs
- Middlewares (interface, pipeline, aliases, globais/por rota/grupo)
- Controllers com resolução segura e injeção simples
- Abstrações Request/Response
- Tratamento de 404/405 com header Allow
- Rotas RESTful (resource), fallback, redirect, currentRoute, url
- Method spoofing opcional
- Tratamento central de exceções
- Testes automatizados

### Alterado
- Componente renomeado de RouterPro para BebelRouter (namespace `BebelRouter` e pacote Composer `bebel/router`)

### Segurança
- Proteção básica contra path traversal
- Method spoofing restrito a POST e lista permitida
- Rejeição de métodos não públicos em controllers
- IP confiável apenas com proxies configurados
- Sem uso de eval()
