# Changelog - Mercado Livre Manager

Todas as mudanças notáveis neste projeto serão documentadas aqui.

## [1.8.7] - 2026-02-18

### 🧹 Higiene de Repositório (Fase 0)

#### Documentação consolidada
- ✅ Conteúdo de hardening consolidado neste changelog (arquivo dedicado removido para evitar duplicação).
- ✅ Relatórios redundantes de sessão removidos da raiz para reduzir ruído operacional.

#### Segurança (consolidado de 2026-02-15)
- ✅ Controle fino de rate limit duplicado via `SECURITY_MW_RATE_LIMIT_ENABLED`.
- ✅ Detecção contextual de resposta JSON/HTML no `ExceptionHandler::wantsJson()`.
- ✅ Controle de headers legados via `SECURITY_HEADERS_LEGACY_ENABLED`.
- ✅ Correções de chamadas inválidas em `CloneAdvancedController` e `SettingsController`.

#### Validação
- ✅ Base preparada para execução incremental por fases, com redução de arquivos lixo e documentação duplicada.

## [1.8.6] - 2026-02-16

### ✅ Hardening Anti-Mock — Consolidação de Implementações Reais

#### API e Scripts Operacionais
- ✅ **`api/realtime_db_api.php`** alinhado com autenticação JWT real (`JwtService`) e rate limiting persistido em `rate_limits`.
- ✅ **`bin/tech-sheet-cache-warmup.php`** corrigido para usar helper de cache real (`cache()->get/set`) em vez de chamada estática incompatível.
- ✅ **`scripts/recalculate_order_metrics.php`** migrou de método inexistente para cálculo financeiro real baseado em `order_data`.
- ✅ **`scripts/cron_tech_sheet_update.php`** removeu dependência de `SyncStatusService` inexistente e passou a persistir status em `sync_status`.

#### Qualidade e Validação
- ✅ Análise Codacy executada após as edições, sem issues pendentes nos arquivos modificados.
- ✅ Validação de sintaxe (`php -l`) concluída com sucesso para os arquivos críticos alterados.
- ✅ Diagnóstico global do workspace sem erros ao final da rodada.

### 📝 Arquivos Modificados
- `api/realtime_db_api.php`
- `bin/tech-sheet-cache-warmup.php`
- `scripts/recalculate_order_metrics.php`
- `scripts/cron_tech_sheet_update.php`
- `AUDIT_MOCK_IMPLEMENTATIONS.md`

## [1.8.5] - 2026-02-16

### 🧹 Code Quality & Structured Logging

#### Logging Centralizado — Migração completa de `error_log()` → LogHelper
- ✅ **~330 chamadas `error_log()` substituídas** por funções estruturadas (`log_info()`, `log_warning()`, `log_error()`, `log_debug()`, `log_critical()`) em **100+ arquivos**
- ✅ **21 `error_log()` intencionais mantidos** — infraestrutura de logging (LogHelper, CentralizedLogService, StructuredLogService, Database, ErrorHandler, ExceptionHandler, AI/Core/LoggingService)
- ✅ Contexto estruturado com `['service' => 'NomeDoService', 'error' => $e->getMessage()]` em todas as chamadas

##### Arquivos afetados por categoria:

**Controllers (14 arquivos)**
- CompetitorAnalysisController, SEOKillerController, AccountHealthController, AuthController, BaseController, PdfController, AuthApiController, AIController, SyncController, DashboardController, MonitoringController, EanController, WebhookController, SEOMonitoringJob

**Core Services (40+ arquivos)**
- MercadoLivreClient (17), AccountHealthService (27), DeepResearchService (17), VersioningService (11), FeatureFlagService (10), CloneProgressTrackerService (10), NotificationService (7), AdsService (6), TrendsService (5), ItemMetricsService (8), AdvancedRedisCacheService (5), ShippingService (4), InventoryService (4), ErrorMonitoringService (4), CloneReportExportService (4), CloneEventTriggerService (4), ItemService (4), PerformanceMetricsService (4), CompetitorService (3), BrandAnalyzerService (3), Database (3), AlternativeSearchService (3), ShopeeService (3), TechSheetEmailService (5), TechSheetSmartGapFillerService (5), SecureTokenService (7), EmailService (2), TechSheetService (2), ListingAutoCreator (2), CatalogCloneService (6), MobileDeviceService (2), AdvancedMonitoringService (2), GeoIPService (2), MessagingService (2), MonitoringService (2), LoggingService (3→1), PromotionService (3), BrandCentralService (2), ClaudeClient (2), UserService (2), e mais 20+ serviços com 1 substituição

