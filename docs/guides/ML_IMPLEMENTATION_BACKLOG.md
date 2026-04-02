# Backlog Executável — Integração Mercado Livre

## Objetivo

Traduzir o plano da integração Mercado Livre em um backlog técnico executável, com frentes de trabalho, dependências, arquivos-alvo, entregáveis e critérios de pronto.

## Convenções

- **Prioridade**
  - `P0` bloqueante para operação real
  - `P1` importante para operação estável
  - `P2` evolutivo
- **Status sugerido**
  - `todo`
  - `doing`
  - `done`
- **Tipo**
  - `core`
  - `infra`
  - `test`
  - `ops`
  - `docs`

## Fase 0 — Inventário e Governança

### ML-BLG-001 — Consolidar inventário oficial de endpoints

- **Prioridade:** P0
- **Tipo:** docs
- **Dependências:** nenhuma
- **Objetivo:** fechar a lista oficial de famílias de endpoints realmente suportadas pelo produto
- **Arquivos-alvo:**
  - `docs/guides/ML_ENDPOINT_DOMAIN_MATRIX.md`
  - `docs/guides/ML_INTEGRATION_EXECUTION_PLAN.md`
- **Entregáveis:**
  - matriz revisada por domínio
  - classificação `P0/P1/P2`
  - indicação de modo `sync/async/hybrid`
- **Pronto quando:**
  - todos os domínios core tiverem dono técnico
  - não existirem endpoints críticos sem domínio associado

### ML-BLG-002 — Consolidar desenho operacional assíncrono

- **Prioridade:** P0
- **Tipo:** infra
- **Dependências:** ML-BLG-001
- **Objetivo:** remover sobreposição entre workers dedicados e o worker combinado
- **Arquivos-alvo:**
  - `bin/auto-token-refresh-worker.php`
  - `bin/orders-sync-worker.php`
  - `bin/questions-sync-worker.php`
  - `bin/shipments-sync-worker.php`
  - `current_crontab`
- **Entregáveis:**
  - desenho único de runtime assíncrono
  - tabela de responsabilidade por worker
  - cron sem duplicidade de função
- **Pronto quando:**
  - cada domínio tiver apenas uma estratégia operacional principal
  - não houver sync concorrente redundante por conta

### ML-BLG-003 — Encerrar deriva entre rotas e documentação de webhook

- **Prioridade:** P0
- **Tipo:** docs
- **Dependências:** ML-BLG-001
- **Objetivo:** alinhar setup, rota ativa e runbooks de webhook
- **Arquivos-alvo:**
  - `app/Routes/webhooks.php`
  - `docs/setup/WEBHOOK_SETUP.md`
  - `docs/guides/ML_INTEGRATION_EXECUTION_PLAN.md`
- **Entregáveis:**
  - documentação atualizada da rota canônica
  - remoção de referências obsoletas
- **Pronto quando:**
  - documentação e runtime indicarem o mesmo endpoint de ingresso

## Fase 1 — OAuth, Conta e Configuração

### ML-BLG-010 — Endurecer contrato de configuração OAuth

- **Prioridade:** P0
- **Tipo:** core
- **Dependências:** nenhuma
- **Objetivo:** tornar a configuração inválida bloqueante em todos os entry points relevantes
- **Arquivos-alvo:**
  - `app/Services/MercadoLivreAuthService.php`
  - `app/Controllers/AuthController.php`
  - `app/Views/dashboard/accounts.php`
  - `app/Views/dashboard/settings-content.php`
- **Entregáveis:**
  - diagnóstico consistente em todas as telas
  - bloqueio seguro de tentativa com configuração inválida
- **Pronto quando:**
  - contas e settings exibirem o mesmo estado operacional
  - todo fluxo de conexão falhar de forma guiada e auditável

### ML-BLG-011 — Formalizar runbook de reconexão

- **Prioridade:** P0
- **Tipo:** ops
- **Dependências:** ML-BLG-010
- **Objetivo:** padronizar a resposta para `invalid_grant`, conta desconectada e redirect inválido
- **Arquivos-alvo:**
  - `docs/guides/ML_CONNECTION_TROUBLESHOOTING.md`
  - `docs/guides/ML_STAGING_VALIDATION_CHECKLIST.md`
- **Entregáveis:**
  - checklist de diagnóstico
  - procedimento de reconexão
  - critérios de escalonamento
- **Pronto quando:**
  - suporte/ops conseguirem diagnosticar a falha sem leitura manual de código

### ML-BLG-012 — Cobrir OAuth com testes comportamentais de ponta a ponta

