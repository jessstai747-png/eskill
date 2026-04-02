# Troubleshooting de Conexão com a Loja Virtual

## Resumo da Análise

O erro de conexão com a loja virtual foi identificado como um problema composto no fluxo OAuth do Mercado Livre:

1. Configuração OAuth inválida no ambiente local/atual
2. Risco de uso indevido de proxy herdado do ambiente em workers e integrações HTTP
3. Tokens históricos desconectados por `invalid_grant`

## Tipos de Erro Identificados

### 1. Erro de Configuração OAuth

Evidência encontrada no `.env` atual:

- `ML_APP_ID=your_mercadolibre_app_id`
- `ML_CLIENT_SECRET=your_mercadolibre_client_secret`
- `ML_REDIRECT_URI=https://your-domain.com/dashboard`
- `APP_KEY=change_me_with_32+_chars_minimum________________`

Impacto:

- o Mercado Livre não consegue associar corretamente o aplicativo durante a autorização
- o callback pode falhar por redirect URI incorreto
- a persistência segura dos tokens pode falhar se `APP_KEY` não for válido

### 2. Erro de Proxy / Rede

Evidência encontrada nos logs:

- `storage/logs/cron_questions.log`
- `cURL error 5: Could not resolve proxy: proxy.mercadolivre.com`

Impacto:

- chamadas à API podem falhar mesmo com `ML_PROXY_ENABLED=false` se o processo herdar `HTTP_PROXY`/`HTTPS_PROXY` externos

### 3. Erro de Autenticação

Evidência encontrada nos logs:

- `storage/logs/token_refresh.log`
- `storage/logs/cron_items.log`
- `invalid_grant: Error validating grant. Your authorization code or refresh token is invalid`

Impacto:

- contas já vinculadas podem cair para estado desconectado e exigir reconexão OAuth

### 4. SSL / Certificados

Validação executada:

- `https://eskill.com.br/auth/callback` respondeu com `302`
- não houve erro de certificado no teste HTTPS

Conclusão:

- não há evidência atual de falha SSL no endpoint da aplicação

### 5. Firewall / Endpoints

Validação executada:

- `https://api.mercadolibre.com/sites/MLB` respondeu via HTTPS
- `https://auth.mercadolivre.com.br/authorization` respondeu via HTTPS

Conclusão:

- o caminho de rede HTTPS está acessível
- não há indício atual de bloqueio de firewall no tráfego básico
- respostas `403` nesses testes públicos não caracterizam indisponibilidade da API

## Correções Aplicadas

### Validação antecipada de configuração OAuth

O serviço `MercadoLivreAuthService` agora:

- detecta placeholders em `ML_APP_ID`, `ML_CLIENT_SECRET`, `ML_REDIRECT_URI` e `APP_KEY`
- normaliza `ML_REDIRECT_URI` antes da autorização e do callback
- expõe um diagnóstico estruturado com `issues`, `warnings` e `details`
- bloqueia o início do fluxo OAuth quando a configuração é inválida

### Tratamento de erro mais claro no controller

O `AuthController` agora:

- captura falhas já em `/auth/authorize`
- classifica o erro em categorias como `configuration`, `proxy`, `timeout`, `ssl`, `authentication` e `api`
- registra eventos estruturados do callback OAuth
- retorna mensagens operacionais mais claras ao usuário
- implementa `GET /api/auth/oauth-config-status` para a dashboard de contas

### Hardening de proxy

As integrações com Mercado Livre agora:

- desabilitam explicitamente proxy herdado do ambiente quando `ML_PROXY_ENABLED=false`
- só usam proxy configurado quando `ML_PROXY_ENABLED=true` e `ML_PROXY_HOST`/`ML_PROXY_PORT` estão válidos

### Normalização do redirect URI

O mesmo redirect URI normalizado é usado em:

- geração da URL de autorização
- troca de código por token

Isso evita divergência entre authorize e callback quando o valor vier sem esquema explícito.

## Como Validar a Correção

### Desenvolvimento

1. Confirmar que `ML_APP_ID`, `ML_CLIENT_SECRET`, `ML_REDIRECT_URI` e `APP_KEY` não usam placeholders
2. Acessar `/api/auth/oauth-config-status`
3. Verificar se a resposta indica `ready=true`
4. Iniciar o fluxo em `/auth/authorize`

### Produção

1. Validar que `ML_REDIRECT_URI` aponta para `https://eskill.com.br/auth/callback`
2. Validar `APP_KEY` com no mínimo 32 caracteres reais
3. Confirmar que workers/crons não exportam `HTTP_PROXY` ou `HTTPS_PROXY` inválidos
4. Reconectar contas que estejam com `status=disconnected` ou `invalid_grant`

## Prevenção de Falhas Futuras

- não promover `.env` com valores placeholder
- validar `/api/auth/oauth-config-status` em smoke tests de deploy
- monitorar logs por `invalid_grant`, `Could not resolve proxy`, `SSL`, `timeout` e `redirect_uri`
- manter `ML_PROXY_ENABLED=false` por padrão e habilitar proxy só com host/porta válidos
- validar o cadastro do app Mercado Livre sempre que o domínio ou callback mudar
- revisar `APP_KEY` antes de qualquer deploy para garantir criptografia funcional dos tokens

## Arquivos Envolvidos

- `app/Services/MercadoLivreAuthService.php`
- `app/Services/MercadoLivreClient.php`
- `app/Controllers/AuthController.php`
- `tests/Unit/Services/MercadoLivreAuthServiceOauthUrlTest.php`
- `tests/Unit/Services/MercadoLivreClientTest.php`
- `tests/Unit/Controllers/AuthControllerTest.php`