**AI Services (29 arquivos)**
- AIChatbotService (8), SynonymGenerator (6), BulkOptimizer (6), ABTester (5), AttributeKiller (5), SEOScoreCalculator (5), PerformanceTracker/Scoring (4), KeywordSourceService (8), SynonymExpansionService (7), AIImageAnalyzer (3), GoogleSearchConsoleService (3), AIProviderManager (3), CompetitorSpy (2), SEOKillerEngine (2), AIInsightsService (2), AutoPilotStatusManager (2), AIConfigService (2), KeywordClassifierService (2), RateLimiterService (2), SemanticScoreService (2), KeywordResearchService (2), e mais 8 serviços AI com 1 substituição

**Middleware (3 arquivos)**
- AccountContextMiddleware (4), SecurityMiddleware (2), AuthMiddleware (1)

**Outros**
- Router.php (1), TokenRefreshJob (reescrita do método log), UnifiedTokenRefreshService (refatoração do método log)

#### Limpeza de Código
- ✅ Remoção de debug logs excessivos em CompetitorAnalysisController (10 `log_debug` removidos)
- ✅ Cleanup de TODOs e comentários enganosos
- ✅ Consolidação de migrations duplicadas

#### Reorganização da Documentação (`docs/`)
- ✅ **~130 arquivos históricos movidos** de `docs/` raiz para `docs/archive/` organizado em 8 subcategorias
- ✅ **Subdiretorias históricas esvaziadas**: `changelogs/`, `agent/`, `features/`, `implementation/`, `reports/`, `roadmap/`, `status/` → conteúdo movido para `archive/`
- ✅ **8 arquivos sensíveis/históricos de `setup/`** arquivados (credenciais, SSH, deploy v8)
- ✅ **25 documentos de referência ativa mantidos** no root de `docs/`
- ✅ **4 subdiretorias ativas preservadas**: `guides/` (6 guias), `setup/` (3 docs), `security/` (1), `integrations/` (1)
- ✅ **DOCUMENTATION_INDEX.md** reescrito (v2.0.0) refletindo nova estrutura
- Estrutura do archive: `implementation/` (~35), `reports/` (~45), `seo-killer/` (~30), `roadmap/` (~15), `status/` (~13), `changelogs/` (~3), `features/` (~9), `agent/` (~6)

### 🧱 Compatibilidade PDO/MySQL (LIMIT/OFFSET)

- ✅ **Remoção repo-wide de placeholders em `LIMIT/OFFSET`** em queries PDO
  - Motivo: com `PDO::ATTR_EMULATE_PREPARES=false` alguns drivers MySQL falham ao bindar `LIMIT/OFFSET`
  - Abordagem: `int` + clamp + interpolação segura no SQL; binds mantidos apenas para filtros/IDs/strings
  - Escopo: `app/` + scripts operacionais (`api/`, `bin/`, `scripts/`)

### 🌐 Mixed Content (APP_URL ausente)

- ✅ **`config/app.php`**: fallback de `url` agora tenta inferir `scheme://host/basePath` via `$_SERVER` quando `APP_URL` não está definido (mantém fallback legado para CLI)
- ✅ **`JwtService`**: `iss` (issuer) passa a seguir a URL inferida quando disponível, evitando cair em `http://localhost` em requests HTTPS

### 📋 Auditoria de mocks/stubs

- ✅ Auditoria consolidada com **0 pendências** em `AUDIT_MOCK_IMPLEMENTATIONS.md` (mocks/stubs/hardcodes encerrados ou documentados)

## [1.8.4] - 2026-02-15

### 🔥 SEO Killer — Completar Módulo (70% → 95%)

#### Workers e Crons
- ✅ **`bin/seo-performance-worker.php` criado** — Coleta métricas diárias (views, vendas, receita) de itens otimizados
  - Suporta `--account=ID`, `--limit=N`, `--verbose`, `--dry-run`
  - Rate limiting integrado (300ms entre chamadas API)
  - Coleta itens de `seo_optimization_events` + `seo_performance_metrics`
- ✅ **`bin/ab-test-updater.php` corrigido** — Bug fix: chamava `updateActiveTests()` (inexistente), agora chama `updateTests()`
- ✅ **Crontab atualizado** com 2 entradas SEO Killer:
  - A/B Test Updater: `0 2 * * *` (diário às 02:00)
  - Performance Tracker: `0 4 * * *` (diário às 04:00)

#### Migrations Formais
- ✅ **`2026_02_10_create_seo_killer_remaining_tables.sql`** — 7 tabelas extraídas dos `ensureTableExists()` inline:
  - `seo_autopilot_config`, `seo_autopilot_runs`, `seo_item_scores`
  - `seo_optimization_events`, `seo_scores_history`
  - `seo_category_benchmarks`, `seo_score_alerts`

#### View Legada
- ✅ **`SEOKillerController::dashboard()`** redirecionado de `Views/seo-killer/index.php` → `Views/dashboard/seo-killer.php` (versão modular)
- ✅ **`Views/seo-killer/index.php`** marcada como `@deprecated` com redirect automático

