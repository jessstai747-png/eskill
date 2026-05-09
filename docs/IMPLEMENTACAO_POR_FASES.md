# Implementação por Fases — eskill.com.br

> Plano baseado na auditoria de 2026-02-18.
> Prioriza o que AWA Motos realmente precisa para operar e vender.
> **v2** — Inclui: bin/, migrations, rotas, assets JS/CSS, testes existentes, grafo de dependências, crons/workers.

---

## Grafo de Dependências entre Fases

```
Fase 0 (Limpeza)
  │
  ▼
Fase 1 (Core: Auth + Dashboard + Items) ←── obrigatória para todas
  │
  ├──────────────┬──────────────┬──────────────┐
  ▼              ▼              ▼              ▼
Fase 2         Fase 3        Fase 5        Fase 7
(SEO)         (Clone)       (Pedidos)     (Notificações)
  │              │              │
  ▼              ▼              ▼
Fase 4         Fase 3.3      Fase 6
(Pricing)     (Clone sub)   (Relatórios)
  │
  ▼
Fase 8 (Refatoração) ←── contínua, pode rodar em paralelo com Fases 4-7
  │
  ▼
Fase 9 (Opcionais) ←── sob demanda
```

**Regras de dependência:**
- **Fase 0 → 1**: Sempre sequencial. Limpeza obrigatória antes.
- **Fases 2, 3, 5, 7**: Independentes entre si. Podem rodar em paralelo após Fase 1.
- **Fase 4**: Depende de Fase 2 (SEO usa dados de competidores compartilhados com pricing).
- **Fase 6**: Depende de Fase 5 (relatórios financeiros precisam de dados de pedidos reais).
- **Fase 8**: Contínua — começar refatorações incrementais junto com Fases 4-7. Não é bloqueante.
- **Fase 9**: Sob demanda. Nunca bloqueia nada.

---

## Inventário de Infraestrutura

### Rotas (1592 endpoints)
| Arquivo | Rotas | Linhas | Conteúdo |
|---------|-------|--------|----------|
| `app/Routes/auth.php` | 29 | 44 | Login, register, OAuth ML, 2FA |
| `app/Routes/web.php` | 323 | 495 | Dashboard, views, páginas |
| `app/Routes/api.php` | 1224 | 1808 | Todas as APIs REST |
| `app/Routes/webhooks.php` | 3 | 10 | Webhooks ML |
| `app/Routes/fase8_routes.php` | 13 | 79 | Rotas adicionais da Fase 8 |

### Migrations (99 arquivos)
Distribuídas em `database/migrations/`. Instalação base: `000_install_all.sql`.

### Assets Frontend (27042 linhas total)
| Asset | Linhas | Fase |
|-------|--------|------|
| `public/js/dashboard-modern.js` | 5495 | 1 |
| `public/assets/js/seo-killer.js` | 2407 | 2 |
| `public/css/dashboard-modern.css` | 2135 | 1 |
| `public/assets/css/seo-killer.css` | 1040 | 2 |
| `public/js/catalog-clone.js` | 1002 | 3 |
| `public/assets/js/seo-killer-chatbot.js` | 908 | 9 |
| `public/js/seo-dashboard.js` | 842 | 2 |
| `public/assets/js/seo-killer-2.js` | 779 | 2 |
| `public/js/realtime-dashboard.js` | 763 | 1 |
| `public/js/realtime-notifications.js` | 762 | 7 |
| `public/js/app.js` | 671 | 1 |
| `public/assets/js/ml-advanced-dashboard.js` | 615 | 1 |
| `public/css/pwa.css` | 604 | 7 |
| `public/assets/js/seo-killer-ai-insights.js` | 591 | 2 |
| `public/css/style.css` | 586 | 1 |
| `public/css/components.css` | 583 | 1 |
| `public/js/pwa.js` | 572 | 7 |
| `public/assets/js/seo-killer-utils.js` | 557 | 2 |
| `public/js/clone-progress-widget.js` | 535 | 3 |
| `public/assets/css/ai-optimization.css` | 464 | 2 |
| `public/js/ads-wizard.js` | 422 | 9 |
| `public/css/theme.css` | 420 | 1 |
| `public/js/ads-manager.js` | 413 | 9 |
| `public/js/quality-dashboard.js` | 355 | 2 |
| `public/assets/css/seo-killer-chatbot.css` | 355 | 9 |
| `public/js/tours.js` | 340 | 1 |
| `public/js/ean-widget.js` | 328 | 2 |
| `public/js/ai-center.js` | 315 | 9 |
| `public/css/theme-fixes.css` | 290 | 1 |
| `public/js/api-client.js` | 252 | 1 |
| `public/js/onboarding.js` | 229 | 1 |
| `public/css/ai-center.css` | 204 | 9 |
| `public/js/csrf-helper.js` | 157 | 1 |
| `public/js/theme-switcher.js` | 151 | 1 |
| `public/js/command-palette.js` | 139 | 1 |
| `public/service-worker.js` | 92 | 7 |
| `public/css/shepherd-theme.css` | 10 | 1 |
| `public/assets/js/ai-optimization.js` | 2 | 9 |
| `public/assets/js/notification-client.js` | 1 | 7 |
| `public/js/sortable.min.js` | 2 | 1 |
| `public/assets/js/ean-widget.js` | 1 | 2 |
| `public/sounds/sound-generator.js` | 1 | 7 |

### Testes Existentes (128 test files)
| Domínio | Testes existem | Testes que faltam |
|---------|----------------|-------------------|
| **Auth** (Fase 1) | 5: AuthTest, AuthControllerTest, AuthServiceTest, MercadoLivreAuthServiceTest, MobileAuthControllerTest | E2E login real, refresh token cycle |
| **Dashboard** (Fase 1) | 1: DashboardControllerTest | ItemListTest com dados ML reais |
| **SEO** (Fase 2) | **39 testes** — área mais coberta | Teste E2E de otimização real (aplicar + medir) |
| **Clone** (Fase 3) | 7: CatalogCloneServiceTest, CloneMetricsServiceTest, CloneMonitoringServiceTest, + 4 outros | Teste de clone real entre 2 contas ML |
| **Pricing** (Fase 4) | 5: PricingStrategyServiceTest, AdvancedPricingEngineTest, CompetitorIntelligenceServiceTest, CompetitorAnalysisServiceTest, PricingIntelligenceTest | Teste de regra automática em produção |
| **Pedidos** (Fase 5) | 1: OrderServiceTest | WebhookTest, ClaimsTest, ShippingTest, QuestionTest |
| **Financeiro** (Fase 6) | 2: FinancialServiceTest, TechSheetExportServiceTest | PdfExportTest, CsvExportTest, ReportGenerationTest |
| **Notificações** (Fase 7) | 3: EmailServiceTest, TechSheetNotificationServiceTest, CloneAlertNotificationServiceTest | PushNotificationTest, WhatsAppTest, TelegramTest |
| **Infra/Core** | 24: ContainerTest, RouterTest, RequestTest, SecurityTests, CacheServiceTest, etc. | — |

---

## Inventário de Views

O projeto tem **159 arquivos PHP** em `app/Views/` (4.4MB total).

