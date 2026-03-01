# Plano por Fases - Integracao Real com API do Mercado Livre

## Objetivo
Fechar a integracao real com a API do Mercado Livre em producao, eliminando erros recorrentes (401/403/422/500 operacionais), estabilizando OAuth multi-conta, schema, workers e observabilidade.

Data de referencia: 27/02/2026.

## Estado atual (baseline)
- `bash bin/init.sh` executa com sucesso.
- `php vendor/bin/phpunit --testsuite Integration --no-coverage --colors=never` verde (sem falhas; com testes `risky` ja conhecidos).
- `php vendor/bin/phpunit --testsuite Unit --no-coverage --colors=never` verde.
- Correcoes recentes ja aplicadas:
  - `500` em `/api/analytics/customer-ltv` corrigido.
  - `401` por sync invalido na UI reduzido com guard rails.
  - CSP em `accounts` e layout modernizado sem handlers inline na tela corrigida.

## Catalogo de erros a corrigir (prioridade)

| ID | Erro observado | Impacto | Causa raiz provavel | Correcao necessaria |
|---|---|---|---|---|
| ML-001 | `invalid_grant` no refresh + `401 unauthorized` em `/users/me` e `/api/accounts/{id}/sync` | Conta desconectada, sync falha | Refresh token gerado com outro `client_id` ou token expirado irrecuperavel | Forcar reautorizacao OAuth das contas invalidas e bloquear sync ate reauth |
| ML-002 | `Sem token configurado para ML API` (logs recorrentes) | Ruido operacional e diagnostico falso-negativo | Ciclos de health/worker sem conta ativa/token valido | Ajustar health para degradado controlado quando nao houver conta ativa; reduzir severidade/log flood |
| ML-003 | `403` em `GET /sites/MLB?client_id=...` (`PA_UNAUTHORIZED_RESULT_FROM_POLICIES`) | Health marca API como indisponivel mesmo com internet ativa | Check publico com `client_id` sendo bloqueado por policy do ML | Revisar check de conectividade publica e fallback; nao tratar como critical sem contexto de conta |
| ML-004 | `Table 'worker_execution_logs' doesn't exist` e `Table 'clone_sync_logs' doesn't exist` | Monitoramento parcial quebrado | Migrations de observabilidade/sync nao aplicadas de forma completa | Executar reconciliacao de schema idempotente e validar tabelas criticas |
| ML-005 | Historico de `Access denied` MySQL com `root` | Indisponibilidade de API e middleware | Credencial/usuario inadequado em ambiente | Padronizar usuario de app com grants minimos e remover dependencia de `root` |
| ML-006 | `422` em `/api/orders/all?limit=6` em cenarios sem seller ativo | Dashboard degradado | Conta ativa sem seller/token para origem remota | Garantir fallback local onde aplicavel e resposta acionavel no backend |
| ML-007 | Testes `risky` sem assert (`CloneAlertNotificationServiceTest`, `CloneDuplicateDetectionServiceTest`) | Cobertura de regressao incompleta | Casos sem assercao explicita | Adicionar assercoes e remover risco de falso positivo |
| ML-008 | Segredos em scripts de teste (`tests/scripts`, `tests/manual`) | Risco de seguranca e vazamento | Credenciais hardcoded | Substituir por leitura de env e sanitizar historico/repositorio |

## Fases de implementacao

## Fase 0 - Preparacao da janela
Objetivo: reduzir risco de rollback incompleto.

### Tarefas
- Snapshot do banco (`mysqldump`) e backup de `.env` e `crontab`.
- Pausar cron/workers de alto volume (refresh token, queue, clone monitor).
- Registrar baseline de logs e status de contas.

### Validacao
- `bash bin/init.sh`
- `php bin/production-check.php`

### Criterio de saida
- Backups confirmados e workers pausados com checklist de retomada.

## Fase 1 - Infraestrutura e configuracao obrigatoria
Objetivo: garantir conectividade estavel de MySQL/Redis e variaveis minimas de producao.

### Tarefas
- Confirmar e padronizar em producao:
  - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
  - `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`
  - `ML_APP_ID`, `ML_CLIENT_SECRET`, `ML_REDIRECT_URI`
- Trocar uso de `root` por usuario de aplicacao com grants minimos no schema `meli`.
- Validar MySQL/Redis via CLI e via bootstrap PHP.

### Validacao
- `php bin/production-check.php`
- `bash bin/fix-infrastructure.sh --check-only`

### Criterio de saida
- Zero erro novo de conexao DB/Redis em logs apos reativacao parcial.

## Fase 2 - OAuth e contas ML (integracao real)
Objetivo: eliminar falhas `invalid_grant` e restaurar contas conectadas de verdade.

### Tarefas
- Auditar `ml_accounts` (status, `token_status`, `last_refresh_error`).
- Marcar contas com `invalid_grant` como `disconnected`.
- Forcar reautorizacao de todas as contas invalidas pelo fluxo OAuth oficial.
- Validar par `ML_APP_ID`/`ML_CLIENT_SECRET` igual ao usado para gerar os refresh tokens.
- Bloquear sincronizacao para contas desconectadas ate concluir reauth.