#### Documentação
- ✅ **`docs/SEO_KILLER_STATUS.md`** atualizado: 70% → 95%, inventário completo de 33+ services, 100+ rotas, 27 componentes

### 📝 Arquivos Modificados
- `bin/ab-test-updater.php` — Bug fix: updateActiveTests() → updateTests()
- `app/Controllers/SEOKillerController.php` — dashboard() usa view modular
- `app/Views/seo-killer/index.php` — Marcada @deprecated com redirect
- `current_crontab` — 2 crons SEO Killer adicionados
- `docs/SEO_KILLER_STATUS.md` — Reescrito (95% completo)

### 📝 Arquivos Criados
- `bin/seo-performance-worker.php` — Worker de coleta de métricas
- `database/migrations/2026_02_10_create_seo_killer_remaining_tables.sql` — 7 tabelas

## [1.8.3] - 2026-02-14

### 🚀 Production Readiness — Checklist de Produção

#### Migration Runner Unificado
- ✅ **`bin/migrate.php` agora suporta migrations `.php`** — antes só processava `.sql` (14 migrations PHP eram ignoradas)
- ✅ Migrations PHP executadas via `include` com captura de output e tratamento de erros
- ✅ Scripts hardcoded deprecated: `bin/apply-migrations.php`, `bin/apply-health-migration.php`, `bin/apply-pricing-migration.php`

#### Segurança de Configuração
- ✅ **`config/database.php` rejeita credenciais inseguras** em produção — lança `RuntimeException` se `DB_PASSWORD` estiver vazio ou `CHANGE_ME`
- ✅ Validação de `DB_USERNAME` obrigatório em `APP_ENV=production`

#### Crontab Completo
- ✅ **15+ crons adicionados ao `current_crontab`** que estavam apenas nos `.example`:
  - `cleanup-refresh-tokens.php` (diário 03:30)
  - `pricing-worker.php` (horário + concorrentes 6h + alertas 8h)
  - `bulk-seo-worker.php` (*/2min + recover horário)
  - `auto-token-refresh-worker.php` (*/30min)
  - EAN system (`cron_expire_purchases.php`, `cron_ean_daily_report.php`)
  - Clone advanced (ab-testing, roi-sync, seller-recommendations, automation, alert-monitor)
- ✅ Cron redundante `renew_tokens.php` deprecado (já coberto por `refresh_ml_tokens.php` + `auto-token-refresh-worker.php`)

#### Infraestrutura de Deploy
- ✅ **`docker-compose.production.yml`** criado — stack completo com app + MySQL 8.0 + Redis 7 + cron scheduler
- ✅ MySQL com slow query log, innodb buffer pool, max connections configurados
- ✅ Redis com maxmemory 128mb, LRU policy, AOF persistence
- ✅ Volumes persistentes para dados, logs e storage

#### Install Script Profissional
- ✅ **`install.sh` completamente reescrito** — agora valida:
  - APP_KEY >= 32 chars
  - DB_PASSWORD configurado e diferente de `CHANGE_ME`
  - Executa `php bin/migrate.php` automaticamente
  - Cria diretórios de storage com permissões corretas
  - Suporte a `--with-tests` para rodar PHPUnit após instalação

#### Documentação
- ✅ **`docs/PRODUCTION_READINESS.md`** reescrito com checklist atualizado e itens marcados

### 📝 Arquivos Modificados
- `bin/migrate.php` — Suporte a migrations .php + .sql
- `bin/apply-migrations.php` — Marcado deprecated
- `bin/apply-health-migration.php` — Marcado deprecated
- `bin/apply-pricing-migration.php` — Marcado deprecated
- `config/database.php` — Validação de credenciais em produção
- `current_crontab` — 15+ crons adicionados, 1 deprecated
- `install.sh` — Reescrito com validações completas
- `docs/PRODUCTION_READINESS.md` — Checklist atualizado

### 📝 Arquivos Criados
- `docker-compose.production.yml` — Stack de produção completo
- `.trivyignore` — Ignora secrets em .env e backups (arquivos locais, nunca no Git)

#### Segurança CSP (Content Security Policy)
- ✅ **Removido `'unsafe-inline'` do `script-src`** em produção — substituído por `'strict-dynamic'` + nonces
- ✅ `SecurityMiddleware.php` usa `'nonce-{$cspNonce}' 'strict-dynamic'` em vez de `'unsafe-inline'`
- ✅ `SecurityHeadersMiddleware.php` agora usa nonces no `script-src` de produção
- ✅ `style-src` mantém `'unsafe-inline'` (necessário para estilos inline em componentes)
- ✅ Teste `test_index_has_csp_enabled` corrigido para verificar middleware (não index.php diretamente)