### Estrutura de Views
```
app/Views/
├── auth/                          # 6 arquivos (login, register, 2FA, reset)
├── brand_analysis/                # 1 arquivo (index.php - 1782L)
├── catalog/                       # 1 arquivo (clone.php - 1011L)
├── components/                    # 5 arquivos (navbar, notifications, ui)
├── dashboard/                     # ~120 arquivos — a maioria das telas
│   ├── ads/                       # 2 (dashboard, wizard)
│   ├── ai_optimization/           # 2 (editor, index)
│   ├── analytics/                 # 1 (index)
│   ├── audit/                     # 1 (index)
│   ├── cache/                     # 1 (index)
│   ├── catalog/                   # 1 (competition)
│   ├── claims/                    # 1 (index - 157L)
│   ├── competitors/               # 2 (index, details)
│   ├── customers/                 # 1 (index)
│   ├── financials/                # 1 (conciliation)
│   ├── health/                    # 1 (index)
│   ├── items/                     # 1 (bulk)
│   ├── logistics/                 # 2 (flex, full_restock)
│   ├── logs/                      # 1 (index)
│   ├── marketing/                 # 1 (promotions)
│   ├── notifications/             # 1 (settings)
│   ├── openspec/                  # 3 (index, create_proposal, change_detail)
│   ├── reports/                   # 1 (index - 193L)
│   ├── returns/                   # 1 (index)
│   ├── seo-intelligence/          # 1 (listing-detail)
│   ├── seo-killer/                # 1 + 27 components
│   │   └── components/            # 27 modais/tabs especializados
│   ├── shopee/                    # 1 (index - 97L)
│   └── tech-sheet/                # 1 (index - 6761L)
├── deep_research/                 # 1 (index - 3440L)
├── errors/                        # 2 (404, 500)
├── layouts/                       # 8 (app, main, sidebar, navbar, modern/*)
├── monitoring/                    # 1 (dashboard - 614L)
├── pricing/                       # 2 (dashboard - 8293L, history)
├── public/                        # 1 (product)
├── security/                      # 1 (dashboard - 418L)
├── seo/                           # 1 (dashboard - 1363L)
├── seo-killer/                    # 1 (index)
└── settings/                      # 2 (proxies, users)
```

### Views maiores (potenciais god views)
| View | Linhas | Observação |
|------|--------|------------|
| `pricing/dashboard.php` | 8293 | Maior view do projeto |
| `dashboard/tech-sheet/index.php` | 6761 | Ficha técnica completa |
| `dashboard/account-health.php` | 5042 | Health dashboard enorme |
| `deep_research/index.php` | 3440 | Research UI completa |
| `dashboard/analysis.php` | 3449 | Analytics geral |
| `brand_analysis/index.php` | 1782 | Brand analysis |
| `dashboard/seo-killer/components/` | 15748 | 27 componentes SEO |
| `dashboard/index.php` | 1650 | Dashboard principal |
| `dashboard/items.php` | 1687 | Listagem de itens |

---

## Contexto

O projeto tem **73 features declaradas**, mas a auditoria revelou:

- **~20 features core** que geram valor real para o negócio
- **~15 features estruturais** que funcionam mas precisam de limpeza
- **~20 features infladas** que existem no código mas nunca serão usadas como estão
- **~18 services duplicados** em namespaces diferentes
- **22 arquivos lixo** na raiz (criados por bug de shell)
- **6 documentos redundantes** sobre 4 mudanças de hardening
- `project-status.json` com 73/73 "passing" sem teste funcional real

**Regra:** Cada fase só avança quando a anterior tiver testes reais rodando e features validadas em produção.

---

## Fase 0 — Limpeza e Higiene (1-2 dias)

**Objetivo:** Remover lixo, corrigir o harness, ter uma base limpa.
**Dependência:** Nenhuma.

### 0.1 Deletar arquivos lixo da raiz
```
[__construct],  [applyOptimizations],  [batchPipeline],
[cleanupSnapshots],  [compareVersions],  [fullPipeline],
[getEnrichedItemData],  [getHealthStatus],  [getItemStatus],
[getItemsForOptimization],  [getOptimizationHistory],
[getOptimizationStats],  [optimizeWithContext],
[rollbackOptimization],  [trackImpact],  [updateDescription],
template_fallback,  true,  n;  logger  AI  Apply  Enrich
echo ( === ) ? FAIL:
```

### 0.2 Mover/deletar documentação auto-gerada
| Arquivo | Ação |
|---------|------|
| `IMPLEMENTATION_100_COMPLETE.md` | Deletar |
| `EXECUTIVE_SUMMARY.md` | Deletar |
| `COMPLETION_CHECKLIST.md` | Deletar |
| `FINAL_REPORT.txt` | Deletar |
| `HARDENING_STATUS.md` | Deletar |
| `STATUS_REPORT.md` | Deletar |
| `AUDIT_MOCK_IMPLEMENTATIONS.md` | Mover para `docs/historico/` |
| `SECURITY_CHANGELOG.md` | Merge em `CHANGELOG.md` e deletar |

### 0.3 Corrigir project-status.json
- Resetar TODAS as features para `"passes": false`
- Manter a estrutura, só mudar o status
- Features só voltam para `true` quando tiverem teste automatizado real

### 0.4 Verificar ambiente
- [ ] MySQL conectando
- [ ] Redis conectando
- [ ] `composer install` limpo
- [ ] `php vendor/bin/phpunit` roda (mesmo que falhe)
- [ ] `.env` com credenciais reais do ML

### 0.5 Scripts CLI desta fase
| Script | Ação |
|--------|------|
| `bin/init.sh` | Ajustar smoke tests para verificar o que realmente importa |
| `bin/check-env.php` | Validar .env |
| `bin/apply-migrations.php` | Rodar todas as migrations |
| `bin/workspace-maintenance.sh` | Limpar temporários |

**Critério de saída:** Zero arquivos lixo, phpunit executa, MySQL OK.

---

## Fase 1 — Core de Operação (2-3 semanas)

**Objetivo:** Login funciona, conecta no ML, mostra dashboard com dados reais.
**Dependência:** Fase 0.

### 1.1 Autenticação
| Feature | Arquivo | Status Atual | Ação |
|---------|---------|-------------|------|
| Login email/senha | `AuthService.php` | Real | Validar com teste E2E |
| OAuth 2.0 ML | `MercadoLivreAuthService.php` | Real | Validar refresh token cycle |
| Auto refresh worker | `bin/auto-token-refresh-worker.php` | Real | Testar em background |

**Migrations necessárias:**
| Migration | Conteúdo |
|-----------|----------|
| `001_create_users_table.sql` | Tabela users |
| `002_create_ml_accounts_table.sql` | Contas ML |
| `010_add_user_status_and_last_login.sql` | Status do user |
| `011_create_password_resets_table.sql` | Reset de senha |
| `018_create_remember_tokens_table.sql` | Remember me |
| `019_add_verification_token_to_users.sql` | Verificação email |
| `20260110_create_refresh_tokens_table.sql` | Tokens de refresh |
| `20260122_add_active_account_to_users_table.php` | Conta ativa |
| `2026_01_11_create_ml_accounts.sql` | ML accounts v2 |
| `2026_02_08_create_ml_accounts_table.php` | ML accounts v3 |
| `2026_02_09_000001_create_token_refresh_audit_table.sql` | Audit de refresh |
| `2026_02_09_000002_add_token_tracking_fields_to_ml_accounts.sql` | Tracking |

**Rotas (auth.php — 29 rotas):**
Todas as rotas de `app/Routes/auth.php` + rotas de dashboard/tokens em `web.php`.

**Views:**
| View | Linhas | Descrição |
|------|--------|-----------|
| `app/Views/auth/login.php` | 59 | Tela de login |
| `app/Views/auth/register.php` | 115 | Cadastro |
| `app/Views/auth/forgot_password.php` | 42 | Esqueci minha senha |
| `app/Views/auth/reset_password.php` | 86 | Reset de senha |
| `app/Views/auth/2fa_setup.php` | 74 | Configuração 2FA |
| `app/Views/auth/2fa_verify.php` | 48 | Verificação 2FA |
| `app/Views/dashboard/accounts.php` | 803 | Gestão de contas ML |
| `app/Views/dashboard/tokens.php` | 823 | Gestão de tokens OAuth |

