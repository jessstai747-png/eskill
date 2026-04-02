# Plano Executável de Integração Mercado Livre

## Objetivo

Transformar a integração atual do Mercado Livre em uma plataforma consistente, auditável e profissional, alinhada à documentação oficial da API por unidades de negócio e ao desenho operacional já existente no sistema.

## Estado Atual

O repositório já possui base funcional para:

- OAuth 2.0 com PKCE
- persistência multi-conta em `ml_accounts`
- refresh automático de tokens
- cliente HTTP resiliente
- workers assíncronos
- webhooks com inbox idempotente
- logs estruturados
- health check operacional

Os principais gaps atuais são:

- sobreposição de responsabilidades entre workers
- coexistência de componentes legados e novos
- cobertura desigual entre testes estruturais e comportamentais
- documentação funcional ainda não consolidada por domínio
- critérios de aceite ainda implícitos em parte da implementação

## Princípios da Integração

- usar a documentação oficial do Mercado Livre como contrato externo primário
- organizar a integração por domínio de negócio, não por controller isolado
- centralizar autenticação, retry, rate limit, observabilidade e classificação de erros
- privilegiar webhooks como fonte primária de eventos e polling como compensação
- manter todas as mutações críticas auditáveis por conta, endpoint e operação
- validar tudo em staging antes de promover para produção

## Arquitetura Alvo

### 1. Camada de Autenticação

Responsável por:

- authorization code flow com PKCE
- troca de `code` por token
- refresh token com lock por conta
- diagnóstico operacional de configuração
- reconexão obrigatória em `invalid_grant`

Base atual:

- `app/Services/MercadoLivreAuthService.php`
- `app/Controllers/AuthController.php`

### 2. Camada de Cliente HTTP Central

Responsável por:

- headers padrão
- timeout
- retry com backoff
- tratamento de `Retry-After`
- política de proxy
- circuit breaker
- classificação de erro
- métricas por endpoint

Base atual:

- `app/Services/MercadoLivreClient.php`

### 3. Camada de Serviços por Domínio

Cada domínio deve expor interface estável para o restante do sistema.

Serviços-alvo:

- `MlAccountsService`
- `MlItemsService`
- `MlOrdersService`
- `MlQuestionsService`
- `MlShipmentsService`
- `MlPaymentsService`
- `MlClaimsService`
- `MlMessagesService`
- `MlCatalogService`
- `MlPricingService`

### 4. Camada de Orquestração

Responsável por:

- coordenar sync inicial e incremental
- reconciliar dados de webhook com polling
- controlar checkpoints
- padronizar jobs e cron

Base atual:

- `app/Services/MercadoLivreOrchestratorService.php`
- workers em `bin/`

### 5. Camada de Entrada Assíncrona

Responsável por:

- receber webhook
- validar assinatura
- deduplicar
- persistir inbox
- processar
- reprocessar falhas

Base atual:

- `app/Controllers/MercadoLivreWebhookController.php`
- `app/Services/WebhookInboxService.php`
- `app/Services/MercadoLivreWebhookService.php`
- `app/Services/MercadoLivreWebhookReplayService.php`

### 6. Camada de Observabilidade

Responsável por:

- logs estruturados
- métricas de latência e erro
- trilha de auditoria
- dashboards operacionais
- health checks

Base atual:

- `app/Services/StructuredLogService.php`
- `app/Services/AuditLogService.php`
- `bin/ml-health-check.php`

## Fases de Implementação

## Fase 0 — Inventário e Contratos

### Objetivos

- mapear todos os endpoints oficiais relevantes
- associar cada endpoint ao domínio interno correto
- definir contrato funcional por domínio

### Entregáveis

- matriz de endpoints por domínio
- lista de endpoints obrigatórios, opcionais e futuros
- contrato interno de cada serviço de domínio

### Critérios de aceite