#### Queue & Scan de Segurança
- ✅ **`.env.example` atualizado**: `QUEUE_CONNECTION=database` como default (era `sync`)
- ✅ **Trivy scan completo**: 22 issues encontradas — todas são secrets em `.env` e `storage/backups/` (locais, nunca no Git)
- ✅ **`.gitignore` reforçado**: `storage/backups/` explicitamente excluído
- ✅ **AISEOOptimizerService verificado**: todos os scores são cálculos reais com fallbacks heurísticos (sem mocks)

### 📝 Arquivos Adicionais Modificados
- `app/Middleware/SecurityMiddleware.php` — CSP com strict-dynamic + nonces
- `app/Middleware/SecurityHeadersMiddleware.php` — CSP com nonces em produção
- `tests/Unit/Middleware/SecurityMiddlewaresTest.php` — Teste CSP corrigido
- `.env.example` — QUEUE_CONNECTION=database
- `.gitignore` — storage/backups/ adicionado

### 📊 Testes Validados
- ✅ PHPUnit Unit: 1164 testes, 2498 assertions, 0 falhas
- ✅ SecurityMiddlewaresTest: 18/18 testes passando
- ✅ Codacy CLI: 0 issues em todos os arquivos editados (Semgrep + Trivy + Lizard)

---

## [1.8.2] - 2026-01-30

### ✅ Correções - Bulk SEO Async (Ficha Técnica)

#### Sistema Production-Grade para Jobs Async
- ✅ **Migration oficial** - Tabela `bulk_seo_jobs` agora criada via migration (não on-demand)
- ✅ **assertBulkJobsTableExists()** - Substituiu `ensureBulkJobsTableExists()` (validação apenas)
- ✅ **Status transitions unificados** - `pending → queued → processing → completed/failed`
- ✅ **dispatchBackgroundJob()** - Retorna info de dispatch e define status `queued` consistentemente
- ✅ **Endpoint centralizado** - `TechnicalSheetController::bulkJobStatus()` usa `BulkSEOService`
- ✅ **Recover-stuck** - Worker com opção `--recover-stuck` para recuperar jobs travados
- ✅ **Testes de sanidade** - Scripts de teste lifecycle e async flow

### 📝 Arquivos Modificados
- `app/Services/BulkSEOService.php` - Lógica centralizada, validation-only table check
- `app/Controllers/TechnicalSheetController.php` - `bulkJobStatus()` refatorado
- `bin/bulk-seo-worker.php` - Adicionado `recoverStuckJobs()` e `--recover-stuck`
- `crontab.bulk-seo.example` - Exemplo de cron para recover-stuck

### 📝 Arquivos Criados
- `database/migrations/2026_01_30_create_bulk_seo_jobs.sql` - Migration oficial
- `bin/test-bulk-seo-job-lifecycle.php` - Teste completo de lifecycle
- `bin/test-bulk-seo-async-flow.php` - Teste rápido de sanidade

### 📊 Testes Validados
- ✅ 9/9 steps do lifecycle test passaram
- ✅ 6/6 steps do async flow test passaram
- ✅ PHPUnit: 874 testes, 2233 assertions

---

## [1.8.1] - 2026-01-29

### ✅ Melhorias

#### Integração AIPricingOptimizer + MarginCalculatorService
- ✅ **AIPricingOptimizer** agora usa custos reais da tabela `product_costs`
- ✅ Método `getRealProductCost()` busca custos cadastrados com fallback para estimativa
- ✅ Método `getCostSource()` identifica origem do custo (real vs estimado)
- ✅ Método `calculateRealMargin()` usa MarginCalculatorService para cálculos precisos
- ✅ Novo método público `getMarginAnalysis()` para análise completa de margem
- ✅ `suggestOptimalPrice()` agora retorna `cost_data` com análise de margem real

### 📝 Arquivos Modificados
- `app/Services/AI/SEO/AIPricingOptimizer.php` - Integração com MarginCalculatorService

### 📝 Arquivos Criados
- `bin/test-ai-pricing-integration.php` - Testes de integração AI + Pricing

---

## [1.8.0] - 2026-01-29

### ✅ Implementado

#### Módulo de Precificação Inteligente - Completo
- ✅ **MarginCalculatorService** - Cálculo preciso de margens com taxas ML
- ✅ **PromotionSimulatorService** - Simulação de promoções e Central de Ofertas
- ✅ **PricingScenarioService** - Comparação de estratégias e regras automáticas
- ✅ **RankingAlertService** - Alertas de margem e impacto no ranking
- ✅ **PricingIntelligenceController** - 30+ endpoints de API
- ✅ Dashboard completo com 4 tabs (Simulador, Promoções, Histórico, Regras)
- ✅ Worker automático para execução de regras (bin/pricing-worker.php)
- ✅ Gráficos de histórico de preços com Chart.js
- ✅ Sistema de regras de precificação automática

### 📝 Arquivos Criados

#### Services
- `app/Services/PromotionSimulatorService.php` - Simulação de promoções
- `app/Services/PricingScenarioService.php` - Estratégias e regras