**Assets JS/CSS:**
| Asset | Linhas | Uso |
|-------|--------|-----|
| `public/js/app.js` | 671 | JS base da aplicação |
| `public/js/dashboard-modern.js` | 5495 | JS principal do dashboard |
| `public/css/dashboard-modern.css` | 2135 | CSS principal |
| `public/css/style.css` | 586 | CSS base |
| `public/css/components.css` | 583 | Componentes UI |
| `public/css/theme.css` | 420 | Tema |
| `public/css/theme-fixes.css` | 290 | Correções de tema |
| `public/js/csrf-helper.js` | 157 | CSRF protection |
| `public/js/api-client.js` | 252 | Cliente HTTP base |
| `public/js/theme-switcher.js` | 151 | Dark/light mode |
| `public/js/command-palette.js` | 139 | Command palette |
| `public/js/onboarding.js` | 229 | Guia inicial |
| `public/js/tours.js` | 340 | Tours guiados |

**Testes existentes (5):**
- `tests/Unit/AuthTest.php`
- `tests/Unit/Controllers/AuthControllerTest.php`
- `tests/Unit/Services/AuthServiceTest.php`
- `tests/Unit/Services/MercadoLivreAuthServiceTest.php`
- `tests/Unit/Controllers/MobileAuthControllerTest.php`

**Testes que faltam criar:**
- `tests/Feature/AuthLoginE2ETest.php` — login/logout real com DB
- `tests/Feature/MLOAuthFlowTest.php` — troca de code por token (mock HTTP, real DB)
- `tests/Feature/TokenRefreshCycleTest.php` — testar refresh automático

**Workers/Crons:**
| Script | Tipo | Cron sugerido |
|--------|------|---------------|
| `bin/auto-token-refresh-worker.php` | Worker contínuo | `*/5 * * * *` |
| `bin/token-health-monitor.php` | Monitor | `*/15 * * * *` |
| `bin/auth-status.php` | Diagnóstico | Manual |
| `bin/monitor-auth-failures.php` | Monitor | `0 * * * *` |
| `bin/test-auto-token-refresh.php` | Teste | Manual |
| `bin/cleanup-refresh-tokens.php` | Limpeza | `0 4 * * *` |

### 1.2 Dashboard
| Feature | Arquivo | Status Atual | Ação |
|---------|---------|-------------|------|
| Dashboard principal | `DashboardController.php` + `DashboardService.php` | Real | Validar com conta ML conectada |

**Views:**
| View | Linhas | Descrição |
|------|--------|-----------|
| `app/Views/dashboard/index.php` | 1650 | Dashboard principal com métricas |
| `app/Views/dashboard/statistics.php` | 235 | Estatísticas gerais |
| `app/Views/dashboard/metrics.php` | 178 | Métricas detalhadas |
| `app/Views/dashboard/activities.php` | 274 | Log de atividades |
| `app/Views/dashboard/profile.php` | 252 | Perfil do usuário |
| `app/Views/dashboard/profile-content.php` | 324 | Conteúdo do perfil |
| `app/Views/layouts/app.php` | 1099 | Layout base da aplicação |
| `app/Views/layouts/sidebar.php` | 202 | Menu lateral |
| `app/Views/layouts/main.php` | 170 | Layout alternativo |
| `app/Views/layouts/navbar.php` | 5 | Navbar (redirect) |
| `app/Views/layouts/modern/app.php` | 1162 | Layout moderno |
| `app/Views/layouts/modern/sidebar.php` | 812 | Sidebar moderno |
| `app/Views/layouts/modern/auth.php` | 180 | Layout auth moderno |
| `app/Views/layouts/modern/bottom_nav.php` | 46 | Nav mobile |
| `app/Views/layouts/modern/partials/page-header.php` | 39 | Header |
| `app/Views/layouts/modern/partials/page-footer.php` | 12 | Footer |
| `app/Views/components/navbar.php` | 190 | Barra de navegação |
| `app/Views/components/account-selector.php` | 387 | Seletor de conta ML |
| `app/Views/components/notifications_bell.php` | 301 | Sino de notificações |

**Testes existentes (1):**
- `tests/Unit/Controllers/DashboardControllerTest.php`

**Testes que faltam criar:**
- `tests/Feature/DashboardRealDataTest.php` — dashboard com dados ML reais

### 1.3 Listagem de Itens
| Feature | Arquivo | Status Atual | Ação |
|---------|---------|-------------|------|
| CRUD de anúncios | `ItemController.php` + `ItemService.php` | Real | Validar listagem da API ML |

**Migrations:**
| Migration | Conteúdo |
|-----------|----------|
| `008_create_items_table.sql` | Tabela items |
| `20260122_create_ml_items_table.php` | Items ML v2 |
| `2026_01_29_create_sync_status_table.sql` | Status de sync |

**Views:**
| View | Linhas | Descrição |
|------|--------|-----------|
| `app/Views/dashboard/items.php` | 1687 | Listagem e gestão de anúncios |
| `app/Views/dashboard/items/bulk.php` | 214 | Edição em lote |
| `app/Views/dashboard/categories.php` | 419 | Navegação por categorias |
| `app/Views/dashboard/search.php` | 203 | Busca de itens |

**Assets:**
| Asset | Linhas | Uso |
|-------|--------|-----|
| `public/assets/js/ml-advanced-dashboard.js` | 615 | Dashboard ML avançado |
| `public/js/realtime-dashboard.js` | 763 | Dados em tempo real |

**Testes existentes (1):**
- `tests/Unit/Services/ItemServiceTest.php`

**Testes que faltam criar:**
- `tests/Feature/ItemListMLTest.php` — buscar itens reais da conta ML

**Workers/Crons:**
| Script | Tipo | Cron sugerido |
|--------|------|---------------|
| `bin/sync-items.php` | Sync | `*/30 * * * *` |
| `bin/record-metrics.php` | Métricas | `0 */6 * * *` |

**Critério de saída:** Usuário loga, conecta ML, vê dashboard com vendas reais, lista seus anúncios.

---

## Fase 2 — SEO (o valor principal) (3-4 semanas)

**Objetivo:** Otimizar títulos e descrições de anúncios reais.
**Dependência:** Fase 1 (precisa de items carregados).

### 2.1 SEO Killer — Títulos
| Feature | Arquivo | Linhas | Ação |
|---------|---------|--------|------|
| Otimização de títulos | `SEOKillerController.php` | 4252 | **Refatorar** — extrair para services menores |
| Keyword research | `KeywordResearchService.php` | ~600 | Validar |
| Keyword miner | `KeywordMinerService.php` | ~400 | Validar |
| Gap hunter | `GapHunterService.php` | ~300 | Validar |
| Title generator | `app/Services/TitleGenerator/` | ~1200 | Validar |

**PROBLEMA CRÍTICO:** `SEOKillerController.php` tem 4252 linhas e 175 métodos. Precisa ser quebrado:
```
SEOKillerController.php (4252 linhas)
  → SEODashboardController.php  (~300 linhas)
  → SEOAnalysisController.php   (~400 linhas)
  → SEOOptimizeController.php   (~500 linhas)
  → SEOBulkController.php       (~300 linhas)
  → SEOKeywordsController.php   (~300 linhas)
```

**Impacto nas rotas:** ~622 rotas com "seo" em `api.php` + rotas em `web.php` precisarão ser redistribuídas para os novos controllers.

### 2.2 SEO — Descrições com IA
| Feature | Arquivo | Status | Ação |
|---------|---------|--------|------|
| Geração com Claude | `ClaudeProvider.php` (185L) | Real | Validar custo/token |
| Geração com GPT | `OpenAIProvider.php` (171L) | Real | Validar fallback |
| Geração com Gemini | `GeminiProvider.php` (316L) | Real | Validar |
| Provider Manager | `AIProviderManager.php` (575L) | Real | Testar circuit breaker |