### Validacao
- `GET /users/me` com conta ativa sem `401`.
- `POST /api/accounts/{id}/sync` apenas para contas elegiveis.

### Criterio de saida
- Zero novo `401 unauthorized` por token invalido nas contas reautorizadas.

## Fase 3 - Reconciliacao de schema (idempotente)
Objetivo: remover erros de tabela ausente e inconsistencias de enum.

### Tarefas
- Aplicar migration: `database/migrations/2026_02_26_000001_stabilize_production_schema.sql`.
- Garantir tabelas operacionais:
  - `worker_execution_logs`
  - `clone_health_logs`
  - `clone_duplicate_registry`
  - `clone_sync_logs` (usada pelo monitor fallback)
- Ajustar enums para valores usados em runtime:
  - `ml_accounts.status` inclui `disconnected`
  - `token_refresh_audit.action` inclui `refresh_disconnected`

### Validacao
- `SHOW TABLES` e `SHOW COLUMNS` das tabelas criticas.
- Ausencia de erros `Table ... doesn't exist` e `Data truncated` nos logs novos.

### Criterio de saida
- Monitoramento e fluxo de refresh executando sem erro de schema.

## Fase 4 - Hardening do cliente ML e health checks
Objetivo: evitar falso critical em conectividade e reduzir ruido de log.

### Tarefas
- Revisar `MercadoLivreClient::diagnose()` para:
  - nao classificar como indisponibilidade total quando nao existe conta/token ativo.
  - revisar check publico em `/sites/MLB` com `client_id` quando houver bloqueio por policy.
- Ajustar severidade para cenarios sem token (warning controlado, sem flood).
- Manter comportamento fail-fast para conta `disconnected` em chamadas autenticadas.

### Validacao
- Rodar health monitor com e sem conta ativa.
- Confirmar queda de logs repetitivos `Sem token configurado` e `403 /sites/MLB`.

### Criterio de saida
- Alertas passam a refletir erro real de operacao, nao falta de contexto.

## Fase 5 - Endpoints e comportamento funcional
Objetivo: garantir API e dashboard com resposta previsivel em estado degradado.

### Tarefas
- Manter fallback para pedidos recentes (`allow_local_cache`) onde aplicavel.
- Padronizar respostas de erro para sync/account:
  - conta desconectada -> resposta acionavel de reconexao
  - conta nao elegivel -> bloquear acao antes de chamar ML
- Auditar endpoints de analytics para evitar regressao de SQL mode (`ONLY_FULL_GROUP_BY`).

### Validacao
- Smoke manual:
  - login dashboard
  - listagem de pedidos
  - sync por conta
  - endpoint de monitoramento

### Criterio de saida
- Sem regressao funcional em uso real do dashboard.

## Fase 6 - Workers, fila e webhook
Objetivo: manter processamento assincrono confiavel com lock e replay.

### Tarefas
- Garantir lock em refresh token para evitar concorrencia entre processos.
- Validar cadeia: webhook inbox -> fila -> worker -> persistencia.
- Reprocessar eventos falhos via fluxo de replay quando necessario.
- Confirmar isolamento de Redis no ambiente de testes (`REDIS_DB`) e politica de DB em producao.

### Validacao
- Execucao assistida dos workers:
  - token refresh -> queue -> clone monitor
- Analise de `app.log`, `token_refresh.log`, `queue.log`.

### Criterio de saida
- Fila e webhook processando sem backlog anormal e sem duplicidade critica.

## Fase 7 - Qualidade, seguranca e go-live
Objetivo: fechar criterio tecnico de entrega e governanca.

### Tarefas
- Corrigir testes `risky` sem assert.
- Remover credenciais hardcoded em scripts de teste/manual.
- Rodar suites completas na ordem:
  - `php vendor/bin/phpunit --testsuite Integration --no-coverage --colors=never`
  - `php vendor/bin/phpunit --testsuite Unit --no-coverage --colors=never`
- Executar smoke final de producao com conta ML real conectada.

### Criterio de saida
- Suites verdes + zero erro novo critico por 24h em producao.

## Checklist final de aceite
- `bin/init.sh` sem erro critico de DB/Redis.
- Zero novo erro de conexao MySQL/Redis em `storage/logs/app.log` por 24h.
- Zero `401 unauthorized` por token invalido em contas reautorizadas.
- Zero erro `Table ... doesn't exist` em tabelas criticas.
- Zero warning `Data truncated` em colunas de status/action.
- Integration e Unit verdes.

## Rollback (se taxa de erro subir)
- Reaplicar `.env` de backup.
- Pausar workers imediatamente.
- Restaurar schema/data via dump da janela.
- Reativar apenas leitura e monitorar antes de retomar escrita.

## Arquivos e scripts chave desta execucao
- `app/Services/MercadoLivreClient.php`
- `app/Services/MercadoLivreAuthService.php`
- `app/Services/CloneHealthMonitorService.php`
- `database/migrations/2026_02_26_000001_stabilize_production_schema.sql`
- `database/migrations/2025_06_clone_sync_tables.php`
- `bin/init.sh`
- `bin/production-check.php`
- `bin/fix-infrastructure.sh`