#### Workers
- `bin/pricing-worker.php` - Worker automático de pricing
- `bin/apply-pricing-migration.php` - Script de migração
- `bin/test-pricing-integration.php` - Testes de integração

#### Database
- `database/migrations/2026_01_29_create_pricing_intelligence_tables.sql` - 5 tabelas

#### Documentation
- `docs/PRICING_INTELLIGENCE_IMPLEMENTATION.md` - Documentação completa
- `crontab.pricing.example` - Exemplo de configuração cron

### 📊 Endpoints de API Adicionados (20+)

```
POST /api/pricing-intelligence/{accountId}/margin/calculate
POST /api/pricing-intelligence/{accountId}/margin/minimum
GET  /api/pricing-intelligence/{accountId}/costs/{itemId}
POST /api/pricing-intelligence/{accountId}/costs/{itemId}
POST /api/pricing-intelligence/{accountId}/promotion/simulate/{itemId}
GET  /api/pricing-intelligence/{accountId}/promotion/scenarios/{itemId}
POST /api/pricing-intelligence/{accountId}/promotion/central-ofertas/{itemId}
POST /api/pricing-intelligence/{accountId}/promotion/apply/{itemId}
GET  /api/pricing-intelligence/{accountId}/history/{itemId}
GET  /api/pricing-intelligence/{accountId}/rules
POST /api/pricing-intelligence/{accountId}/rules
POST /api/pricing-intelligence/{accountId}/rules/{ruleId}/execute
POST /api/pricing-intelligence/{accountId}/rules/{ruleId}/toggle
DELETE /api/pricing-intelligence/{accountId}/rules/{ruleId}
GET  /api/pricing-intelligence/{accountId}/alerts
POST /api/pricing-intelligence/{accountId}/alerts/mark-read
GET  /api/pricing-intelligence/{accountId}/strategies/{itemId}
```

---

## [1.7.0] - 2026-01-28

### ✅ Implementado

#### Market Data API - Dados Reais do Mercado Livre
- ✅ **RealMarketDataService** - Serviço completo para análise de mercado
- ✅ **MarketDataController** - Controller com 18 endpoints de API
- ✅ Análise de preços com estatísticas (min, max, média, mediana, percentis)
- ✅ Análise de qualidade de anúncios (score 0-100)
- ✅ Descoberta de categorias relacionadas (domain_discovery)
- ✅ Requisitos de atributos por categoria
- ✅ Estatísticas de inventário local
- ✅ **Fallback inteligente**: quando API bloqueada (403), usa dados locais

#### Interface - Modal de Análise de Mercado
- ✅ Modal de análise de mercado integrado ao dashboard de ficha técnica
- ✅ KPIs de preços (mínimo, mediana, média, máximo)
- ✅ Features do mercado (% frete grátis, Full, lojas oficiais, catálogo)
- ✅ Recomendações de preço competitivo e premium
- ✅ Categorias relacionadas clicáveis
- ✅ Botão "Mercado" na barra de filtros

#### Interface - Análise de Qualidade
- ✅ Modal de qualidade do anúncio com score visual
- ✅ Breakdown por categoria (título, descrição, imagens, atributos, frete, preço)
- ✅ Lista de problemas encontrados com severidade
- ✅ Recomendações de melhoria
- ✅ Botão no menu de ações de cada item

### 📝 Arquivos Criados/Modificados

#### Services
- `app/Services/RealMarketDataService.php` - Serviço de dados reais de mercado (NEW)

#### Controllers
- `app/Controllers/MarketDataController.php` - Controller de APIs de mercado (NEW)

#### Routes
- `app/Routes/api.php` - 18 novos endpoints de market data

#### Views
- `app/Views/dashboard/tech-sheet/index.php` - Modal de análise + JS functions

#### Documentation
- `MARKET_API_README.md` - Documentação completa das APIs de mercado (NEW)

### 📊 Endpoints de API Adicionados

```
GET  /api/market/analyze/{categoryId}      - Análise completa
GET  /api/market/category/{categoryId}     - Detalhes da categoria
GET  /api/market/pricing/{categoryId}      - Análise de preços
GET  /api/market/competitors/{categoryId}  - Análise de concorrentes
GET  /api/market/trends/{categoryId}       - Tendências
GET  /api/market/filters/{categoryId}      - Filtros disponíveis
GET  /api/market/quality/{itemId}          - Qualidade do anúncio
GET  /api/market/item/{itemId}             - Detalhes do item
GET  /api/market/discover?q=keyword        - Descobrir categorias
GET  /api/market/autocomplete?q=text       - Autocomplete
GET  /api/market/search                    - Buscar produtos
GET  /api/market/attributes/{categoryId}   - Atributos
GET  /api/market/children/{categoryId}     - Subcategorias
GET  /api/market/requirements/{categoryId} - Requisitos
GET  /api/market/stats                     - Estatísticas
POST /api/market/similar                   - Produtos similares
POST /api/market/suggest-price             - Sugestão de preço
```