**Migrations necessárias:**
| Migration | Conteúdo |
|-----------|----------|
| `001_create_seo_killer_settings.sql` | Settings SEO |
| `020_create_ai_optimization_queue_table.sql` | Queue de IA |
| `021_create_ai_ab_tests_tables.sql` | A/B tests IA |
| `022_create_ai_audit_log_table.sql` | Audit log IA |
| `023_create_ai_performance_tracking_table.sql` | Performance tracking |
| `030_create_seo_intelligence_tables.sql` | Intelligence tables |
| `2026_01_01_000002_create_seo_optimizations_table.sql` | Optimizations |
| `2026_01_16_create_seo_bulk_jobs.sql` | Bulk jobs |
| `2026_01_22_create_seo_monitoring_schedule.sql` | Schedule |
| `2026_01_22_create_seo_synonyms_tables.sql` | Sinônimos |
| `2026_01_23_create_seo_analysis_cache_table.sql` | Cache análise |
| `2026_01_23_create_seo_strategies_tables.sql` | Estratégias |
| `2026_01_24_create_seo_hidden_attributes_table.sql` | Atributos ocultos |
| `2026_01_30_create_bulk_seo_jobs.sql` | Bulk jobs v2 |
| `2026_02_01_fase8_progress_tracking_seo.sql` | Progress tracking |
| `2026_02_10_create_seo_killer_remaining_tables.sql` | Tabelas restantes |
| `2026_02_16_create_seo_performance_metrics_table.sql` | Performance metrics |

### 2.3 SEO — Ficha Técnica
| Feature | Arquivo | Status | Ação |
|---------|---------|--------|------|
| Tech Sheet | `TechSheetService.php` | Real | Validar atributos ML |
| EAN/GTIN | `EanService.php` + `EanController.php` | Real | Validar |

**Migrations:**
| Migration | Conteúdo |
|-----------|----------|
| `2026_01_01_000001_create_tech_sheet_tables.sql` | Tabelas tech sheet |
| `2026_01_01_create_tech_sheet_execution_log.sql` | Execution log |
| `2026_01_01_create_tech_sheet_scheduled_jobs.sql` | Scheduled jobs |
| `2026_01_01_create_tech_sheet_webhooks_alerts.sql` | Webhooks/alerts |
| `20260101_create_tech_sheet_tables.php` | Tables PHP |
| `create_ean_tables.sql` | Tabelas EAN |

**Views:**
| View | Linhas | Descrição |
|------|--------|-----------|
| `app/Views/dashboard/tech-sheet/index.php` | **6761** | Ficha técnica (2ª maior view) |
| `app/Views/dashboard/ean.php` | 765 | Gestão de códigos EAN |
| `app/Views/dashboard/ean-admin.php` | 392 | Admin EAN |

### 2.4 Views de SEO
| View | Linhas | Descrição |
|------|--------|-----------|
| `app/Views/dashboard/seo-killer.php` | 403 | Página principal SEO Killer |
| `app/Views/dashboard/seo.php` | 1330 | Dashboard SEO geral |
| `app/Views/seo/dashboard.php` | 1363 | Dashboard SEO alternativo |
| `app/Views/seo-killer/index.php` | 16 | SEO Killer entry point (redirect) |
| `app/Views/dashboard/seo-intelligence.php` | 573 | SEO Intelligence |
| `app/Views/dashboard/seo-intelligence/listing-detail.php` | 561 | Detalhe de anúncio SEO |
| `app/Views/dashboard/analysis.php` | 3449 | Análise geral |
| `app/Views/dashboard/opportunities.php` | 124 | Oportunidades SEO |
| `app/Views/dashboard/quality.php` | 215 | Qualidade de anúncios |

**Componentes SEO Killer (27 arquivos, 15748 linhas total):**
| Componente | Linhas | Descrição |
|------------|--------|-----------|
| `components/title-generator-modal.php` | 463 | Modal gerador de títulos |
| `components/keyword-research-modal.php` | 556 | Modal pesquisa de keywords |
| `components/description-generator-modal.php` | 924 | Modal gerador de descrições |
| `components/bulk-optimizer-modal.php` | 650 | Modal otimização em lote |
| `components/technical-sheet-tab.php` | 193 | Tab ficha técnica |
| `components/attribute-filler-modal.php` | 1077 | Modal preenchimento de atributos |
| `components/competitor-spy-tab.php` | 1788 | Tab espionagem de concorrentes |
| `components/competitor-spy-modal.php` | 1448 | Modal detalhes concorrente |
| `components/performance-tracker-tab.php` | 1144 | Tab métricas de performance |
| `components/performance-analytics-enhanced.php` | 483 | Analytics avançado |
| `components/ab-test-tab.php` | 987 | Tab A/B testing |
| `components/ai-image-analyzer.php` | 956 | Analisador de imagens IA |
| `components/image-analyzer-modal.php` | 843 | Modal analisador de imagens |
| `components/ai-pricing-optimizer.php` | 918 | Otimizador de preços IA |
| `components/ai-insights-dashboard.php` | 296 | Dashboard insights IA |
| `components/ai-chatbot-widget.php` | 103 | Widget chatbot IA |
| `components/seo-strategies-dashboard.php` | 999 | Dashboard estratégias SEO |
| `components/autopilot-config-modal.php` | 993 | Config autopilot |
| `components/autopilot-stats-dashboard.php` | 281 | Stats autopilot |
| `components/quick-actions-panel.php` | 483 | Painel ações rápidas |
| `components/notifications-center.php` | 692 | Centro de notificações |
| `components/worker-monitor-modal.php` | 507 | Monitor de workers |
| `components/pdf-export-modal.php` | 100 | Export PDF |
| `components/developer-hub-tab.php` | 206 | Hub desenvolvedor |
| `components/gsc-dashboard-tab.php` | 250 | Google Search Console |
| `components/backlink-analysis-modal.php` | 126 | Análise de backlinks |
| `components/schema-markup-modal.php` | 102 | Schema markup |

**NOTA:** Existem views duplicadas — `seo.php`, `seo/dashboard.php` e `seo-killer.php` parecem cobrir funcionalidade similar. Consolidar.

**Assets JS/CSS:**
| Asset | Linhas | Uso |
|-------|--------|-----|
| `public/assets/js/seo-killer.js` | 2407 | JS principal SEO Killer |
| `public/assets/css/seo-killer.css` | 1040 | CSS SEO Killer |
| `public/js/seo-dashboard.js` | 842 | Dashboard SEO |
| `public/assets/js/seo-killer-2.js` | 779 | JS SEO Killer v2 |
| `public/assets/js/seo-killer-ai-insights.js` | 591 | AI Insights |
| `public/assets/js/seo-killer-utils.js` | 557 | Utilitários SEO |
| `public/assets/css/ai-optimization.css` | 464 | CSS otimização IA |
| `public/js/quality-dashboard.js` | 355 | Quality dashboard |
| `public/js/ean-widget.js` | 328 | Widget EAN |

**Testes existentes (39) — área mais coberta:**
- 22 testes unitários em `tests/Unit/Services/SEO/`
- 7 testes de estratégias em `tests/Unit/Services/AI/SEO/Strategies/`
- 4 testes de IA em `tests/Unit/Services/AI/`
- 3 testes de integração em `tests/Integration/SEO/`
- 1 teste de aceitação em `tests/Acceptance/SEO/`
- 2 testes avulsos (`KeywordKillerTest`, `SEOKillerEngineTest`)

**Testes que faltam criar:**
- `tests/Feature/SEORealOptimizationTest.php` — pegar 5 anúncios reais, gerar títulos, comparar
- `tests/Feature/TechSheetMLTest.php` — validar atributos contra API ML