- **Prioridade:** P0
- **Tipo:** test
- **Dependências:** ML-BLG-010
- **Objetivo:** elevar a confiança dos fluxos reais de authorize, callback, refresh e reconexão
- **Arquivos-alvo:**
  - `tests/Feature/MLOAuthFlowTest.php`
  - `tests/Unit/Services/MercadoLivreAuthServiceOauthUrlTest.php`
  - `tests/e2e/ml-integration.spec.ts`
- **Entregáveis:**
  - cenários reais de erro e sucesso
  - validação do comportamento de reconexão
- **Pronto quando:**
  - os cenários `success`, `invalid_grant`, `callback incompleto` e `config inválida` estiverem cobertos

## Fase 2 — Cliente HTTP, Erros e Rate Limit

### ML-BLG-020 — Consolidar todo tráfego ML no cliente central

- **Prioridade:** P0
- **Tipo:** core
- **Dependências:** ML-BLG-001
- **Objetivo:** impedir chamadas HTTP diretas fora do núcleo de integração
- **Arquivos-alvo:**
  - `app/Services/MercadoLivreClient.php`
  - `app/Services/MercadoLivre/*.php`
  - `app/Services/OrderService.php`
  - `app/Services/QuestionService.php`
- **Entregáveis:**
  - inventário de chamadas diretas
  - migração das chamadas para o client central
- **Pronto quando:**
  - novas integrações não usarem `curl_*` diretamente para ML

### ML-BLG-021 — Formalizar classificador de erros ML

- **Prioridade:** P0
- **Tipo:** core
- **Dependências:** ML-BLG-020
- **Objetivo:** normalizar `authentication`, `authorization`, `rate_limit`, `timeout`, `network`, `ssl`, `proxy`, `provider_4xx`, `provider_5xx`
- **Arquivos-alvo:**
  - `app/Services/MercadoLivreClient.php`
  - `app/Services/MercadoLivreAuthService.php`
  - `docs/guides/ML_INTEGRATION_EXECUTION_PLAN.md`
- **Entregáveis:**
  - taxonomia de erro
  - payload de erro consistente
- **Pronto quando:**
  - logs e serviços retornarem `error_type` padronizado

### ML-BLG-022 — Padronizar rate limit e backoff

- **Prioridade:** P0
- **Tipo:** infra
- **Dependências:** ML-BLG-020
- **Objetivo:** centralizar retry, `Retry-After`, backoff exponencial e circuit breaker
- **Arquivos-alvo:**
  - `app/Services/MercadoLivreClient.php`
  - `tests/Unit/Services/MercadoLivreClientTest.php`
- **Entregáveis:**
  - política única de retry
  - testes de 429 e falha transitória
- **Pronto quando:**
  - chamadas 429 respeitarem o provedor e não gerarem storm

## Fase 3 — Domínios Core

### ML-BLG-030 — Consolidar serviço de itens

- **Prioridade:** P0
- **Tipo:** core
- **Dependências:** ML-BLG-020
- **Objetivo:** unificar leitura, sync e mutações essenciais de anúncios
- **Arquivos-alvo:**
  - `app/Services/MercadoLivre`
  - `app/Services/MercadoLivreOrchestratorService.php`
  - workers de sync relacionados
- **Entregáveis:**
  - contrato único de itens
  - sync incremental por conta
- **Pronto quando:**
  - itens estiverem reconciliados de forma estável e auditável

### ML-BLG-031 — Consolidar serviço de pedidos

- **Prioridade:** P0
- **Tipo:** core
- **Dependências:** ML-BLG-020
- **Objetivo:** padronizar pedido, shipment e reconciliação com pagamento
- **Arquivos-alvo:**
  - `app/Services/OrderService.php`
  - `bin/orders-sync-worker.php`
  - webhook services relacionados
- **Entregáveis:**
  - sync incremental confiável
  - atualização por evento
- **Pronto quando:**
  - pedido novo e update de status refletirem localmente sem duplicação

### ML-BLG-032 — Consolidar serviço de perguntas

- **Prioridade:** P0
- **Tipo:** core
- **Dependências:** ML-BLG-020
- **Objetivo:** unificar recebimento, resposta e auditoria de perguntas
- **Arquivos-alvo:**
  - `app/Services/QuestionService.php`
  - `bin/questions-sync-worker.php`
  - `app/Services/MercadoLivreWebhookService.php`
- **Entregáveis:**
  - pipeline de pergunta do ingresso à resposta
- **Pronto quando:**
  - perguntas puderem ser recebidas e respondidas com rastreabilidade completa

## Fase 4 — Webhooks e Inbox

### ML-BLG-040 — Tornar webhook a trilha principal de atualização

- **Prioridade:** P0
- **Tipo:** core
- **Dependências:** ML-BLG-002, ML-BLG-030, ML-BLG-031, ML-BLG-032
- **Objetivo:** reduzir polling redundante e privilegiar evento assíncrono
- **Arquivos-alvo:**
  - `app/Controllers/MercadoLivreWebhookController.php`
  - `app/Services/WebhookInboxService.php`
  - `app/Services/MercadoLivreWebhookService.php`