---

## [1.6.0] - 2024-12-15

### ✅ Implementado

#### Fase 10 - Documentação Completa (COMPLETA)
- ✅ Documentação completa da API interna
- ✅ Manual do usuário completo
- ✅ Guia de deploy detalhado
- ✅ Guia de segurança
- ✅ Script de instalação automatizado
- ✅ Script de backup automatizado
- ✅ README atualizado com todas as funcionalidades

### 📝 Arquivos Criados

#### Documentação
- `docs/API_DOCUMENTATION.md` - Documentação completa da API
- `docs/USER_MANUAL.md` - Manual do usuário
- `docs/DEPLOY_GUIDE.md` - Guia de deploy
- `SECURITY.md` - Guia de segurança

#### Scripts
- `scripts/install.sh` - Instalação automatizada
- `scripts/backup.sh` - Backup automatizado

### 📚 Documentação

Toda a documentação está completa e pronta para uso:
- API endpoints documentados
- Exemplos de uso
- Troubleshooting
- Boas práticas
- Guias passo a passo

---

## [1.5.0] - 2024-12-15

### ✅ Implementado

#### Fase 9 - Cache Avançado (COMPLETA)
- ✅ CacheService com suporte a Redis e File
- ✅ Fallback automático para File se Redis não disponível
- ✅ Método `remember()` para cache pattern
- ✅ Limpeza automática de cache expirado
- ✅ Integração nos serviços (CategoryService)
- ✅ Cache hierárquico em arquivos

#### Fase 9 - Segurança (COMPLETA)
- ✅ SecurityService com criptografia AES-256-CBC
- ✅ Criptografia de tokens no banco de dados
- ✅ Descriptografia automática ao usar tokens
- ✅ Rate limiting por IP (100 req/min)
- ✅ RateLimitMiddleware implementado
- ✅ CSRF protection completo
- ✅ CsrfMiddleware para requisições POST/PUT/DELETE
- ✅ Proteção XSS com sanitização
- ✅ SecurityHelper para views
- ✅ Logs de auditoria completos
- ✅ AuditLogService para rastreamento

### 📝 Arquivos Criados

#### Services
- `app/Services/CacheService.php` - Sistema de cache avançado
- `app/Services/SecurityService.php` - Criptografia e segurança
- `app/Services/AuditLogService.php` - Logs de auditoria

#### Middleware
- `app/Middleware/CsrfMiddleware.php` - Proteção CSRF
- `app/Middleware/RateLimitMiddleware.php` - Rate limiting

#### Helpers
- `app/Helpers/SecurityHelper.php` - Helpers de segurança para views

#### Controllers
- `app/Controllers/AuditController.php` - Endpoints de auditoria

#### Database
- `database/migrations/007_create_security_tables.sql` - Tabelas de segurança

### 🔄 Melhorias

#### MercadoLivreAuthService
- Tokens agora são criptografados antes de salvar
- Descriptografia automática ao recuperar tokens
- Segurança reforçada

#### CategoryService
- Migrado para usar CacheService
- Suporte a Redis e File
- Performance melhorada

#### Public/index.php
- Rate limiting aplicado automaticamente
- Proteção CSRF em requisições modificadoras

### 📊 Endpoints Adicionados

#### Auditoria
- `GET /api/audit` - Lista logs de auditoria

### 🔐 Segurança

#### Criptografia
- Tokens criptografados com AES-256-CBC
- Chave de criptografia via APP_KEY no .env
- IV único para cada criptografia

#### Rate Limiting
- 100 requisições por minuto por IP
- Limpeza automática de registros antigos
- Resposta 429 quando limite excedido

#### CSRF Protection
- Tokens gerados por sessão
- Validação automática em POST/PUT/DELETE
- Expiração de 1 hora

#### XSS Protection
- Sanitização automática de inputs
- Helper `SecurityHelper::e()` para views
- Proteção em todos os dados de usuário

---

## [1.4.0] - 2024-12-15

### ✅ Implementado

#### Fase 7 - Notificações Visuais (COMPLETA)
- ✅ Componente de notificações com bell icon
- ✅ Badge com contador de não lidas
- ✅ Dropdown com lista de notificações
- ✅ Marcar como lido individualmente
- ✅ Marcar todas como lidas
- ✅ Atualização automática a cada 30 segundos
- ✅ Integrado no navbar do dashboard

#### Fase 7 - E-mails (COMPLETA)
- ✅ EmailService para envio de e-mails
- ✅ Template HTML para e-mails
- ✅ Notificação de novo pedido por e-mail
- ✅ Notificação de token expirando por e-mail
- ✅ Configuração via .env
- ✅ Integrado com webhooks e alertas