**Workers/Crons:**
| Script | Tipo | Cron sugerido |
|--------|------|---------------|
| `bin/seo-worker.php` | Worker SEO | Contínuo ou `*/2 * * * *` |
| `bin/bulk-seo-worker.php` | Bulk SEO | `*/5 * * * *` |
| `bin/seo-performance-worker.php` | Performance | `0 */4 * * *` |
| `bin/ai-worker.php` | Worker IA | Contínuo |
| `bin/ai-queue-monitor.php` | Monitor fila IA | `*/5 * * * *` |
| `bin/ai-analyze-items.php` | Análise IA | Manual/Cron |
| `bin/ai-cost-report.php` | Relatório custos | `0 8 * * 1` |
| `bin/ml-ai-optimization-worker.php` | Otimização ML+IA | Contínuo |
| `bin/tech-sheet-auto-optimizer.php` | Tech sheet auto | `0 3 * * *` |
| `bin/tech-sheet-cache-warmup.php` | Cache warmup | `0 2 * * *` |
| `bin/tech-sheet-daily-report.php` | Relatório diário | `0 7 * * *` |
| `bin/tech-sheet-scheduler.php` | Scheduler | `*/10 * * * *` |
| `bin/ab-test-worker.php` | A/B testing | `*/5 * * * *` |
| `bin/ab-test-updater.php` | A/B updater | `*/15 * * * *` |
| `bin/watchlist-updater.php` | Watchlist | `*/30 * * * *` |

### 2.5 Consolidar Services Duplicados de SEO
| Service | Cópias | Ação |
|---------|--------|------|
| `TitleOptimizerService` | 2 | Manter `SEO/`, deletar outro |
| `KeywordResearchService` | 2 | Manter raiz, deletar `SEO/` |
| `KeywordSourceService` | 2 | Manter `SEO/`, deletar outro |
| `SynonymExpansionService` | 2 | Manter `SEO/`, deletar outro |
| `SemanticScoreService` | 2 | Manter `SEO/`, deletar outro |
| `ValidationService` | 2 | Avaliar qual é mais completo |

**Critério de saída:** Otimizar 50 títulos reais da conta AWA Motos com resultado mensurável (CTR antes vs depois).

---

## Fase 3 — Clonagem de Catálogo (2-3 semanas)

**Objetivo:** Clonar anúncios entre contas ML da AWA Motos.
**Dependência:** Fase 1 (precisa de auth e items).

### 3.1 Clone Básico
| Feature | Arquivo | Linhas | Status | Ação |
|---------|---------|--------|--------|------|
| Clone core | `CatalogCloneService.php` | 2720 | Real | Validar em produção |
| Clone controller | `CatalogCloneController.php` | ~800 | Real | Validar |
| Clone worker | `bin/catalog-clone-worker.php` | ~400 | Real | Testar background |
| Clone template | `CloneTemplateService.php` | ~600 | Real | Validar |

**Migrations:**
| Migration | Conteúdo |
|-----------|----------|
| `2026_01_30_create_catalog_clone_batch_tables.sql` | Batch tables |
| `2026_01_30_create_clone_templates_tables.sql` | Templates |
| `2026_01_31_create_clone_health_alerts_tables.sql` | Health alerts |
| `2025_01_08_clone_compliance_analytics_tables.php` | Compliance |
| `2025_06_clone_advanced_tables.php` | Advanced tables |
| `2025_06_clone_sync_tables.php` | Sync tables |
| `2026_02_01_clone_advanced_features_tables.php` | Advanced features |
| `2026_02_clone_event_triggers_tables.php` | Event triggers |
| `2026_02_clone_scheduler_tables.php` | Scheduler |
| `create_clone_schedules_table.sql` | Schedules |

### 3.2 Views de Clonagem
| View | Linhas | Descrição |
|------|--------|-----------|
| `app/Views/dashboard/catalog_clone.php` | 415 | Clone básico |
| `app/Views/dashboard/catalog_clone_batch.php` | 1457 | Clone em lote |
| `app/Views/dashboard/catalog_clone_metrics.php` | 369 | Métricas do clone |
| `app/Views/catalog/clone.php` | 1011 | View clone alternativa |
| `app/Views/dashboard/clone_ab_testing.php` | 776 | A/B testing de clones |
| `app/Views/dashboard/clone_analytics.php` | 769 | Analytics de clones |
| `app/Views/dashboard/clone_automation.php` | 676 | Automação de clones |
| `app/Views/dashboard/clone_roi_analysis.php` | 618 | ROI de clones |
| `app/Views/dashboard/clone_compliance.php` | 768 | Compliance de clones |
| `app/Views/dashboard/clone_items_management.php` | 748 | Gestão de itens clonados |
| `app/Views/dashboard/clone_monitoring.php` | 460 | Monitoramento de clones |
| `app/Views/dashboard/clone_notifications.php` | 574 | Notificações de clone |
| `app/Views/dashboard/clone_operations.php` | 821 | Operações de clone |
| `app/Views/dashboard/clone_realtime_dashboard.php` | 694 | Dashboard real-time |
| `app/Views/dashboard/clone_scheduler.php` | 752 | Scheduler de clone |
| `app/Views/dashboard/clone_seller_recommendations.php` | 709 | Recomendações de vendedores |
| `app/Views/dashboard/clone_triggers.php` | 617 | Triggers de clone |
| `app/Views/dashboard/clone_widget_embed.php` | 641 | Widget embed |

**NOTA:** 18 views para uma funcionalidade de clone (11.870 linhas total). Muitas devem ser consolidadas.

**Assets JS/CSS:**
| Asset | Linhas | Uso |
|-------|--------|-----|
| `public/js/catalog-clone.js` | 1002 | JS principal do clone |
| `public/js/clone-progress-widget.js` | 535 | Widget de progresso |

**Testes existentes (7):**
- `tests/Unit/Services/CatalogCloneServiceTest.php`
- `tests/Unit/Services/CloneMetricsServiceTest.php`
- `tests/Unit/Services/CloneMonitoringServiceTest.php`
- `tests/Unit/Services/ClonePostActionsServiceTest.php`
- `tests/Unit/Services/CloneTemplateServiceTest.php`
- `tests/Unit/CloneAlertNotificationServiceTest.php`
- `tests/Unit/CloneDuplicateDetectionServiceTest.php`

**Testes que faltam criar:**
- `tests/Feature/CloneRealAccountTest.php` — clonar anúncios reais entre 2 contas ML

### 3.3 Avaliar se precisa das 6 sub-features
| Sub-feature | Arquivo | View (Linhas) | Decisão |
|-------------|---------|---------------|---------|
| Clone A/B Testing | `CloneABTestingService.php` | `clone_ab_testing.php` (776L) | **Postergar** |
| Clone Automation | `CloneAutomationService.php` | `clone_automation.php` (676L) | **Postergar** |
| Clone ROI Analysis | `CloneROIAnalysisService.php` | `clone_roi_analysis.php` (618L) | **Postergar** |
| Clone Analytics | `CloneAnalyticsService.php` | `clone_analytics.php` (769L) | **Postergar** |
| Clone Health Monitor | `CloneHealthMonitorService.php` | `clone_monitoring.php` (460L) | **Manter** |
| Clone Sync | `CloneSyncService.php` | `clone_operations.php` (821L) | **Manter** |

**Workers/Crons:**
| Script | Tipo | Cron sugerido |
|--------|------|---------------|
| `bin/catalog-clone-worker.php` | Worker principal | Contínuo |
| `bin/clone-sync-worker.php` | Sync clones | `*/10 * * * *` |
| `bin/clone-health-monitor.php` | Health monitor | `*/15 * * * *` |
| `bin/clone-alert-monitor.php` | Alertas | `*/10 * * * *` |
| `bin/clone-post-actions-worker.php` | Pós-clone | `*/5 * * * *` |
| `bin/generate-clone-metrics-report.php` | Relatórios | `0 8 * * 1` |
| `bin/cleanup-clone-data.php` | Limpeza | `0 3 * * 0` |
| `bin/clone-ab-testing-worker.php` | A/B testing | Postergar |
| `bin/clone-automation-worker.php` | Automação | Postergar |
| `bin/clone-event-trigger-worker.php` | Triggers | Postergar |
| `bin/clone-roi-sync-worker.php` | ROI sync | Postergar |
| `bin/clone-scheduler-worker.php` | Scheduler | Postergar |
| `bin/clone-seller-recommendations-worker.php` | Recomendações | Postergar |

**Critério de saída:** Clonar 100 anúncios reais entre 2 contas ML com sucesso.

---

