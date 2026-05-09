# Módulos e Funcionalidades — eskill.com.br
>
> **Projeto:** SEO Optimizer para Mercado Livre | **Empresa:** AWA Motos
> **Stack:** PHP 8.0+ | MySQL | Redis | Guzzle 7 | Monolog 3
> **Última atualização:** 2026-06-01

> **LIMPEZA 2026-06:** Serviços e controllers órfãos movidos para `.tmp/orphan-services/`.
> Módulos Amazon/Shopee desativados (skeleton sem rotas). Dev tooling movido para `.tmp/`.
> Veja `.tmp/` para recuperação se necessário.

---

## Sumário

1. [Autenticação e Acesso](#1-autenticação-e-acesso)
2. [Dashboard](#2-dashboard)
3. [SEO e Otimização de Anúncios](#3-seo-e-otimização-de-anúncios)
4. [Clonagem de Catálogo](#4-clonagem-de-catálogo)
5. [Precificação Dinâmica](#5-precificação-dinâmica)
6. [Inteligência Artificial](#6-inteligência-artificial)
7. [Análise de Concorrentes](#7-análise-de-concorrentes)
8. [Gestão de Anúncios e Itens](#8-gestão-de-anúncios-e-itens)
9. [Gestão de Estoque](#9-gestão-de-estoque)
10. [Pedidos e Pós-Venda](#10-pedidos-e-pós-venda)
11. [Frete e Logística](#11-frete-e-logística)
12. [Relatórios e Exportação](#12-relatórios-e-exportação)
13. [Notificações e Alertas](#13-notificações-e-alertas)
14. [Monitoramento e Saúde da Conta](#14-monitoramento-e-saúde-da-conta)
15. [Raio X da Conta (X-Ray)](#15-raio-x-da-conta-x-ray)
16. [Governança de Conta](#16-governança-de-conta)
17. [Segurança](#17-segurança)
18. [Webhooks e Integrações](#18-webhooks-e-integrações)
19. [Publicidade (Ads)](#19-publicidade-ads)
20. [Marca e Posicionamento](#20-marca-e-posicionamento)
21. [Multi-Conta](#21-multi-conta)
22. [Finanças e Faturamento](#22-finanças-e-faturamento)
23. [Marketplaces Alternativos](#23-marketplaces-alternativos)
24. [Infraestrutura e Core](#24-infraestrutura-e-core)
25. [Workers e Jobs em Background](#25-workers-e-jobs-em-background)
26. [Testes e Qualidade](#26-testes-e-qualidade)

---

## 1. Autenticação e Acesso

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| AUTH-001 | Login com email/senha e sessão PHP | `AuthController.php`, `AuthService.php` |
| AUTH-002 | OAuth 2.0 com Mercado Livre (access_token + refresh_token) | `MercadoLivreAuthService.php`, `RefreshTokenService.php` |
| AUTH-003 | Auto refresh de token em background (worker) | `UnifiedTokenRefreshService.php`, `bin/auto-token-refresh-worker.php` |
| AUTH-004 | Two-factor authentication (2FA) | `TwoFactorService.php` |
| AUTH-005 | Diagnóstico e hardening da conexão OAuth ML | `MercadoLivreAuthService.php` |
| AUTH-006 | Monitor de falhas de autenticação | `AuthMonitorApiController.php`, `bin/monitor-auth-failures.php` |
| AUTH-007 | Password reset seguro com tokens | `PasswordResetService.php` |
| AUTH-008 | JWT para autenticação de API | `JwtService.php` |
| AUTH-009 | Token de acesso para API externas | `ApiTokenController.php`, `ApiTokenService.php` |

---

## 2. Dashboard

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| DASH-001 | Dashboard principal — métricas de vendas, anúncios e saúde da conta | `DashboardController.php`, `DashboardService.php` |
| DASH-002 | Token Dashboard — monitoramento de tokens OAuth | `TokenDashboardController.php` |
| DASH-003 | API do dashboard para dados em tempo real | `DashboardApiController.php` |
| DASH-004 | Estatísticas gerais do sistema | `StatisticsController.php`, `StatisticsService.php` |
| DASH-005 | Analytics de vendas e performance | `AnalyticsController.php`, `AnalyticsService.php` |

---

## 3. SEO e Otimização de Anúncios

### 3.1 SEO Killer (Motor Principal)

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| SEO-001 | Otimização de títulos com keywords relevantes | `SEOKillerController.php`, `app/Services/SEO/` |
| SEO-002 | Geração de títulos com IA (Claude/GPT) | `TitleGeneratorController.php`, `TitleGenerator/` |
| SEO-003 | Otimização de descrições com IA | `SeoDescriptionController.php`, `AIContentGeneratorService.php` |
| SEO-004 | Pesquisa e mineração de keywords | `SeoKeywordsController.php`, `KeywordResearchService.php`, `KeywordMinerService.php` |
| SEO-005 | Cobertura SEO — análise de gaps e oportunidades | `SeoCoverageController.php`, `GapHunterService.php` |
| SEO-006 | Sinônimos SEO automáticos para enriquecimento de títulos | `SeoSynonymsController.php`, `SynonymExpansionService.php` |
| SEO-007 | Bulk SEO — otimização em lote de múltiplos anúncios | `BulkSEOService.php`, `bin/bulk-seo-worker.php` |
| SEO-008 | SEO Performance tracking — rankings e posições | `SeoOptimizationController.php`, `bin/seo-performance-worker.php` |
| SEO-009 | SEO API — endpoints REST para otimização | `SEOApiController.php` |
| SEO-010 | Estratégias SEO avançadas | `SeoStrategiesController.php`, `SEOStrategiesEngine.php` |

### 3.2 Serviços SEO Internos

| Serviço | Função |
|---------|--------|
| `SEOOptimizerService.php` | Motor principal de otimização SEO |
| `SEOAuditService.php` | Auditoria completa de SEO por anúncio |
| `SEOMonitoringService.php` | Monitoramento contínuo de performance SEO |
| `TitleOptimizerService.php` | Otimização específica de títulos |
| `TitleAnalyzerService.php` | Análise e scoring de títulos |
| `TitleVariationsService.php` | Geração de variações de títulos |
| `KeywordDistributionService.php` | Distribuição de keywords no conteúdo |
| `KeywordGapAnalyzerService.php` | Análise de lacunas de keywords |
| `KeywordSourceService.php` | Fontes e coleta de keywords |
| `LongTailGeneratorService.php` | Geração de keywords long tail |
| `SemanticAnalyzerService.php` | Análise semântica de conteúdo |
| `SemanticScoreService.php` | Scoring semântico |
| `DescriptionBuilderService.php` | Construtor de descrições otimizadas |
| `CompatibilityService.php` | Compatibilidade de produtos (motos) |
| `SearchCoverageService.php` | Cobertura de buscas do marketplace |
| `SEOMonitoringService.php` | Monitoramento de rankings |
| `AttributeModelService.php` | Modelo de atributos SEO |
| `ContextInjectorService.php` | Injeção de contexto em prompts IA |
| `VersioningService.php` | Versionamento de otimizações |
| `TokenManager.php` | Gestão de tokens de uso da API IA |

### 3.3 Ficha Técnica

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| ITEMS-004 | Otimização de atributos técnicos do produto | `TechnicalSheetController.php`, `TechSheetService.php` |
| TS-001 | Auto-otimizador de ficha técnica | `TechSheetAutoOptimizerService.php`, `bin/tech-sheet-auto-optimizer.php` |
| TS-002 | Batch optimizer de fichas técnicas | `TechSheetBatchOptimizerService.php` |
| TS-003 | Smart gap filler automático | `TechSheetSmartGapFillerService.php` |
| TS-004 | Relatórios diários de fichas | `TechSheetEmailService.php`, `bin/tech-sheet-daily-report.php` |
| TS-005 | Scheduler de otimizações | `TechSheetSchedulerService.php`, `bin/tech-sheet-scheduler.php` |
| TS-006 | Analytics de fichas técnicas | `TechSheetAnalyticsService.php` |
| TS-007 | SEO integrado às fichas | `TechSheetSEOIntegrationService.php` |
| TS-008 | Export de fichas | `TechSheetExportService.php` |
| TS-009 | Benchmark de fichas | `TechSheetBenchmarkService.php` |
| TS-010 | Gráficos de evolução | `TechSheetChartsService.php` |
| TS-011 | Alertas de ficha técnica | `TechSheetAlertService.php`, `TechSheetNotificationService.php` |

---

## 4. Clonagem de Catálogo

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| CLONE-001 | Clonagem de catálogo entre contas ML | `CatalogCloneService.php`, `CatalogCloneController.php`, `bin/catalog-clone-worker.php` |
| CLONE-002 | Clone avançado com templates e customização por categoria | `CloneAdvancedController.php`, `CloneTemplateService.php` |
| CLONE-003 | Clone A/B Testing — variações de anúncios | `CloneABTestingController.php`, `CloneABTestingService.php` |
| CLONE-004 | Clone automação — scheduler e triggers | `CloneAutomationController.php`, `CloneAutomationService.php` |
| CLONE-005 | Clone ROI — análise de retorno dos clones | `CloneROIAnalysisController.php`, `CloneROIAnalysisService.php` |
| CLONE-006 | Clone analytics — métricas e dashboards | `CloneAnalyticsController.php`, `CloneAnalyticsService.php` |
| CLONE-007 | Clone health monitor — saúde dos itens clonados | `CloneHealthMonitorService.php`, `bin/clone-health-monitor.php` |
| CLONE-008 | Clone sync — sincronização contínua entre contas | `CloneSyncService.php`, `bin/clone-sync-worker.php` |
| CLONE-009 | Wizard de clonagem por concorrente (4 passos) | `CatalogCloneController.php`, `Views/dashboard/catalog_clone_wizard.php` |
| CLONE-010 | Clonagem por seller job via API | `CatalogCloneController.php::startSellerJob()` |

### Serviços auxiliares de Clone

| Serviço | Função |
|---------|--------|
| `CloneBatchOperationsService.php` | Operações em lote |
| `CloneComplianceService.php` | Compliance e regras de clonagem |
| `CloneDuplicateDetectionService.php` | Detecção de duplicatas |
| `CloneDataExportService.php` | Export de dados de clone |
| `CloneEventTriggerService.php` | Triggers de eventos |
| `CloneItemManagerService.php` | Gestão de itens clonados |
| `CloneMLRecommendationsService.php` | Recomendações ML para clones |
| `CloneMetricsService.php` | Métricas de performance |
| `CloneMonitoringService.php` | Monitoramento geral |
| `CloneNotificationService.php` | Notificações de clone |
| `ClonePostActionsService.php` | Ações pós-clonagem |
| `CloneProgressTrackerService.php` | Rastreamento de progresso |
| `CloneRealtimeDashboardService.php` | Dashboard em tempo real |
| `CloneReportExportService.php` | Export de relatórios |
| `CloneRetryStrategyService.php` | Estratégia de retry |
| `CloneSEOIntegrationService.php` | Integração SEO pós-clone |
| `CloneSellerRecommendationService.php` | Recomendações de vendedores |
| `CloneSlackDiscordNotificationService.php` | Notificações Slack/Discord |
| `CloneTrendChartService.php` | Gráficos de tendência de clone |
| `CloneAlertNotificationService.php` | Alertas de clonagem |
| `CloneAutoSchedulerService.php` | Agendamento automático |
| `CatalogCloneMonitoringService.php` | Monitoramento de catálogo |

---

## 5. Precificação Dinâmica

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| PRICE-001 | Pricing dinâmico baseado em concorrência | `DynamicPricingController.php`, `DynamicPricingService.php` |
| PRICE-002 | Auto pricing optimizer — otimização automática | `AutoPricingOptimizerService.php`, `bin/auto-pricing-optimizer.php` |
| PRICE-003 | Rules engine — regras de precificação | `PriceRulesEngineService.php`, `bin/rules-engine-worker.php` |
| PRICE-004 | Price history e analytics — histórico e tendências | `PriceHistoryService.php`, `PriceAnalyticsService.php` |
| PRICE-005 | Scheduled pricing — agendamento de mudanças | `ScheduledPriceService.php`, `bin/scheduled-price-worker.php` |
| PRICE-006 | Pricing intelligence — monitoramento de concorrentes | `PricingIntelligenceController.php`, `PricingCompetitorMonitorService.php` |
| PRICE-007 | Price A/B testing | `PriceAbTestService.php` |
| PRICE-008 | Price notifications — alertas de variação | `PriceNotificationService.php` |
| PRICE-009 | Cenários de precificação | `PricingScenarioService.php` |
| PRICE-010 | Estratégias de precificação | `PricingStrategyService.php` |
| PRICE-011 | Engine de precificação avançada (ML) | `MercadoLivre/AdvancedPricingEngine.php` |
| PRICE-012 | Calculadora de margem | `MarginCalculatorService.php` |
| PRICE-013 | Simulador de promoções | `PromotionSimulatorService.php` |
| PRICE-014 | Bulk price editor | `BulkPriceEditorService.php`, `BulkEditorController.php` |

---

## 6. Inteligência Artificial

### 6.1 Motor de IA

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| AI-001 | Integração com Claude (Anthropic) | `ClaudeClient.php`, `AI/Providers/ClaudeProvider.php` |
| AI-001b | Integração com OpenAI (GPT) | `AI/Providers/OpenAIProvider.php` |
| AI-001c | Integração com Google Gemini | `AI/Providers/GeminiProvider.php` |
| AI-002 | AI Center — dashboard centralizado de IA | `AICenterController.php` |
| AI-003 | AI Predictions — previsão de vendas e tendências | `AIPredictionsController.php`, `AIPredictionsService.php` |
| AI-004 | AI Queue — fila de processamento com workers | `bin/ai-worker.php`, `bin/ai-queue-monitor.php`, `QueueService.php` |
| AI-005 | AI Image Analyzer — análise de imagens de produtos | `AIImageAnalyzerService.php` |
| AI-006 | Deep Research — pesquisa profunda de mercado | `DeepResearchController.php`, `DeepResearchService.php` |
| AI-007 | Chatbot AI — assistente conversacional | `ChatbotAIController.php`, `ChatbotAIService.php` |
| AI-008 | ML-AI Integration Pipeline (circuit breaker + batch) | `MercadoLivreAIIntegrationService.php`, `MLAIIntegrationController.php` |
| AI-009 | ML-AI Version Management — comparação de versões | `VersioningService.php` |

### 6.2 Machine Learning (app/Services/AI/ML/)

| Serviço | Função |
|---------|--------|
| `CategoryLearningService.php` | Aprendizado de categorias ML |
| `DeepDemandPredictor.php` | Predição profunda de demanda |
| `KeywordClassifierService.php` | Classificação de keywords |
| `LearningEngine.php` | Motor de aprendizado |
| `MarketTrendPredictor.php` | Predição de tendências de mercado |
| `NLPIntegrationService.php` | Integração NLP (microserviço Python) |
| `PredictiveAnalytics.php` | Analytics preditivo |
| `SynonymGenerator.php` | Geração de sinônimos por ML |
| `TrendPredictorService.php` | Preditor de tendências |

### 6.3 Core de IA (app/Services/AI/Core/)

| Componente | Função |
|-----------|--------|
| `AIConfigService.php` | Configuração de provedores IA |
| `AIOptimizationEngine.php` | Motor de otimização IA |
| `AIProviderManager.php` | Gestão de múltiplos provedores IA |
| `BatchOptimizationQueue.php` | Fila de otimizações em lote |
| `CacheStrategy.php` | Estratégia de cache para IA |
| `CircuitBreakerService.php` | Circuit breaker para chamadas IA |
| `DecisionEngineService.php` | Motor de decisão |
| `LearningPipelineService.php` | Pipeline de aprendizado |
| `PromptBuilder.php` | Construtor de prompts |
| `RateLimiterService.php` | Limitador de taxa de API IA |
| `RetryService.php` | Serviço de retry |
| `ValidationService.php` | Validação de outputs IA |

### 6.4 Otimizadores IA (app/Services/AI/Optimizers/)

| Serviço | Função |
|---------|--------|
| `DescriptionOptimizer.php` | Otimizador de descrições |
| `TechSheetOptimizer.php` | Otimizador de fichas técnicas |
| `TitleOptimizer.php` | Otimizador de títulos |

### 6.5 Respostas Automáticas com IA

| Serviço | Função |
|---------|--------|
| `AI/Answers/AnswerGeneratorService.php` | Gerador de respostas a perguntas |
| `AI/Answers/QuestionAnalyzerService.php` | Analisador de perguntas de compradores |
| `MercadoLivre/SmartQAService.php` | Q&A inteligente via LLM |

---

## 7. Análise de Concorrentes

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| COMP-001 | Análise de concorrentes — preços, posições, tendências | `CompetitorAnalysisController.php`, `CompetitorAnalysisService.php` |
| COMP-002 | Monitor de concorrentes — monitoramento contínuo | `CompetitorMonitorController.php`, `bin/competitor-monitor-worker.php` |
| COMP-003 | Competitor Intelligence ML | `MercadoLivre/CompetitorIntelligenceService.php` |
| COMP-004 | Opportunity Detector — identificação de oportunidades | `OpportunityDetectorService.php`, `OpportunityController.php` |
| COMP-005 | Ranking Alert — alertas de queda/subida de posição | `RankingAlertService.php` |

---

## 8. Gestão de Anúncios e Itens

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| ITEMS-001 | CRUD de anúncios — listar, editar, pausar, ativar | `ItemController.php`, `ItemService.php` |
| ITEMS-002 | Bulk editor — edição em lote | `BulkEditorController.php`, `BulkPriceEditorService.php` |
| ITEMS-003 | Listing Builder — criação guiada de anúncios | `ListingBuilderController.php`, `ListingBuilderService.php` |
| ITEMS-005 | EAN/GTIN — gerenciamento de códigos de barras | `EanController.php`, `EanService.php` |
| ITEMS-006 | Bulk Compatibility — compatibilidade em massa | `BulkCompatibilityController.php` |
| ITEMS-007 | Inventory Manager automático | `InventoryAutoManager.php`, `InventoryAdvancedController.php` |
| ITEMS-008 | Item Metrics — métricas por anúncio | `ItemMetricsService.php` |
| ITEMS-009 | Item Sync — sincronização de anúncios | `ItemSyncService.php`, `bin/items-sync-worker.php` |
| ITEMS-010 | Auto Answer — respostas automáticas a perguntas | `bin/AutoAnswerJob.php` |
| ITEMS-011 | Attribute Suggestion — sugestão de atributos | `AttributeSuggestionService.php` |
| ITEMS-012 | Hidden Attributes Detector | `HiddenAttributesDetector.php` |
| ITEMS-013 | Listing Auto Creator | `ListingAutoCreator.php` |

---

## 9. Gestão de Estoque

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| STOCK-001 | Stock Sync — sincronização entre sistema e ML | `StockSyncController.php`, `bin/stock-sync-worker.php` |
| STOCK-002 | Inventory Service | `InventoryService.php` |
| STOCK-003 | Shipment Sync — sincronização de envios | `ShipmentSyncService.php`, `bin/shipments-sync-worker.php` |
| STOCK-004 | EAN Inventory — estoque por código EAN | `Models/EanInventory.php` |

---

## 10. Pedidos e Pós-Venda

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| ORDERS-001 | Gerenciamento de pedidos do ML | `OrderController.php`, `OrdersController.php`, `OrderService.php` |
| ORDERS-002 | Perguntas e respostas automáticas com IA | `QuestionController.php`, `QuestionService.php` |
| ORDERS-003 | Mensagens com compradores | `MessageController.php`, `MessagingController.php`, `MessagingService.php` |
| ORDERS-004 | Claims (reclamações e devoluções) | `ClaimsController.php`, `ClaimsService.php` |
| ORDERS-005 | Returns (gestão de devoluções) | `ReturnsController.php` |
| ORDERS-006 | Settlement — conciliação financeira | `SettlementController.php`, `SettlementService.php` |
| ORDERS-007 | Negociação com compradores | `NegotiationService.php` |
| ORDERS-008 | Order Audit ML | `MlOrderAuditController.php`, `MlOrderAuditService.php` |

---

## 11. Frete e Logística

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| SHIP-001 | Cálculo e gestão de frete (Mercado Envios) | `ShippingController.php`, `ShippingService.php` |
| SHIP-002 | Simulador de frete | `Shipping/ShippingSimulatorService.php` |
| SHIP-003 | Otimizador de frete | `Shipping/ShippingOptimizerService.php` |
| SHIP-004 | Calculadora de dimensões | `Shipping/DimensionCalculatorService.php` |
| SHIP-005 | Custo de frete financeiro | `Financial/ShippingCostService.php` |
| SHIP-006 | Flex (Mercado Envios Flex) | `FlexController.php` |

---

## 12. Relatórios e Exportação

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| REPORT-001 | Relatórios avançados — vendas, performance, financeiro | `AdvancedReportController.php`, `ReportService.php` |
| REPORT-002 | Relatórios financeiros — comissões, taxas, margem | `FinancialReportController.php`, `FinancialService.php` |
| REPORT-003 | Export de dados — PDF, CSV, Excel | `ExportController.php`, `ExportService.php`, `PdfService.php` |
| REPORT-004 | Relatórios automáticos por email/push | `bin/automated-reports-worker.php`, `bin/weekly-report.php` |
| REPORT-005 | X-Ray PDF Export | `PdfService::generateXRayReport()` |
| REPORT-006 | Settlement Report | `Financial/SettlementReportService.php` |
| REPORT-007 | PnL Report (Resultado) | `Financial/PnlReportService.php` |
| REPORT-008 | Financial Forecast | `Financial/FinancialForecastService.php` |
| REPORT-009 | Relatório de custos de IA | `bin/ai-cost-report.php` |

---

## 13. Notificações e Alertas

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| NOTIF-001 | Push notifications (web-push) | `PushController.php`, `PushNotificationService.php` |
| NOTIF-002 | Real-time notifications via polling/SSE | `RealTimeNotificationController.php`, `RealTimeNotificationService.php` |
| NOTIF-003 | Email (PHPMailer) | `EmailService.php` |
| NOTIF-004 | WhatsApp alerts | `WhatsAppController.php`, `WhatsAppService.php` |
| NOTIF-005 | Telegram alerts | `TelegramService.php` |
| NOTIF-006 | Brevo (Sendinblue) integration | `BrevoIntegrationController.php`, `Services/Integrations/Brevo/` |
| NOTIF-007 | Monitoring alert notifications | `MonitoringAlertNotificationService.php` |
| NOTIF-008 | EAN notifications | `EanNotificationService.php` |
| NOTIF-009 | Slack/Discord notifications (clone) | `CloneSlackDiscordNotificationService.php` |

---

## 14. Monitoramento e Saúde da Conta

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| HEALTH-001 | Account Health — saúde da conta ML | `AccountHealthController.php`, `AccountHealthService.php` |
| HEALTH-002 | Error monitoring — rastreamento e alertas | `ErrorMonitoringController.php`, `ErrorMonitoringService.php` |
| HEALTH-003 | Health endpoint `/health` | `HealthController.php` |
| MON-001 | ML Observability — observabilidade da integração | `MlObservabilityController.php`, `MlObservabilityService.php` |
| MON-002 | Performance metrics | `PerformanceController.php`, `PerformanceMetricsService.php` |
| MON-003 | Advanced Monitoring | `AdvancedMonitoringService.php` |
| MON-004 | Token health monitor | `bin/token-health-monitor.php` |
| MON-005 | ML health check CLI | `bin/ml-health-check.php` |
| MON-006 | AwaSellerAlerts — alertas de análise de vendedores AWA | `AwaSellerAlertService.php` |

---

## 15. Raio X da Conta (X-Ray)

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| XRAY-001 | AccountXRayService — orquestrador do diagnóstico | `AccountXRayService.php` |
| XRAY-002 | MercadoPagoAccountService — saúde financeira MP | `MercadoPagoAccountService.php` |
| XRAY-003 | AccountXRayController — endpoints REST | `AccountXRayController.php` |
| XRAY-004 | Dashboard Raio X (interface completa) | `Views/dashboard/account-xray.php` |
| XRAY-005 | DB Migration para X-Ray | `database/migrations/2026_03_08_account_xray_reports.sql` |
| XRAY-006 | AccountRecoveryApplierService — aplica plano de recuperação | `AccountRecoveryApplierService.php` |
| XRAY-007 | X-Ray Background Worker | `bin/xray-worker.php` |
| XRAY-008 | X-Ray Scheduler Diário | `bin/xray-scheduler.php` |
| XRAY-009 | Apply Recovery Plan API | `POST /api/xray/apply/{id}` |
| XRAY-010 | Async Job Queue API | `POST /api/xray/queue` |
| XRAY-011 | Apply Recovery Plan Dashboard UI | Modal no painel Raio X |
| XRAY-012 | X-Ray PDF Export | `PdfService::generateXRayReport()` |

---

## 16. Governança de Conta

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| GOV-001 | AccountGovernanceService — motor de governança e recuperação | `AccountGovernanceService.php`, `AccountGovernanceController.php` |
| GOV-002 | AccountGovernanceIntegrationService — dados reais ML API | `MercadoLivre/AccountGovernanceIntegrationService.php` |
| GOV-003 | Governance Diagnostic Worker — diagnósticos periódicos | `bin/governance-diagnostic-worker.php` |

---

## 17. Segurança

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| SEC-001 | Input validation e sanitização em todas as rotas | `app/Middleware/`, `SecurityController.php` |
| SEC-002 | Rate limiting global | `RateLimitTrackerService.php`, `Middleware/RateLimitMiddleware.php` |
| SEC-003 | Encryption service — dados sensíveis | `EncryptionService.php` |
| SEC-004 | Audit log — registro de ações sensíveis | `AuditLogService.php`, `AuditService.php` |
| SEC-005 | CSRF protection | `Middleware/CsrfMiddleware.php` |
| SEC-006 | Security headers | `Middleware/SecurityHeadersMiddleware.php` |
| SEC-007 | Security Middleware geral | `Middleware/SecurityMiddleware.php` |
| SEC-008 | GeoIP service — geolocalização por IP | `GeoIPService.php` |
| SEC-009 | Secure token service | `SecureTokenService.php` |
| SEC-010 | Security Helper | `Helpers/SecurityHelper.php` |
| SEC-011 | Export/block de IPs | `bin/export-blocked-ips.php`, `bin/manage-ips.php`, `bin/unblock-ip.php` |
| SEC-AUDIT | Auditoria de segurança ISO 27001/GDPR | `docs/reports/SECURITY_AUDIT_2026-03-29.md` |

---

## 18. Webhooks e Integrações

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| WEBHOOK-001 | Webhooks do ML — pedidos, perguntas, etc. | `MercadoLivreWebhookController.php`, `MercadoLivreWebhookService.php` |
| WEBHOOK-002 | CLAWDBOT webhook (HMAC assinado) | `ClawdbotWebhookController.php`, `ClawdbotWebhookService.php` |
| WEBHOOK-003 | Webhook Inbox — processamento de webhooks genéricos | `WebhookInboxService.php`, `bin/webhook-processor-worker.php` |
| WEBHOOK-004 | Webhook replay (reprocessamento) | `MercadoLivreWebhookReplayService.php` |
| ASSIST-001 | Assistant Connector — API Bearer multi-conta | `AssistantConnectorController.php`, `AssistantConnectorService.php` |
| OPENCLAW | OpenClaw Connector — integração externa | `OpenClawConnectorController.php`, `OpenClawConnectorService.php` |
| MCP | MCP (Model Context Protocol) — bridge ML | `bin/mcp-ml-start.sh`, `bin/mcp-ml-auth.php` |

---

## 19. Publicidade (Ads)

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| ADS-001 | Gestão de Product Ads no ML | `AdsController.php`, `AdsService.php` |
| ADS-002 | ML Ads Advanced — recursos avançados de ads | `MercadoLivre/MLAdsAdvancedService.php` |
| ADS-003 | Ads Wizard | `AdsWizardService.php` |
| ADS-004 | Promotions — gestão de promoções | `PromotionController.php`, `PromotionService.php` |

---

## 20. Marca e Posicionamento

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| BRAND-001 | Brand Central — gestão de marca no ML | `BrandCentralController.php`, `BrandCentralService.php` |
| BRAND-002 | Brand Analyzer — análise de posicionamento (marca AWA) | `BrandAnalyzerController.php`, `BrandAnalyzerService.php` |
| BRAND-003 | Brand Search — busca genérica de anúncios por marca no ML | `BrandSearchController.php`, `MercadoLivre/BrandSearchService.php` |

---

## 21. Multi-Conta

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| MULTI-001 | Gerenciamento de múltiplas contas ML | `MultiAccountController.php` |
| MULTI-002 | Account Sync Service | `AccountSyncService.php` |
| MULTI-003 | Account Context Middleware | `Middleware/AccountContextMiddleware.php` |

---

## 22. Finanças e Faturamento

| Módulo | Serviço | Função |
|--------|---------|--------|
| Comissões | `Financial/FeeCommissionService.php` | Cálculo de comissões ML |
| Lucratividade | `Financial/ProductProfitabilityService.php` | Análise de rentabilidade por produto |
| Reembolsos | `Financial/PaymentRefundService.php` | Gestão de reembolsos |
| Disputas | `Financial/ClaimDisputeService.php` | Disputas financeiras |
| Formas de Pagamento | `Financial/CustomerPaymentMethodService.php` | Meios de pagamento dos clientes |
| Previsão | `Financial/FinancialForecastService.php` | Previsão financeira |
| Pedido Financeiro | `Financial/OrderFinancialService.php` | Financeiro por pedido |
| Reputação | `Financial/SellerReputationService.php` | Reputação do vendedor |
| Assinatura | `Financial/SubscriptionService.php` | Assinaturas/planos do sistema |
| Settlement | `Financial/SettlementReportService.php` | Relatório de liquidação |
| MercadoPago | `MercadoPagoService.php` | Integração com MP |

---

## 23. Marketplaces Alternativos

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| SHOPEE-001 | Integração com Shopee | `ShopeeController.php`, `ShopeeService.php` |
| AMZ-001 | Integração com Amazon | `Services/Marketplace/Amazon/` |
| MKT-001 | Marketplace Factory | `Services/Marketplace/MarketplaceFactory.php` |
| TRENDS | Análise de tendências de mercado | `TrendsController.php`, `TrendsService.php` |
| MARKET-DATA | Dados reais de mercado | `MarketDataController.php`, `RealMarketDataService.php` |

---

## 24. Infraestrutura e Core

### Middleware

| Middleware | Função |
|-----------|--------|
| `AuthMiddleware.php` | Proteção de rotas autenticadas |
| `ApiAuthMiddleware.php` | Autenticação via API key/JWT |
| `AccountContextMiddleware.php` | Contexto de conta ML |
| `CacheMiddleware.php` | Cache de respostas HTTP |
| `CsrfMiddleware.php` | Proteção CSRF |
| `PerformanceMiddleware.php` | Métricas de performance |
| `RateLimitMiddleware.php` | Rate limiting global |
| `SecurityHeadersMiddleware.php` | Headers de segurança |
| `SecurityMiddleware.php` | Middleware de segurança geral |

### Classes Core (app/Core/)

| Classe | Função |
|--------|--------|
| `Config.php` | Gestão de configurações |
| `Container.php` | Injeção de dependência |
| `ErrorHandler.php` | Handler de erros global |
| `EventBus.php` | Barramento de eventos |
| `ExceptionHandler.php` | Handler de exceções |
| `QueryBuilder.php` | Query Builder fluente |
| `Request.php` | Objeto de request HTTP |
| `Validator.php` | Validação de dados |
| `Pipeline.php` | Pipeline de middlewares |
| `Paginator.php` | Paginação de resultados |
| `Flash.php` | Mensagens flash |
| `Collection.php` | Coleções tipadas |

### Helpers (app/Helpers/)

| Helper | Função |
|--------|--------|
| `CacheHelper.php` | Helpers de cache |
| `EnvValidator.php` | Validação de variáveis de ambiente |
| `Functions.php` | Funções utilitárias globais |
| `Log.php` / `LogHelper.php` | Logging simplificado |
| `MLStatisticsHelper.php` | Estatísticas ML |
| `MercadoLivreHelper.php` | Helpers ML |
| `ResponseHelper.php` | Helpers de resposta HTTP |
| `SecurityHelper.php` | Helpers de segurança |
| `SessionHelper.php` | Helpers de sessão |
| `ViewHelper.php` | Helpers de view |

### Cache e Performance

| ID | Funcionalidade | Arquivos Principais |
|----|---------------|---------------------|
| CACHE-001 | Cache Redis — invalidação, warmup, gestão | `CacheService.php`, `AdvancedRedisCacheService.php`, `CacheController.php` |
| CACHE-002 | Advanced Redis Cache | `AdvancedRedisCacheService.php` |
| CACHE-003 | Cache Manager | `CacheManagerService.php` |
| CACHE-004 | Query Optimizer | `QueryOptimizerService.php` |
| CACHE-005 | Lazy Load Service | `LazyLoadService.php` |
| CACHE-006 | Feature Flags | `FeatureFlagService.php` |

### Auditoria e Logging

| Serviço | Função |
|---------|--------|
| `CentralizedLogService.php` | Log centralizado |
| `StructuredLogService.php` | Log estruturado JSON |
| `LoggingService.php` | Service de logging |
| `LogService.php` / `LoggerService.php` | Wrappers de Monolog |
| `AuditLogService.php` | Log de ações de usuário |
| `AuditService.php` | Auditoria geral |

---

## 25. Workers e Jobs em Background

### Workers com Cron

| Worker | Frequência | Função |
|--------|-----------|--------|
| `auto-token-refresh-worker.php` | A cada 5 min | Refresh automático de tokens OAuth |
| `bulk-seo-worker.php` | Sob demanda | Otimização SEO em lote |
| `catalog-clone-worker.php` | Sob demanda | Clonagem de catálogo |
| `clone-sync-worker.php` | A cada 30 min | Sincronização de clones |
| `clone-automation-worker.php` | Triggers | Automação de clones |
| `clone-ab-testing-worker.php` | Sob demanda | A/B testing de clones |
| `clone-scheduler-worker.php` | Cron | Agendador de clones |
| `clone-health-monitor.php` | A cada hora | Saúde dos clones |
| `competitor-monitor-worker.php` | A cada hora | Monitor de concorrentes |
| `orders-sync-worker.php` | A cada 15 min | Sincronização de pedidos |
| `questions-sync-worker.php` | A cada 10 min | Sincronização de perguntas |
| `stock-sync-worker.php` | A cada 30 min | Sincronização de estoque |
| `shipments-sync-worker.php` | A cada 30 min | Sincronização de envios |
| `pricing-worker.php` | A cada 30 min | Atualização de preços |
| `auto-pricing-optimizer.php` | Diário | Otimização automática de preços |
| `rules-engine-worker.php` | A cada 15 min | Regras de precificação |
| `scheduled-price-worker.php` | A cada 5 min | Preços agendados |
| `seo-worker.php` | Diário | Worker SEO geral |
| `seo-metrics-worker.php` | Diário | Métricas SEO |
| `seo-performance-worker.php` | Diário | Performance SEO |
| `ai-worker.php` | Contínuo | Worker de IA |
| `ml-ai-optimization-worker.php` | Sob demanda | Otimização ML-AI |
| `xray-worker.php` | Sob demanda / diário | Análise Raio X |
| `xray-scheduler.php` | Diário 02h | Scheduler X-Ray |
| `governance-diagnostic-worker.php` | Diário | Diagnóstico de governança |
| `error-monitor.php` | A cada 5 min | Monitor de erros |
| `tech-sheet-scheduler.php` | Cron | Scheduler de fichas |
| `tech-sheet-auto-optimizer.php` | Diário | Auto-otimizador |
| `tech-sheet-daily-report.php` | Diário | Relatório de fichas |
| `automated-reports-worker.php` | Semanal | Relatórios automáticos |
| `weekly-report.php` | Semanal | Relatório semanal |
| `performance-monitor.php` | A cada hora | Monitor de performance |
| `token-health-monitor.php` | A cada hora | Saúde de tokens |
| `ml-health-check.php` | Diário 06h | Health check ML |
| `ean-payment-reconcile-worker.php` | Diário | Conciliação EAN |
| `awa-sellers-scan-worker.php` | Semanal | Scan de vendedores AWA |
| `items-sync-worker.php` | Sob demanda | Sincronização de itens |
| `webhook-processor-worker.php` | Contínuo | Processamento de webhooks |
| `clone-event-trigger-worker.php` | Sob demanda | Triggers de eventos de clone |
| `clone-post-actions-worker.php` | Sob demanda | Pós-ações de clone |
| `clone-roi-sync-worker.php` | Diário | Sync ROI de clones |
| `clone-seller-recommendations-worker.php` | Semanal | Recomendações de clones |
| `ab-test-worker.php` | Sob demanda | Worker de A/B testing |
| `clone-alert-monitor.php` | A cada 30 min | Monitor de alertas de clone |

### Jobs (app/Jobs/)

| Job | Função |
|-----|--------|
| `AIOptimizationWorker.php` | Worker de otimização IA |
| `AgentJob.php` | Job de agente autônomo |
| `AutoAnswerJob.php` | Resposta automática a perguntas |
| `SEOMonitoringJob.php` | Job de monitoramento SEO |
| `TokenRefreshJob.php` | Job de refresh de token |

---

## 26. Testes e Qualidade

| Tipo | Descrição | Ferramentas |
|------|-----------|-------------|
| Testes unitários | 2923 testes em `tests/Unit/` | PHPUnit 9 + Faker |
| Testes E2E | Validação de produção — 81 passed, 0 failed | Playwright + TypeScript |
| Lint PHP | Verificação de sintaxe | `php -l arquivo.php` |
| Code Standards | PSR-12 | `phpcs.xml` |
| Code Quality | Análise com Codacy CLI (15+ engines) | phpcs, phpmd, semgrep, trivy |
| Security Scan | Análise de vulnerabilidades | trivy (OWASP Top 10) |

### Comandos de Verificação

```bash
# Todos os testes unitários
php vendor/bin/phpunit

# Suite unitária
php vendor/bin/phpunit --testsuite=Unit

# Filtro
php vendor/bin/phpunit --filter NomeDoTest

# E2E produção
./run-prod-validation.sh <email> <password>

# Health check CLI
php bin/ml-health-check.php

# Status do sistema
./bin/ai.sh status
```

---

## Resumo Geral

| Categoria | Qtd. Módulos/Features |
|-----------|----------------------|
| Autenticação | 9 |
| Dashboard | 5 |
| SEO e Otimização | 32 |
| Clonagem de Catálogo | 23 |
| Precificação Dinâmica | 14 |
| Inteligência Artificial | 30+ |
| Análise de Concorrentes | 5 |
| Gestão de Anúncios | 13 |
| Gestão de Estoque | 4 |
| Pedidos e Pós-Venda | 8 |
| Frete e Logística | 6 |
| Relatórios e Exportação | 9 |
| Notificações e Alertas | 9 |
| Monitoramento | 6 |
| Raio X da Conta | 12 |
| Governança | 3 |
| Segurança | 12 |
| Webhooks e Integrações | 7 |
| Publicidade | 4 |
| Marca | 2 |
| Multi-Conta | 3 |
| Finanças | 11 |
| Marketplaces Alternativos | 0 (Amazon/Shopee removidos — `.tmp/`) |
| Infraestrutura e Core | 40+ |
| Workers e Jobs | 45+ |
| Testes e Qualidade | 4 tipos |
| **TOTAL estimado** | **~370+ funcionalidades** |