#### Fase 8 - Histórico de Preços (COMPLETA)
- ✅ PriceHistoryService para registrar histórico
- ✅ Armazenamento de histórico no banco
- ✅ Análise de tendência de preços
- ✅ API para consultar histórico
- ✅ Detecção de tendência (aumentando/diminuindo/estável)

#### Fase 8 - Detector de Oportunidades Avançado (COMPLETA)
- ✅ Detecção de produtos sem catálogo
- ✅ Detecção de categorias com pouca concorrência
- ✅ Detecção de produtos mais vendidos sem anúncio do usuário
- ✅ OpportunityDetectorService completo
- ✅ APIs para todas as detecções

### 📝 Arquivos Criados

#### Views/Components
- `app/Views/components/notifications_bell.php` - Componente de notificações

#### Services
- `app/Services/EmailService.php` - Envio de e-mails
- `app/Services/PriceHistoryService.php` - Histórico de preços
- `app/Services/OpportunityDetectorService.php` - Detector avançado

#### Controllers
- `app/Controllers/OpportunityController.php` - Endpoints de oportunidades
- `app/Controllers/PriceHistoryController.php` - Endpoints de histórico

#### Database
- `database/migrations/006_create_price_history_table.sql` - Tabela de histórico

### 📊 Endpoints Adicionados

#### Oportunidades
- `GET /api/opportunities/products-without-catalog` - Produtos sem catálogo
- `GET /api/opportunities/low-competition` - Categorias com pouca concorrência
- `GET /api/opportunities/best-sellers` - Produtos mais vendidos sem seu anúncio

#### Histórico de Preços
- `POST /api/price-history/record` - Registrar histórico
- `GET /api/price-history` - Obter histórico
- `GET /api/price-history/trend` - Analisar tendência

### 🔄 Melhorias

#### WebhookController
- Integração com EmailService
- Envio automático de e-mail ao receber novo pedido

#### AlertService
- Integração com EmailService
- Envio automático de e-mail quando token expira

---

## [1.3.0] - 2024-12-15

### ✅ Implementado

#### Fase 6 - Webhooks (COMPLETA)
- ✅ WebhookController para receber notificações do ML
- ✅ Processamento de notificações de pedidos
- ✅ Processamento de notificações de itens
- ✅ Processamento de notificações de perguntas
- ✅ Logs de webhooks no banco de dados
- ✅ Sincronização automática ao receber webhook

#### Fase 7 - Sistema de Alertas (PARCIALMENTE COMPLETA)
- ✅ AlertService para gerenciar alertas
- ✅ Alerta de token expirando
- ✅ Alerta de novo concorrente
- ✅ Alerta de variação de preço
- ✅ Alerta de novo pedido
- ✅ Sistema de severidade (info, warning, danger, success)
- ✅ Tabela de alertas no banco
- ✅ API para listar e gerenciar alertas

#### Fase 7 - Notificações (PARCIALMENTE COMPLETA)
- ✅ Sistema de notificações no banco
- ✅ Notificações automáticas de novos pedidos
- ✅ API para listar notificações
- ✅ Marcar notificações como lidas
- ✅ Contador de notificações não lidas

#### Fase 8 - Análise de Concorrência (PARCIALMENTE COMPLETA)
- ✅ CompetitorAnalysisService
- ✅ Identificação de vendedores por marca
- ✅ Comparação de preços entre vendedores
- ✅ Ranking de vendedores por vendas
- ✅ Estatísticas por vendedor (média, min, max)
- ✅ API para análise de concorrência

#### Fase 8 - Detector de Oportunidades (PARCIALMENTE COMPLETA)
- ✅ Detecção de baixa concorrência
- ✅ Detecção de mercado com preço alto
- ✅ Detecção de vendedor dominante
- ✅ API para detectar oportunidades

### 📝 Arquivos Criados

#### Controllers
- `app/Controllers/WebhookController.php` - Recebe webhooks do ML
- `app/Controllers/AlertController.php` - Gerencia alertas
- `app/Controllers/CompetitorController.php` - Análise de concorrência

#### Services
- `app/Services/AlertService.php` - Sistema de alertas
- `app/Services/CompetitorAnalysisService.php` - Análise de concorrência

#### Database
- `database/migrations/005_create_notifications_and_alerts_tables.sql` - Tabelas de notificações e alertas

### 📊 Endpoints Adicionados

#### Webhooks
- `POST /webhook/ml` - Recebe notificações do Mercado Livre
- `GET /webhook/ml` - Recebe notificações (GET também suportado)

#### Alertas
- `GET /api/alerts` - Lista alertas
- `GET /api/alerts/count` - Conta alertas não lidos
- `POST /api/alerts/{id}/read` - Marca alerta como lido
- `POST /api/alerts/read-all` - Marca todos como lidos

#### Concorrência
- `GET /api/competitors/analyze` - Analisa concorrência
- `GET /api/competitors/opportunities` - Detecta oportunidades