- **Entregáveis:**
  - fluxo canônico de ingestão e processamento
- **Pronto quando:**
  - atualização por webhook for preferencial e polling virar compensação

### ML-BLG-041 — Cobrir replay, deduplicação e backlog

- **Prioridade:** P0
- **Tipo:** test
- **Dependências:** ML-BLG-040
- **Objetivo:** garantir resiliência operacional do inbox
- **Arquivos-alvo:**
  - `tests/Unit/Services/MercadoLivreWebhookServiceTest.php`
  - `tests/Integration/MercadoLivreWebhookInboxQueueTransitionTest.php`
  - `tests/Unit/Services/MercadoLivreWebhookReplayServiceTest.php`
- **Entregáveis:**
  - testes de duplicidade
  - testes de replay
  - testes de falha transitória
- **Pronto quando:**
  - nenhum evento duplicado gerar mutação duplicada

## Fase 5 — Staging, Smoke e Operação

### ML-BLG-050 — Formalizar gate de staging

- **Prioridade:** P0
- **Tipo:** ops
- **Dependências:** ML-BLG-012, ML-BLG-022, ML-BLG-040
- **Objetivo:** transformar staging em etapa obrigatória de promoção
- **Arquivos-alvo:**
  - `docs/guides/ML_STAGING_VALIDATION_CHECKLIST.md`
  - `bin/ml-health-check.php`
  - smoke tests existentes
- **Entregáveis:**
  - checklist executável
  - gate de promoção
- **Pronto quando:**
  - nenhuma release ML subir sem health check e smoke básicos

### ML-BLG-051 — Unificar observabilidade operacional ML

- **Prioridade:** P1
- **Tipo:** ops
- **Dependências:** ML-BLG-020, ML-BLG-040
- **Objetivo:** agregar saúde de OAuth, refresh, webhooks e sync em visão única
- **Arquivos-alvo:**
  - `app/Services/StructuredLogService.php`
  - dashboards/controladores de monitoramento
  - `bin/ml-health-check.php`
- **Entregáveis:**
  - métricas por conta e por domínio
  - alertas operacionais
- **Pronto quando:**
  - for possível identificar rapidamente conta desconectada, backlog ou spike de erro

## Fase 6 — Domínios Estendidos

### ML-BLG-060 — Payments, claims e messages

- **Prioridade:** P1
- **Tipo:** core
- **Dependências:** Fases 3 e 4 concluídas
- **Objetivo:** completar a operação ampliada com pós-venda e financeiro
- **Arquivos-alvo:**
  - `app/Services/MercadoLivreWebhookService.php`
  - novos services de domínio conforme necessário
- **Entregáveis:**
  - trilha auditável por pedido/conta
- **Pronto quando:**
  - eventos financeiros e de pós-venda estiverem reconciliados

### ML-BLG-061 — Analytics e pricing avançado

- **Prioridade:** P2
- **Tipo:** core
- **Dependências:** domínios core estáveis
- **Objetivo:** evoluir a plataforma sem acoplar risco ao core commerce
- **Arquivos-alvo:**
  - `app/Services/MercadoLivre/`
  - módulos avançados de pricing e analytics
- **Entregáveis:**
  - módulos desacoplados por feature flag
- **Pronto quando:**
  - falhas avançadas não impactarem a operação core

## Dependências Críticas

### Sequência mínima recomendada

1. `ML-BLG-001`
2. `ML-BLG-002`
3. `ML-BLG-010`
4. `ML-BLG-020`
5. `ML-BLG-021`
6. `ML-BLG-022`
7. `ML-BLG-030`, `ML-BLG-031`, `ML-BLG-032`
8. `ML-BLG-040`
9. `ML-BLG-041`
10. `ML-BLG-050`

## Definition of Done Transversal

Um item do backlog só pode ser marcado como concluído quando:

- a implementação estiver aplicada
- logs estruturados estiverem coerentes
- houver teste unitário ou de integração compatível com o risco
- a documentação operacional estiver atualizada quando aplicável
- o fluxo estiver validado em staging ou por smoke controlado

## Próximo Sprint Recomendado

### Sprint 1

- `ML-BLG-001`
- `ML-BLG-002`
- `ML-BLG-003`
- `ML-BLG-010`

### Sprint 2

- `ML-BLG-012`
- `ML-BLG-020`
- `ML-BLG-021`
- `ML-BLG-022`

### Sprint 3

- `ML-BLG-030`
- `ML-BLG-031`
- `ML-BLG-032`

### Sprint 4

- `ML-BLG-040`
- `ML-BLG-041`
- `ML-BLG-050`