- todos os endpoints críticos do negócio estão classificados
- todo endpoint mapeado aponta para um dono técnico interno
- toda integração tem estratégia definida: síncrona, assíncrona ou híbrida

## Fase 1 — Núcleo OAuth e Conta

### Escopo

- autorização OAuth 2.0
- callback
- refresh
- health de configuração
- reconexão
- seleção de conta ativa

### Entregáveis

- fluxo OAuth estabilizado
- diagnóstico `oauth-config-status`
- health check CLI operacional
- runbook de falhas OAuth

### Critérios de aceite

- conectar conta real com sucesso
- validar `/users/me` após callback
- refresh automático funcionando
- `invalid_grant` dispara reconexão e não retry infinito
- logs estruturados por fase do fluxo

## Fase 2 — Cliente HTTP e Resiliência

### Escopo

- consolidar todo acesso HTTP ML no cliente central
- aplicar retry, timeout, rate limit e classificação de erro

### Entregáveis

- catálogo de erros normalizado
- política única de retry/backoff
- rate limit policy por endpoint
- métricas por request

### Critérios de aceite

- nenhum domínio ML novo faz chamada HTTP direta fora do cliente central
- toda falha retorna `error_type` consistente
- respostas 429 respeitam `Retry-After`

## Fase 3 — Domínios Core

### Escopo prioritário

- contas
- anúncios / items
- pedidos
- perguntas
- envios

### Entregáveis

- services por domínio
- sync incremental por conta
- DTOs e contratos internos estáveis
- persistência local auditável

### Critérios de aceite

- leitura e sincronização estável dos domínios core
- atualização local consistente
- operações críticas com logs e auditoria
- dashboards conseguem consumir dados consistentes

## Fase 4 — Webhooks e Reconciliação

### Escopo

- padronizar webhook ingress
- consolidar inbox idempotente
- reconciliar polling vs. evento

### Entregáveis

- fluxo webhook canônico
- replay automatizado
- métricas de backlog e falhas
- redução de polling redundante

### Critérios de aceite

- eventos duplicados não geram mutação duplicada
- falhas podem ser reprocessadas
- backlog é observável
- polling funciona como contingência, não como fonte primária

## Fase 5 — Financeiro, Pós-venda e Mensageria

### Escopo

- payments
- claims
- messages
- feedback

### Entregáveis

- serviços de domínio adicionais
- regras de reconciliação com pedidos
- trilha de auditoria por evento

### Critérios de aceite

- cada evento financeiro ou de pós-venda é rastreável até conta/pedido
- falhas ficam classificadas por domínio

## Fase 6 — Governança, Analytics e Automação

### Escopo

- pricing
- analytics
- governança da conta
- automações inteligentes

### Entregáveis

- serviços avançados desacoplados do core
- feature flags
- métricas de valor de negócio

### Critérios de aceite

- módulos avançados não introduzem dependência forte sobre o core
- falhas avançadas não derrubam a trilha operacional principal

## Estratégia de Endpoints

### Obrigatórios no Core

- OAuth / identidade
- `/users/me`
- anúncios
- pedidos
- perguntas
- envios
- webhooks de pedidos, itens, perguntas, envios, pagamentos e reclamações

### Obrigatórios na Operação Estendida

- pagamentos
- claims
- messages
- feedback

### Opcionais / Fase Posterior

- pricing avançado
- analytics avançado
- módulos de otimização e governança além do core commerce

## Tratamento de Erros

### Taxonomia mínima

- `authentication`
- `authorization`
- `rate_limit`
- `timeout`
- `network`
- `proxy`
- `ssl`
- `provider_4xx`
- `provider_5xx`
- `api_validation`
- `reconnect_required`

### Regras

- retry automático apenas para falhas transitórias
- `invalid_grant` sempre vira reconexão
- `403` precisa ser classificado por contexto: escopo, política, conta desconectada ou bloqueio
- falha em webhook não pode ser descartada sem persistência de erro

## Rate Limit e Performance

### Diretrizes