---

## [1.2.0] - 2024-12-15

### ✅ Implementado

#### Fase 5 - Dashboard Avançado (COMPLETA)
- ✅ Cards com métricas principais (contas, pedidos, receita, tokens)
- ✅ Gráfico de rosca com distribuição de pedidos por status
- ✅ Lista de pedidos recentes no dashboard
- ✅ Métricas consolidadas via API
- ✅ DashboardService para cálculos de métricas

#### Fase 6 - Gestão de Pedidos (PARCIALMENTE COMPLETA)
- ✅ OrderService para gerenciar pedidos
- ✅ Sincronização manual de pedidos
- ✅ Armazenamento de pedidos no banco de dados
- ✅ Visualização unificada de pedidos de múltiplas contas
- ✅ Filtros por status e data
- ✅ Interface completa de gestão de pedidos
- ✅ Detalhes do pedido em modal
- ✅ Tabela ml_orders criada

### 📝 Arquivos Criados

#### Services
- `app/Services/OrderService.php` - Gerenciamento de pedidos
- `app/Services/DashboardService.php` - Métricas do dashboard

#### Controllers
- `app/Controllers/OrderController.php` - Endpoints de pedidos
- `app/Controllers/DashboardController.php` - Melhorado com métricas

#### Views
- `app/Views/dashboard/orders.php` - Interface de gestão de pedidos
- `app/Views/dashboard/index.php` - Dashboard melhorado com gráficos

#### Database
- `database/migrations/004_create_ml_orders_table.sql` - Tabela de pedidos

### 🔄 Melhorias

#### Dashboard Principal
- Cards com métricas em tempo real
- Gráfico interativo de pedidos por status
- Lista de pedidos recentes
- Indicadores visuais de status

#### Gestão de Pedidos
- Sincronização de múltiplas contas
- Filtros avançados (status, data)
- Visualização unificada
- Detalhes completos do pedido

### 📊 Endpoints Adicionados

- `GET /api/dashboard/metrics` - Métricas do dashboard
- `GET /api/orders` - Lista pedidos de uma conta
- `GET /api/orders/all` - Lista pedidos de todas as contas
- `GET /api/orders/{id}` - Detalhes de um pedido
- `POST /api/orders/sync` - Sincronizar pedidos

---

## [1.1.0] - 2024-12-15

### ✅ Implementado

#### Fase 2 - Melhorias no Cliente HTTP
- ✅ Rate limiting básico implementado
- ✅ Retry automático com backoff exponencial
- ✅ Controle de requisições por hora

#### Fase 3 - Navegador Visual de Categorias
- ✅ Interface visual hierárquica
- ✅ Busca em tempo real
- ✅ Detalhes completos da categoria
- ✅ Navegação expansível/colapsável

#### Fase 4 - Exportação e Filtros Avançados
- ✅ Exportação CSV (compatível Excel)
- ✅ Exportação JSON
- ✅ Filtros avançados (condição, preço, frete, tipo)
- ✅ Botão de exportação na interface

---

## [1.0.0] - 2024-12-15

### ✅ Implementado

#### Fase 1 - Fundação e Autenticação OAuth2
- ✅ Estrutura completa do projeto MVC
- ✅ Configuração do Composer com dependências
- ✅ Sistema de configuração via `.env`
- ✅ Schema completo do banco de dados
- ✅ Classe Database para conexão PDO
- ✅ Serviço de autenticação OAuth2
- ✅ Fluxo completo de autorização e callback
- ✅ Sistema de refresh automático de tokens
- ✅ Dashboard básico com listagem de contas vinculadas

#### Fase 2 - Core da API
- ✅ Cliente HTTP para API ML
- ✅ Suporte a métodos GET, POST, PUT, DELETE
- ✅ Tratamento de erros da API
- ✅ Integração automática com tokens
- ✅ Sistema de rotas
- ✅ Controllers: AuthController, DashboardController

#### Fase 3 - Categorias e Marcas
- ✅ CategoryService para gerenciar categorias
- ✅ Listagem de todas as categorias do site MLB
- ✅ Detalhes de categoria específica
- ✅ Atributos de categoria
- ✅ Busca de categoria por nome
- ✅ Obtenção de marcas por categoria
- ✅ Sistema de cache simples em arquivo
- ✅ CategoryController com endpoints REST

#### Fase 4 - Análise de Anúncios
- ✅ SearchService para buscas avançadas
- ✅ Busca por categoria e marca
- ✅ Análise diferenciando catálogo vs comum
- ✅ Estatísticas de preços (min, max, média)
- ✅ Análise de condições (novo/usado)
- ✅ Análise de frete (grátis/pago)
- ✅ SearchController com endpoints
- ✅ Interface de análise

---

## Formato

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Semantic Versioning](https://semver.org/lang/pt-BR/).