## Fase 4 — Pricing e Concorrência (3-4 semanas)

**Objetivo:** Preço competitivo automático.
**Dependência:** Fase 2 (SEO usa dados de competidores compartilhados com pricing).

### 4.1 Pricing Dinâmico
| Feature | Arquivo | Status | Ação |
|---------|---------|--------|------|
| Pricing controller | `DynamicPricingController.php` | Real | Validar |
| Pricing service | `DynamicPricingService.php` | Real | Validar |
| Auto optimizer | `AutoPricingOptimizerService.php` | Real | Testar com limites seguros |
| Rules engine | `PriceRulesEngineService.php` | Real | Validar regras |

**PROBLEMA CRÍTICO:** `PricingIntelligenceController.php` tem 4707 linhas e 123 métodos.
```
PricingIntelligenceController.php (4707 linhas)
  → PricingDashboardController.php  (~400 linhas)
  → PricingAnalysisController.php   (~500 linhas)
  → PricingRulesController.php      (~400 linhas)
  → PricingHistoryController.php    (~300 linhas)
```

**Impacto nas rotas:** ~284 rotas com "pricing" em `api.php` precisarão ser redistribuídas.

**Migrations:**
| Migration | Conteúdo |
|-----------|----------|
| `006_create_price_history_table.sql` | Histórico de preços |
| `2024_06_01_create_pricing_phase3_tables.sql` | Phase 3 pricing |
| `2025_01_01_000003_create_pricing_rules.sql` | Regras de preço |
| `2025_01_01_000004_create_competitor_monitoring_tables.sql` | Monitoring |
| `2026_01_29_create_pricing_intelligence_tables.sql` | Intelligence tables |
| `create_competitor_watchlist_table.php` | Watchlist |

### 4.2 Views de Pricing
| View | Linhas | Descrição |
|------|--------|-----------|
| `app/Views/pricing/dashboard.php` | **8293** | **Maior view do projeto** |
| `app/Views/pricing/history.php` | 811 | Histórico de preços |
| `app/Views/dashboard/competitors.php` | 189 | Lista de concorrentes |
| `app/Views/dashboard/competitors/index.php` | 149 | Index concorrentes |
| `app/Views/dashboard/competitors/details.php` | 162 | Detalhes concorrente |
| `app/Views/dashboard/competitor-monitor.php` | 885 | Monitor de concorrentes |
| `app/Views/dashboard/catalog/competition.php` | 658 | Competição por catálogo |

**ALERTA:** `pricing/dashboard.php` com 8293 linhas precisa ser quebrado em componentes.

**Assets:**
Pricing usa JS inline nas views e `dashboard-modern.js` (5495L).

**Testes existentes (5):**
- `tests/Unit/Services/PricingStrategyServiceTest.php`
- `tests/Unit/Services/MercadoLivre/AdvancedPricingEngineTest.php`
- `tests/Unit/Services/MercadoLivre/CompetitorIntelligenceServiceTest.php`
- `tests/Unit/Services/SEO/CompetitorAnalysisServiceTest.php`
- `tests/Feature/PricingIntelligenceTest.php`

**Testes que faltam criar:**
- `tests/Feature/PricingAutoRuleTest.php` — regra automática em 20 produtos por 1 semana

### 4.3 Análise de Concorrentes
| Feature | Arquivo | Status | Ação |
|---------|---------|--------|------|
| Competitor analysis | `CompetitorAnalysisService.php` | 2 cópias (417L + 621L) | **Consolidar** |
| Competitor intelligence | `CompetitorIntelligenceService.php` | 2 cópias (1849L + 127L) | **Manter o de 1849L** |
| Competitor monitor | `CompetitorMonitorService.php` | Real | Validar |

**Workers/Crons:**
| Script | Tipo | Cron sugerido |
|--------|------|---------------|
| `bin/pricing-worker.php` | Worker pricing | Contínuo |
| `bin/auto-pricing-optimizer.php` | Auto optimizer | `*/10 * * * *` |
| `bin/rules-engine-worker.php` | Rules engine | `*/5 * * * *` |
| `bin/scheduled-price-worker.php` | Scheduled pricing | `0 */2 * * *` |
| `bin/competitor-monitor-worker.php` | Monitor concorrentes | `*/30 * * * *` |

**Critério de saída:** Regra de preço automática rodando em 20 produtos reais por 1 semana sem erros.

---

## Fase 5 — Pedidos e Pós-venda (2-3 semanas)

**Objetivo:** Gestão de pedidos e reclamações.
**Dependência:** Fase 1 (precisa de auth e items).

### 5.1 Pedidos
| Feature | Arquivo | Status | Ação |
|---------|---------|--------|------|
| Order controller | `OrderController.php` | Real | Validar |
| Order service | `OrderService.php` | Real | Validar com pedidos reais |
| Webhooks ML | `MercadoLivreWebhookController.php` | Real | Configurar endpoint no ML |

**Migrations:**
| Migration | Conteúdo |
|-----------|----------|
| `004_create_ml_orders_table.sql` | Tabela orders |
| `016_add_user_id_to_ml_orders.sql` | User ID em orders |
| `2026_02_15_expand_ml_orders_table.sql` | Expansão da tabela |

**Rotas webhooks (webhooks.php — 3 rotas):**
Todas as rotas de `app/Routes/webhooks.php`.

### 5.2 Perguntas e Respostas
| Feature | Arquivo | Status | Ação |
|---------|---------|--------|------|
| Questions | `QuestionService.php` | Real | Validar respostas |

**Migration:** `2026_02_16_create_ml_questions_table.sql`

### 5.3 Reclamações
| Feature | Arquivo | Status | Ação |
|---------|---------|--------|------|
| Claims | `ClaimsService.php` + `ClaimsController.php` (65L) | Funcional | Controller muito fino, validar |

**Migration:** `20260122_create_ml_claims_table.php`

### 5.4 Frete
| Feature | Arquivo | Status | Ação |
|---------|---------|--------|------|
| Shipping | `ShippingService.php` | Real | Validar cálculos Mercado Envios |

**Views:**
| View | Linhas | Descrição |
|------|--------|-----------|
| `app/Views/dashboard/orders.php` | 798 | Listagem de pedidos |
| `app/Views/dashboard/orders-content.php` | 570 | Conteúdo de pedidos |
| `app/Views/dashboard/questions.php` | 364 | Perguntas e respostas |
| `app/Views/dashboard/messages.php` | 241 | Mensagens da conta |
| `app/Views/dashboard/claims/index.php` | 157 | Reclamações |
| `app/Views/dashboard/returns/index.php` | 291 | Devoluções |
| `app/Views/dashboard/shipping.php` | 210 | Gestão de frete |
| `app/Views/dashboard/logistics/flex.php` | 224 | Mercado Envios Flex |
| `app/Views/dashboard/logistics/full_restock.php` | 128 | Restock Full |

**Testes existentes (1):**
- `tests/Unit/Services/OrderServiceTest.php`

**Testes que faltam criar:**
- `tests/Feature/WebhookProcessTest.php` — processar webhook real do ML
- `tests/Feature/ClaimsFlowTest.php` — fluxo de reclamação
- `tests/Unit/Services/QuestionServiceTest.php` — perguntas e respostas
- `tests/Unit/Services/ShippingServiceTest.php` — cálculos de frete

**Workers/Crons:**
| Script | Tipo | Cron sugerido |
|--------|------|---------------|
| `bin/migrate_questions.php` | Migração | Manual (uma vez) |
| `bin/stock-sync-worker.php` | Sync estoque | `*/30 * * * *` (se usar) |

**Critério de saída:** Receber webhook de novo pedido, visualizar no dashboard, responder pergunta.

---

## Fase 6 — Relatórios e Export (2 semanas)

**Objetivo:** Relatórios úteis para gestão do negócio.
**Dependência:** Fase 5 (relatórios financeiros precisam de dados de pedidos reais).

