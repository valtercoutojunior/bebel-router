# Publicação automática no Packagist

Este guia deixa o repositório preparado para que cada atualização enviada ao
GitHub seja refletida no Packagist.

O Packagist não hospeda o código: ele lê o repositório do GitHub e transforma
tags e branches em versões do Composer.

- Push na branch `main` gera/atualiza a versão `dev-main`;
- Tag `vX.Y.Z` gera a versão estável `X.Y.Z`;
- O pacote publicado será `bebel/router`.

## Pré-requisitos

- Repositório público no GitHub;
- `composer.json` válido na raiz;
- Licença declarada (este projeto usa MIT);
- Conta no [Packagist](https://packagist.org) conectada ao GitHub.

Valide o pacote localmente:

```bash
composer validate --strict
composer test
```

Não adicione o campo `version` no `composer.json`. O Packagist calcula a versão
a partir das tags do Git.

## Passo 1: publicar o pacote pela primeira vez

1. Acesse <https://packagist.org/packages/submit>;
2. Informe a URL do repositório:

   ```text
   https://github.com/valtercoutojunior/bebel-router
   ```

3. Clique em **Check**;
4. Confirme o nome `bebel/router` e clique em **Submit**.

Após o envio, o Packagist faz a primeira leitura do repositório e publica a
branch `main` como `dev-main`.

## Passo 2: ativar a atualização automática

Existem três formas de manter o Packagist sincronizado. Use a opção **A** ou a
opção **B**; a opção **C** é uma alternativa via GitHub Actions.

### Opção A: integração oficial com o GitHub (recomendada)

1. Faça login no Packagist usando **Log in with GitHub**;
2. Autorize o aplicativo do Packagist a configurar webhooks;
3. Acesse <https://packagist.org/profile/>;
4. Verifique se o pacote aparece sem o aviso de sincronização pendente;
5. Se necessário, use a sincronização de conta em
   <https://packagist.org/trigger-github-sync/>.

Com essa integração, o Packagist instala o webhook automaticamente e atualiza o
pacote a cada push.

### Opção B: webhook manual no GitHub

Use esta opção caso não queira conceder ao Packagist acesso para configurar
webhooks automaticamente.

1. Obtenha seu API token em <https://packagist.org/profile/>;
2. No GitHub, abra o repositório e vá em **Settings → Webhooks → Add webhook**;
3. Preencha:

   | Campo        | Valor                                                        |
   |--------------|--------------------------------------------------------------|
   | Payload URL  | `https://packagist.org/api/github?username=SEU_USUARIO`      |
   | Content type | `application/json`                                           |
   | Secret       | Seu API token do Packagist                                   |
   | Events       | **Just the push event**                                      |
   | Active       | Marcado                                                      |

4. Substitua `SEU_USUARIO` pelo seu usuário do Packagist, não do GitHub;
5. Clique em **Add webhook**.

O GitHub mostrará o resultado de cada entrega em **Recent Deliveries**. Um
retorno `200` indica que o Packagist recebeu a notificação.

### Opção C: GitHub Actions

O repositório já inclui o workflow
[`.github/workflows/packagist.yml`](../.github/workflows/packagist.yml). Ele
chama a API do Packagist em pushes para `main`, em tags `v*` e também pode ser
executado manualmente.

1. No GitHub, abra **Settings → Secrets and variables → Actions**;
2. Crie os secrets:

   | Secret                 | Valor                                      |
   |------------------------|--------------------------------------------|
   | `PACKAGIST_USERNAME`   | Seu usuário do Packagist                   |
   | `PACKAGIST_API_TOKEN`  | API token obtido em packagist.org/profile/ |

3. Faça um push para `main` ou dispare o workflow em **Actions → Atualizar
   Packagist → Run workflow**.

O workflow usa a API oficial:

```text
POST https://packagist.org/api/update-package?username=USUARIO&apiToken=TOKEN
```

Com o corpo:

```json
{
    "repository": {
        "url": "https://github.com/valtercoutojunior/bebel-router"
    }
}
```

Se os secrets não estiverem configurados, o workflow não falha: ele apenas
registra um aviso e não chama a API.

> **Nunca** coloque o token no código, no `composer.json` ou em arquivos
> versionados. Use sempre os secrets do GitHub.

## Passo 3: criar uma versão estável

O Packagist só cria versões estáveis quando você envia uma tag no formato
`X.Y.Z` ou `vX.Y.Z`.

```bash
git add .
git commit -m "chore: preparar publicação no Packagist"
git push origin main

git tag -a v0.1.0 -m "BebelRouter v0.1.0"
git push origin v0.1.0
```

Depois do push da tag, o Packagist cria a versão `0.1.0`. O tempo de
processamento costuma ser de segundos, mas o índice de busca pode levar até
cinco minutos para refletir a novidade.

## Passo 4: conferir a publicação

1. Abra a página do pacote:

   ```text
   https://packagist.org/packages/bebel/router
   ```

2. Confira as abas **Versions** e **Update log**;
3. Teste a instalação em um projeto separado:

   ```bash
   mkdir teste-bebel-router
   cd teste-bebel-router
   composer init --no-interaction
   composer require bebel/router:^0.1 --prefer-dist
   ```

Se o pacote ainda não for encontrado, aguarde alguns minutos e execute:

```bash
composer clear-cache
composer require bebel/router:^0.1 --prefer-dist
```

## Opcional: badges no README

Depois que o pacote estiver publicado, você pode adicionar badges ao README:

```markdown
[![Versão](https://img.shields.io/packagist/v/bebel/router.svg)](https://packagist.org/packages/bebel/router)
[![Downloads](https://img.shields.io/packagist/dt/bebel/router.svg)](https://packagist.org/packages/bebel/router)
```

## Checklist antes de cada release

- [ ] `composer validate --strict` sem erros;
- [ ] `composer test` passando;
- [ ] `composer stan` sem erros (quando aplicável);
- [ ] `CHANGELOG.md` atualizado;
- [ ] `composer.json` sem o campo `version`;
- [ ] Commit enviado para `main`;
- [ ] Tag criada no formato `vX.Y.Z`;
- [ ] Tag enviada com `git push origin vX.Y.Z`;
- [ ] Versão confirmada na página do Packagist.

## Solução de problemas

| Sintoma                              | Causa provável                                  | Solução                                                                    |
|--------------------------------------|-------------------------------------------------|----------------------------------------------------------------------------|
| Pacote não atualiza após o push      | Webhook ausente ou inativo                      | Revise **Settings → Webhooks** e as entregas recentes                      |
| Webhook retorna `401`                | Secret/token incorreto                          | Gere um novo API token no Packagist e atualize o secret                    |
| Webhook retorna `404`                | URL do repositório diferente da cadastrada      | Confira a URL exata do pacote no Packagist                                 |
| Tag não aparece como versão          | Tag fora do padrão ou não enviada               | Use `vX.Y.Z` ou `X.Y.Z` e execute `git push origin vX.Y.Z`                |
| `Could not find package`             | Índice de busca ainda não atualizado            | Aguarde até cinco minutos e limpe o cache do Composer                      |
| Workflow não atualiza                | Secrets ausentes                                | Cadastre `PACKAGIST_USERNAME` e `PACKAGIST_API_TOKEN`                      |
| Packagist ignora o `composer.json`   | Arquivo inválido ou fora da raiz                | Execute `composer validate --strict` e corrija os erros                    |
| Repositório privado não é lido       | Packagist não acessa repositórios privados      | Torne o repositório público ou use o Private Packagist                     |

## Referências

- <https://packagist.org/about>
- <https://packagist.org/packages/submit>
- <https://packagist.org/profile/>
- <https://getcomposer.org/doc/04-schema.md>
