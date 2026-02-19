# SEO Module Consolidation Guide

> **Data**: 2025-07-17 | Atualizado: 2026-02-15
> **Status**: Fase 2 concluída (Limpeza de referências + Controllers menores depreciados)

## Resumo

O sistema possuía **5 módulos SEO sobrepostos** causando confusão na navegação.
A consolidação unifica tudo em uma única interface: **SEO Killer** (`/dashboard/seo-killer`).

---

## Interface Canônica

| Componente | Caminho |
|---|---|
| **View** | `app/Views/dashboard/seo-killer.php` |
| **Controller** | `app/Controllers/SEOKillerController.php` |
| **Rota** | `/dashboard/seo-killer` |
| **Menu** | Navbar → "SEO" (dropdown com sub-abas) |

### Abas do SEO Killer
1. Dashboard (diagnóstico, ferramentas, ações rápidas, AutoPilot)
2. Ficha Técnica
3. Espião de Concorrentes
4. Performance Tracker
5. Testes A/B
6. Search Console
7. AI Insights
8. AI Pricing
9. AI Images

---

## Redirects (301) Implementados

| Rota Antiga | Destino | Controller Alterado |
|---|---|---|
| `/seo` | `/dashboard/seo-killer` | `ViewController::seoDashboard` |
| `/seo/dashboard` | `/dashboard/seo-killer` | `ViewController::seoDashboard` |
| `/dashboard/seo` | `/dashboard/seo-killer` | `DashboardController::seo` |
| `/dashboard/seo-intelligence` | `/dashboard/seo-killer` | `ViewController::seoIntelligence` |
| `/dashboard/ai-optimization` | `/dashboard/seo-killer#ai-insights` | `AIOptimizationController::index` |

---

## Serviços Canônicos (usados pelo SEO Killer)

| Funcionalidade | Serviço Canônico | Namespace |
|---|---|---|
| Motor SEO | `SEOKillerEngine` | `App\Services\AI\SEO` |
| Títulos | `TitleKiller` | `App\Services\AI\SEO` |
| Descrições | `DescriptionKiller` | `App\Services\AI\SEO` |
| Atributos | `AttributeKiller` | `App\Services\AI\SEO` |
| Keywords | `KeywordKiller` | `App\Services\AI\SEO` |
| Concorrentes | `CompetitorSpy` | `App\Services\AI\SEO` |
| Otimização em Lote | `BulkOptimizer` | `App\Services\AI\SEO` |
| AutoPilot | `AutoPilot` + `AutoPilotStatusManager` | `App\Services\AI\SEO` |
| Performance | `PerformanceTracker` | `App\Services\AI\SEO` |
| Imagens | `ImageKiller` | `App\Services\AI\SEO` |
| Testes A/B | `ABTester` | `App\Services\AI\SEO` |
| Estratégias | `SEOStrategiesEngine` | `App\Services\AI\SEO\Strategies` |
| Search Console | `GoogleSearchConsoleService` | `App\Services\AI\SEO` |
| Schema/JSON-LD | `SchemaGenerator` | `App\Services\AI\SEO` |
| Backlinks | `BacklinkAnalyzer` | `App\Services\AI\SEO` |
| PDF Export | `PdfExporter` | `App\Services\AI\SEO` |
| Score | `SEOScoreCalculator` | `App\Services\AI\SEO` |
| Sinônimos | `SynonymExpansionService` | `App\Services\AI\SEO\Strategies` |
| Score Semântico | `SemanticScoreService` | `App\Services\AI\SEO\Strategies` |
| Fontes de KW | `KeywordSourceService` | `App\Services\AI\SEO\Strategies` |
| Campos Ocultos | `HiddenFieldsService` | `App\Services\AI\SEO\Strategies` |
| Injetor de KW | `KeywordInjectorService` | `App\Services\AI\SEO\Strategies` |
| Cobertura de Busca | `SearchTypeCoverageService` | `App\Services\AI\SEO\Strategies` |
| Peso de Campos | `FieldWeightService` | `App\Services\AI\SEO\Strategies` |
| SEO Avançado | `AdvancedSEOMaximizer` | `App\Services\AI\SEO` |
| Predição | `SEOPerformancePredictor` | `App\Services\AI\SEO` |
| Auto-Otimizador | `IntelligentAutoOptimizer` | `App\Services\AI\SEO` |

---