### 6.1 Relatórios
| Feature | Arquivo | Problema | Ação |
|---------|---------|----------|------|
| Financial reports | `FinancialReportController.php` (3278L) | **God class** | Refatorar |
| Financial service | `FinancialService.php` (7501L, 179 métodos) | **Pior god object** | **Quebrar em 5-8 services** |
| Report service | `ReportService.php` | Real | Validar |
| Export PDF | `PdfService.php` (DomPDF) | Real | Testar |
| Export CSV/Excel | `ExportService.php` | Real | Testar |

**Refatoração obrigatória de FinancialService.php:**
```
FinancialService.php (7501 linhas, 179 métodos)
  → PnLService.php             — P&L, margem, lucratividade
  → CashFlowService.php        — fluxo de caixa, projeções
  → BillingService.php         — comissões ML, taxas, Mercado Pago
  → SettlementService.php      — liquidações, repasses
  → ReputationMetricsService.php — reputação, visitas, conversão
  → ClaimsFinanceService.php   — reclamações, devoluções, impacto financeiro
  → RevenueAnalyticsService.php — receita diária, semanal, tendências
```

**Views:**
| View | Linhas | Descrição |
|------|--------|-----------|
| `app/Views/dashboard/financials.php` | 190 | Dashboard financeiro |
| `app/Views/dashboard/financials/conciliation.php` | 167 | Conciliação financeira |
| `app/Views/dashboard/reports/index.php` | 193 | Central de relatórios |
| `app/Views/dashboard/advanced-analytics.php` | 694 | Analytics avançado |
| `app/Views/dashboard/advanced.php` | 880 | Painel avançado |

**Testes existentes (2):**
- `tests/Unit/Services/FinancialServiceTest.php`
- `tests/Unit/Services/TechSheetExportServiceTest.php`

**Testes que faltam criar:**
- `tests/Feature/PdfExportTest.php` — gerar PDF real
- `tests/Feature/CsvExportTest.php` — exportar CSV com dados reais
- `tests/Feature/FinancialReportTest.php` — relatório financeiro mensal

**Workers/Crons:**
| Script | Tipo | Cron sugerido |
|--------|------|---------------|
| `bin/automated-reports-worker.php` | Relatórios auto | `0 7 * * 1` |
| `bin/weekly-report.php` | Semanal | `0 8 * * 1` |
| `bin/ean-payment-reconcile-worker.php` | Reconciliação | `0 6 * * *` |

**Critério de saída:** Gerar PDF de relatório financeiro mensal com dados reais.

---

## Fase 7 — Notificações (1-2 semanas)

**Objetivo:** Alertas que importam.
**Dependência:** Fase 1 (precisa de auth).

| Feature | Status | Ação |
|---------|--------|------|
| Push (web-push) | Real | Validar |
| Email (PHPMailer) | Real | Validar |
| Real-time polling | Real | Validar |
| WhatsApp (Twilio) | Real (285L) | Validar se tem conta Twilio |
| Telegram | Básico (96L) | OK como está — bot simples |

**Migrations:**
| Migration | Conteúdo |
|-----------|----------|
| `005_create_notifications_and_alerts_tables.sql` | Notifications + alerts |
| `010_pwa_push_notifications.sql` | Push web |
| `015_whatsapp_integration.sql` | WhatsApp |
| `create_notification_tables.php` | Notification tables |

**Views:**
| View | Linhas | Descrição |
|------|--------|-----------|
| `app/Views/dashboard/notifications.php` | 185 | Central de notificações |
| `app/Views/dashboard/notifications/settings.php` | 194 | Config de notificações |
| `app/Views/dashboard/alerts.php` | 170 | Alertas do sistema |
| `app/Views/dashboard/whatsapp.php` | 216 | Config WhatsApp |

**Assets JS/CSS:**
| Asset | Linhas | Uso |
|-------|--------|-----|
| `public/js/realtime-notifications.js` | 762 | Notificações real-time |
| `public/js/pwa.js` | 572 | Progressive Web App |
| `public/css/pwa.css` | 604 | CSS PWA |
| `public/service-worker.js` | 92 | Service Worker push |
| `public/assets/css/notifications.css` | — | CSS notificações |

**Testes existentes (3):**
- `tests/Unit/Services/EmailServiceTest.php`
- `tests/Unit/Services/TechSheetNotificationServiceTest.php`
- `tests/Unit/CloneAlertNotificationServiceTest.php`

**Testes que faltam criar:**
- `tests/Feature/PushNotificationTest.php` — enviar push real
- `tests/Unit/Services/WhatsAppServiceTest.php` — mock Twilio
- `tests/Unit/Services/TelegramServiceTest.php` — mock Telegram

**Critério de saída:** Receber alerta push quando novo pedido chegar.

---

## Fase 8 — Refatoração de God Classes (contínua, 4-6 semanas total)

**Objetivo:** Código manutenível.
**Dependência:** Pode começar em paralelo com Fases 4-7. Não é bloqueante.

**NOTA IMPORTANTE:** Esta fase é **contínua**. Não esperar terminar todas as fases anteriores. Começar refatorações incrementais assim que uma feature estiver estável.

### God Views para quebrar
| View | Linhas | Ação |
|------|--------|------|
| `pricing/dashboard.php` | 8293 | Quebrar em componentes (`pricing-rules.php`, `pricing-history.php`, `pricing-competitors.php`, etc.) |
| `dashboard/tech-sheet/index.php` | 6761 | Quebrar em componentes por seção |
| `dashboard/account-health.php` | 5042 | Extrair pilares para componentes separados |
| `deep_research/index.php` | 3440 | Extrair seções |
| `dashboard/analysis.php` | 3449 | Extrair gráficos e tabelas |

### God Classes para quebrar
| Controller | Linhas | Métodos | Ação |
|------------|--------|---------|------|
| `PricingIntelligenceController.php` | 4707 | 123 | Quebrar em 4 controllers |
| `SEOKillerController.php` | 4252 | 175 | Quebrar em 5 controllers |
| `FinancialReportController.php` | 3278 | 131 | Quebrar em 4 controllers |

| Service | Linhas | Métodos | Ação |
|---------|--------|---------|------|
| `FinancialService.php` | 7501 | 179 | Quebrar em 7 services |
| `AccountHealthService.php` | 3917 | 58 | Quebrar em 3 services |
| `MLAnalyticsIntelligenceService.php` | 3208 | ~80 | Quebrar em 3 services |
| `CatalogCloneService.php` | 2720 | ~60 | Avaliar split |

### Services Duplicados para consolidar
| Service | Cópias | Total linhas | Ação |
|---------|--------|-------------|------|
| `PredictiveAnalyticsService` | 3 | 6557 | Manter 1, deletar 2 |
| `CompetitorAnalysisService` | 2 | 1038 | Consolidar |
| `CompetitorIntelligenceService` | 2 | 1976 | Manter o maior |
| `TechSheetService` | 2 | ~800 | Consolidar |
| `AnalyticsService` | 2 | ~600 | Consolidar |
| `AuditLogService` | 2 | ~400 | Consolidar |
| `ListingBuilderService` | 2 | ~800 | Consolidar |
| `LoggingService` | 2 | ~300 | Consolidar |
| + 10 outros duplicados | 2 cada | ~5000 | Consolidar |

### Impacto nas rotas
Redistribuir as rotas de `api.php` (1224 rotas, 1808 linhas) para apontar aos novos controllers. Considerar modularizar `api.php` em sub-arquivos:
```
app/Routes/
├── api.php           (1808L → ~200L loader)
├── api/
│   ├── seo.php       (~600 rotas)
│   ├── pricing.php   (~280 rotas)
│   ├── clone.php     (~375 rotas)
│   ├── items.php     (~300 rotas)
│   ├── orders.php    (~30 rotas)
│   └── misc.php      (restante)
```

**Critério de saída:** Nenhum arquivo com mais de 1000 linhas. Zero duplicatas. Rotas modularizadas.

---