- respeitar cabeçalhos do provedor
- usar backoff exponencial com jitter
- separar limites por domínio crítico
- não executar workers concorrentes com responsabilidade duplicada

### Ação arquitetural necessária

- consolidar `auto-token-refresh-worker.php` com os workers dedicados de pedidos, perguntas e envios
- definir um único desenho operacional para evitar carga duplicada e competição por rate limit

## Staging

### Requisitos

- ambiente com banco segregado
- conta ML dedicada ou controlada
- webhook endpoint próprio
- logs segregados
- flags para bloquear mutações destrutivas

### Cenários obrigatórios

- conectar conta
- trocar token
- refresh automático
- `/users/me`
- sync de items
- sync de pedidos
- sync de perguntas
- recepção de webhook
- replay de webhook
- falha de proxy
- falha de rate limit

### Gate de promoção

- `ml-health-check` sem erro crítico
- smoke tests de OAuth e sync aprovados
- backlog de webhook zerado ou controlado

## Estratégia de Testes

### Testes unitários

- cliente HTTP
- auth service
- classificador de erros
- policy de retry
- domain services
- mapeamento de payloads

### Testes de integração

- OAuth end-to-end controlado
- refresh com persistência local
- webhook inbox
- replay de webhook
- sync incremental por conta
- execução CLI de workers

### Testes E2E / smoke

- conectar conta
- listar conta ativa
- validar sync básico
- processar evento crítico

### Meta de qualidade

- priorizar testes comportamentais nos fluxos críticos
- reduzir dependência de testes puramente estruturais

## Monitoramento e Logs

### Campos mínimos de log

- `request_id`
- `account_id`
- `ml_user_id`
- `endpoint`
- `operation`
- `status_code`
- `latency_ms`
- `error_type`
- `retry_count`

### Métricas mínimas

- sucesso por endpoint
- latência p50 e p95
- 429 por endpoint
- falha de refresh por conta
- backlog de webhook
- idade do último sync por domínio

### Alertas

- `invalid_grant`
- conta desconectada
- spikes de 429
- proxy inválido
- falha persistente em `/users/me`
- backlog de webhook acima do limite

## Documentação Técnica

### Obrigatória

- arquitetura de integração
- matriz de endpoints
- catálogo de erros
- runbook OAuth
- runbook webhook
- runbook rate limit
- operação de staging
- critérios de aceite

### Artefatos recomendados

- `docs/guides/ML_INTEGRATION_EXECUTION_PLAN.md`
- `docs/guides/ML_ENDPOINT_DOMAIN_MATRIX.md`
- `docs/guides/ML_STAGING_VALIDATION_CHECKLIST.md`

## Critérios de Aceite por Domínio

### Contas / OAuth

- conectar, persistir, selecionar e reconectar conta com sucesso

### Items

- sincronização confiável e consistente por conta

### Orders

- pedidos novos e atualizações refletidos localmente

### Questions

- perguntas entram e podem ser respondidas de forma auditável

### Shipments

- tracking e status atualizados corretamente

### Payments / Claims / Messages

- trilha auditável e reconciliação por pedido/conta

### Webhooks

- assinatura válida, deduplicação, persistência e replay

## Riscos Principais do Programa

- duplicidade operacional entre workers
- deriva entre documentação e rotas ativas
- gaps de testes comportamentais reais
- dependência forte da configuração correta de OAuth
- pressão de rate limit por desenho de execução concorrente

## Quick Wins

- consolidar matriz de endpoints oficiais por domínio
- reduzir sobreposição entre workers
- formalizar runbook de `invalid_grant`
- transformar health checks em gates de staging
- alinhar documentação de webhook com rotas ativas

## Próximos Artefatos

Após este plano, os próximos documentos operacionais devem ser:

1. matriz de endpoints por domínio
2. checklist de staging
3. backlog por fase com donos técnicos
4. critérios de aceite detalhados por fluxo