## Serviços Deprecados (duplicados)

| Serviço Antigo | Substituído por | Local |
|---|---|---|
| `SeoAnalyzerService` | `SEOKillerEngine` | `app/Services/` |
| `TitleOptimizerService` | `TitleKiller` | `app/Services/` |
| `KeywordResearchService` | `KeywordKiller` | `app/Services/` |
| `ListingBuilderService` | `SEOKillerEngine` | `app/Services/` |
| `SEO\TitleOptimizerService` | `TitleKiller` | `app/Services/SEO/` |
| `SEO\CompetitorAnalysisService` | `CompetitorSpy` | `app/Services/SEO/` |
| `SEO\DescriptionBuilderService` | `DescriptionKiller` | `app/Services/SEO/` |
| `SEO\SEOAuditService` | `SEOKillerEngine` | `app/Services/SEO/` |
| `SEO\SEOOptimizerService` | `SEOKillerEngine` | `app/Services/SEO/` |
| `SEO\SEOMonitoringService` | `PerformanceTracker` | `app/Services/SEO/` |
| `SEO\KeywordDistributionService` | `KeywordKiller` | `app/Services/SEO/` |

---

## Controllers Deprecados

| Controller | Status | Motivo |
|---|---|---|
| `SEOController` | `@deprecated` | Funcionalidade no `SEOKillerController` |
| `SEOToolsController` | `@deprecated` | Funcionalidade no `SEOKillerController` |
| `SEOApiController` | `@deprecated` | Funcionalidade no `SEOKillerController` |
| `AIOptimizationController` | `@deprecated` | Dashboard redirecionado; APIs ainda funcionais |

> **Nota**: APIs dos controllers deprecados continuam funcionando. Não deletar até migrar todos os consumidores.

---

## Views Deprecadas

| View | Status |
|---|---|
| `app/Views/seo/dashboard.php` | `@deprecated` - nunca mais renderizada |
| `app/Views/dashboard/seo.php` | `@deprecated` - nunca mais renderizada |
| `app/Views/dashboard/seo-intelligence.php` | `@deprecated` - nunca mais renderizada |

---

## Controllers Secundários Deprecados (Fase 2)

| Controller | Status | Motivo |
|---|---|---|
| `SeoSynonymsController` | `@deprecated` | Consolidado em `SEOKillerController` |
| `SeoKeywordsController` | `@deprecated` | Consolidado em `SEOKillerController` (KeywordKiller) |
| `SeoDescriptionController` | `@deprecated` | Consolidado em `SEOKillerController` (DescriptionKiller) |
| `SeoCoverageController` | `@deprecated` | Consolidado em `SEOKillerController` |
| `SeoStrategiesController` | `@deprecated` | Consolidado em `SEOKillerController` (SEOStrategiesEngine) |

---

## Referências Atualizadas (Fase 2)

| Arquivo | Mudança |
|---|---|
| `Views/errors/404.php` | `/dashboard/seo` → `/dashboard/seo-killer` |
| `Views/layouts/modern/sidebar.php` | Removido link duplicado `/seo`; "Ficha Técnica" → `#technical-sheet`; "Otimização IA" → `#ai-insights` |
| `Views/layouts/app.php` | `/dashboard/ai-optimization` → `#ai-insights`; `/dashboard/seo-intelligence` → `#competitor-spy` |
| `Views/dashboard/index.php` | Quick action "Otimizar SEO" → "SEO Killer" `/dashboard/seo-killer` |
| `Views/dashboard/items.php` | Botão `/seo` → `/dashboard/seo-killer` |
| `Views/dashboard/account-health.php` | 3 links `/seo` → `/dashboard/seo-killer` |
| `Views/dashboard/seo-intelligence/listing-detail.php` | Botão "Voltar" → `/dashboard/seo-killer` |

---

## Fase 3 (Futura)

- [ ] Migrar endpoints de `SEOToolsController` → `SEOKillerController`
- [ ] Migrar endpoints de `SEOApiController` → `SEOKillerController`
- [ ] Migrar endpoints de `SEOController` → `SEOKillerController`
- [ ] Migrar endpoints dos 5 Seo*Controllers → `SEOKillerController`
- [ ] Mover views deprecados para `app/Views/_deprecated/`
- [ ] Mover services antigos para `app/Services/_deprecated/`
- [ ] Remover rotas mortas de `web.php` e `api.php`