## Fase 9 — Features Opcionais (postergar indefinidamente)

Estas features existem no código mas **não são prioridade**. Manter o código, mas não investir tempo validando até que haja demanda real.

| Feature | Razão para postergar |
|---------|---------------------|
| Shopee integration (199L) + `shopee/index.php` (97L) | Esqueleto, AWA não vende na Shopee ainda |
| Brand Central / Analyzer + `brand_analysis/index.php` (1782L) | Enterprise feature, não necessário agora |
| AI Predictions (regressão linear) | Estatística básica, não gera ação |
| AI Image Analyzer (1438L GD) + views (956L + 843L) | Útil mas não prioritário |
| AI Chatbot (518L) + JS (908L) | Depende de volume de perguntas |
| Deep Research (2021L) + `deep_research/index.php` (3440L) | Analytics avançado, pode esperar |
| Clone A/B Testing | Complexo demais para o momento |
| Clone ROI Analysis | Nice to have |
| Clone Automation (scheduler) | Manual basta por agora |
| Promotion Simulator | Funcional mas raramente usado |
| Stock Sync | Só se tiver controle de estoque integrado |
| Ads Manager + `ads/dashboard.php` (209L) + `ads/wizard.php` (311L) | Só se investir em Product Ads ML |
| 2FA (105L) | Funcional mas sem UI completa |
| Scheduled Pricing | Manual basta por agora |
| Price A/B Testing | Avançado demais para agora |
| Multi-account manager | Útil, mas clone service já cobre parcialmente |
| Automated weekly reports | Email worker, postergar |
| Trends analysis | Nice to have |

**Assets postergados:**
| Asset | Linhas | Feature |
|-------|--------|---------|
| `public/assets/js/seo-killer-chatbot.js` | 908 | Chatbot IA |
| `public/assets/css/seo-killer-chatbot.css` | 355 | Chatbot IA |
| `public/js/ads-wizard.js` | 422 | Ads Manager |
| `public/js/ads-manager.js` | 413 | Ads Manager |
| `public/js/ai-center.js` | 315 | AI Center |
| `public/css/ai-center.css` | 204 | AI Center |

### Views sem fase atribuída (avaliar quando chegar)
| View | Linhas | Descrição |
|------|--------|-----------|
| `app/Views/dashboard/account-health.php` | 5042 | Health da conta ML |
| `app/Views/dashboard/account-health-advanced.php` | 342 | Health avançado |
| `app/Views/dashboard/health/index.php` | 173 | Health index |
| `app/Views/dashboard/settings.php` | 265 | Configurações gerais |
| `app/Views/dashboard/settings-content.php` | 710 | Conteúdo settings |
| `app/Views/settings/proxies.php` | 600 | Config proxies |
| `app/Views/settings/users.php` | 198 | Config usuários |
| `app/Views/dashboard/api-tokens.php` | 411 | Tokens de API |
| `app/Views/dashboard/backups.php` | 233 | Backups |
| `app/Views/dashboard/jobs.php` | 264 | Background jobs |
| `app/Views/dashboard/logs/index.php` | 329 | Visualizador de logs |
| `app/Views/dashboard/audit.php` | 190 | Trilha de auditoria |
| `app/Views/dashboard/audit/index.php` | 82 | Audit index |
| `app/Views/dashboard/cache/index.php` | 473 | Gestão de cache |
| `app/Views/dashboard/agents.php` | 394 | Painel de agents |
| `app/Views/dashboard/help.php` | 342 | Ajuda |
| `app/Views/monitoring/dashboard.php` | 614 | Monitoramento do sistema |
| `app/Views/security/dashboard.php` | 418 | Dashboard de segurança |
| `app/Views/dashboard/marketing/promotions.php` | 220 | Promoções |
| `app/Views/dashboard/customers/index.php` | 181 | Clientes |
| `app/Views/dashboard/ai-center.php` | 176 | Central de IA |
| `app/Views/dashboard/analytics/index.php` | 297 | Analytics |
| `app/Views/dashboard/ai_optimization/index.php` | 434 | Otimização IA |
| `app/Views/dashboard/ai_optimization/editor.php` | 589 | Editor de otimização IA |
| `app/Views/dashboard/research.php` | 701 | Pesquisa |
| `app/Views/public/product.php` | 78 | Página pública de produto |
| `app/Views/dashboard/openspec/index.php` | 228 | OpenSpec |
| `app/Views/dashboard/openspec/create_proposal.php` | 97 | Criar proposta |
| `app/Views/dashboard/openspec/change_detail.php` | 238 | Detalhe de mudança |
| `app/Views/error.php` | 43 | Página de erro genérica |
| `app/Views/errors/404.php` | 476 | Página 404 |
| `app/Views/errors/500.php` | 37 | Página 500 |
| `app/Views/maintenance.php` | 52 | Página de manutenção |

### Scripts CLI postergados
| Script | Feature |
|--------|---------|
| `bin/ml-auto-improve.php` | Auto-improve ML |
| `bin/dev-apply-ml-auto-improve.sh` | Dev auto-improve |
| `bin/dev-smoke-ml-auto-improve.sh` | Smoke test |
| `bin/raiox-conta.php` | Raio-X da conta |
| `bin/download-geoip-db.php` | GeoIP |
| `bin/mcp-ml-auth.php` | MCP auth |
| `bin/mcp-ml-start.sh` | MCP start |
| `bin/mcp-ml-token.php` | MCP token |
| `bin/verify-system.php` | Verificação geral |
| `bin/validate-ml-health-fix.php` | Validação health |
| `bin/test-ml-integration.php` | Teste integração |
| `bin/test-implementation.php` | Teste implementação |

---

## Resumo de Esforço (revisado)

| Fase | Duração | Paralelizável | Resultado |
|------|---------|---------------|-----------|
| **0 — Limpeza** | 1-2 dias | Não | Base limpa, harness honesto |
| **1 — Core** | 2-3 semanas | Não | Login + Dashboard + Itens |
| **2 — SEO** | 3-4 semanas | Sim (após F1) | Títulos e descrições otimizados |
| **3 — Clone** | 2-3 semanas | Sim (após F1) | Clonagem entre contas |
| **4 — Pricing** | 3-4 semanas | Após F2 | Preço automático competitivo |
| **5 — Pedidos** | 2-3 semanas | Sim (após F1) | Gestão de vendas |
| **6 — Relatórios** | 2 semanas | Após F5 | PDFs e exports |
| **7 — Notificações** | 1-2 semanas | Sim (após F1) | Alertas push/email |
| **8 — Refatoração** | 4-6 semanas | Contínua | Código manutenível |
| **9 — Opcionais** | indefinido | Sob demanda | Sob demanda |
| **TOTAL** | **~20-30 semanas** | — | Sistema real em produção |

### Caminho crítico (mais rápido possível para produção)
```
Semana 1-2:   Fase 0 + início Fase 1
Semana 3-5:   Fase 1 completa
Semana 6-9:   Fase 2 (SEO) + Fase 3 (Clone) em paralelo
Semana 10-13: Fase 4 (Pricing) + Fase 5 (Pedidos) em paralelo
Semana 14-15: Fase 6 (Relatórios) + Fase 7 (Notificações) em paralelo
Semana 16-20: Fase 8 (Refatoração) — pode ter começado incremental na semana 10
Semana 20+:   Fase 9 sob demanda
```

**Se 1 dev:** ~20-30 semanas (5-7 meses)
**Se 2 devs:** ~14-20 semanas (3.5-5 meses)
**Se 1 dev + AI coding assistants:** ~16-24 semanas (4-6 meses)

---

## Regra de Ouro

> **Nenhuma feature é "passing" até ter:**
> 1. Teste automatizado que roda com `phpunit`
> 2. Validação manual com dados reais do Mercado Livre
> 3. Pelo menos 1 semana rodando em produção sem erro

Os agents anteriores marcaram 73/73 como "passing" no dia da criação. Isso não acontece mais.
