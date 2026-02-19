# 🎯 PLANO DE LIMPEZA - SISTEMA SEO ONLY

## Objetivo
Transformar o sistema atual removendo todos os módulos não relacionados a SEO, mantendo apenas funcionalidades de otimização de conteúdo, análise SEO e geração de títulos/descrições.

---

## 📊 ANÁLISE DO SISTEMA ATUAL

### ✅ MÓDULOS SEO - MANTER

**Serviços Core de SEO**:
```
✅ app/Services/SeoService.php
✅ app/Services/SeoAnalyzerService.php
✅ app/Services/AISEOOptimizerService.php
✅ app/Services/TitleOptimizerService.php
✅ app/Services/AIContentGeneratorService.php
✅ app/Services/CompetitorAnalysisService.php
✅ app/Services/AI/Analyzers/KeywordResearchService.php
✅ app/Services/AI/Analyzers/CompetitiveAnalysisService.php
✅ app/Services/AI/Optimizers/ImageOptimizer.php
✅ app/Services/AI/Core/AIOptimizationEngine.php
```

**Serviços de Suporte (necessários para SEO)**:
```
✅ app/Services/UnifiedAIService.php
✅ app/Services/AI/Providers/OpenAIProvider.php
✅ app/Services/AI/Providers/ClaudeProvider.php
✅ app/Services/AI/Providers/GeminiProvider.php
✅ app/Services/AI/Core/PromptBuilder.php
✅ app/Services/AI/Core/ValidationService.php
✅ app/Services/AI/Core/RetryService.php
✅ app/Services/AI/Core/RateLimiterService.php
✅ app/Services/AI/Utils/CacheManager.php
✅ app/Services/CacheService.php
✅ app/Services/LoggingService.php
✅ app/Services/EncryptionService.php
```

**Serviços de Busca e Análise**:
```
✅ app/Services/SearchService.php
✅ app/Services/AlternativeSearchService.php
✅ app/Services/DeepResearchService.php
✅ app/Services/CategoryService.php
✅ app/Services/BrandAnalyzerService.php
```

---

### ❌ MÓDULOS PARA REMOVER

#### 1. **Mercado Livre Integration** (completo)
```
❌ app/Services/MercadoLivreAuthService.php
❌ app/Services/MercadoLivreService.php
❌ app/Services/MercadoLivreApiLogService.php
❌ app/Services/MercadoLivreClient.php
❌ app/Services/MercadoLivreWebhookService.php
❌ app/Services/MercadoLivreAccountService.php
❌ app/Services/MercadoLivreItemService.php
❌ app/Services/MercadoLivreOrderService.php
❌ app/Services/MercadoLivreMessagingService.php
❌ app/Jobs/MercadoLivreSyncJob.php
❌ bin/sync-ml.php
❌ bin/test-ml-integration.php
❌ database/migrations/100_create_ml_messages_table.sql
❌ database/migrations/101_create_ml_webhooks_and_logs_table.sql
❌ public/webhook-ml.php
❌ MERCADOLIVRE_INTEGRATION.md
❌ INTEGRACAO_MERCADOLIVRE_SUMARIO.md
```

#### 2. **Video Creation System** (completo)
```
❌ app/Services/VideoCreation/ (todo diretório)
   ├── ProjectSpec.php
   ├── Jobs/VideoJob.php
   ├── Audio/TTSService.php
   ├── Subtitles/SubtitleGenerator.php
   ├── Timeline/TimelineBuilder.php
   ├── Assets/AssetProvider.php
   ├── Render/FFmpegRenderer.php
   └── Pipeline/VideoPipeline.php
❌ bin/create-video.php
❌ bin/test-video-creation.php
❌ VIDEO_CREATION_SYSTEM.md
❌ VIDEO_CREATION_SUMARIO.md
❌ VIDEO_CREATION_SYSTEM_PRODUCT_BACKLOG.md
```

#### 3. **Módulos de E-commerce não-SEO**
```
❌ app/Services/OrderService.php (pedidos)
❌ app/Services/ReturnService.php (devoluções)
❌ app/Services/ClaimsService.php (reclamações)
❌ app/Services/MessageService.php (mensagens)
❌ app/Services/FinancialService.php (financeiro)
❌ app/Services/RepricingService.php (repricing)
❌ app/Services/PriceHistoryService.php (histórico de preços)
❌ app/Services/PricingStrategyService.php (estratégia de preços)
❌ app/Services/MercadoPagoService.php (pagamentos)
```

#### 4. **Módulos de Integração EAN**
```
❌ app/Services/EanService.php
❌ app/Services/EanReportService.php
❌ app/Services/EanNotificationService.php
❌ app/Services/EanIntegrationService.php
```

#### 5. **Módulos de Monitoramento de Sistema**
```
❌ app/Services/AdvancedHealthCheckService.php
❌ app/Services/PerformanceMetricsService.php
❌ app/Services/PerformanceMonitoringService.php
❌ app/Services/ErrorTrackingService.php
❌ app/Services/AdvancedAnalyticsService.php
```

#### 6. **Módulos de Notificação e Comunicação**
```
❌ app/Services/TelegramService.php
❌ app/Services/RealTimeNotificationService.php
❌ app/Services/NotificationBroadcaster.php
❌ app/Services/EmailSchedulerService.php
```

#### 7. **Outros Módulos Não-SEO**
```
❌ app/Services/FlexService.php
❌ app/Services/GapHunterService.php
❌ app/Services/ListingBuilderService.php
❌ app/Services/ExportService.php
❌ app/Services/PollingService.php
❌ app/Services/ProxyService.php
❌ app/Services/WebhookProcessorService.php
❌ app/Services/CatalogCloneMonitoringService.php
❌ app/Services/CompetitorMonitoringService.php
❌ app/Services/AutomationOrchestratorService.php
❌ app/Services/AutonomousAgentService.php
❌ app/Services/Agent/ (todo diretório)
```

---

## 🗂️ ESTRUTURA FINAL (SEO ONLY)

```
/home/eskill/htdocs/eskill.com.br/
├── app/
│   ├── Services/
│   │   ├── SEO/                              # Organizar serviços SEO
│   │   │   ├── SeoService.php
│   │   │   ├── SeoAnalyzerService.php
│   │   │   ├── AISEOOptimizerService.php
│   │   │   ├── TitleOptimizerService.php
│   │   │   └── AIContentGeneratorService.php
│   │   │
│   │   ├── Analysis/                         # Análise e pesquisa
│   │   │   ├── CompetitorAnalysisService.php
│   │   │   ├── DeepResearchService.php
│   │   │   ├── SearchService.php
│   │   │   ├── AlternativeSearchService.php
│   │   │   ├── CategoryService.php
│   │   │   └── BrandAnalyzerService.php
│   │   │
│   │   ├── AI/                               # Mantido
│   │   │   ├── Providers/
│   │   │   │   ├── OpenAIProvider.php
│   │   │   │   ├── ClaudeProvider.php
│   │   │   │   └── GeminiProvider.php
│   │   │   ├── Core/
│   │   │   │   ├── AIOptimizationEngine.php
│   │   │   │   ├── PromptBuilder.php
│   │   │   │   ├── ValidationService.php
│   │   │   │   ├── RetryService.php
│   │   │   │   └── RateLimiterService.php
│   │   │   ├── Analyzers/
│   │   │   │   ├── KeywordResearchService.php
│   │   │   │   └── CompetitiveAnalysisService.php
│   │   │   ├── Optimizers/
│   │   │   │   └── ImageOptimizer.php
│   │   │   └── Utils/
│   │   │       └── CacheManager.php
│   │   │
│   │   ├── Core/                             # Serviços core necessários
│   │   │   ├── UnifiedAIService.php
│   │   │   ├── CacheService.php
│   │   │   ├── LoggingService.php
│   │   │   ├── EncryptionService.php
│   │   │   ├── UserService.php
│   │   │   └── SettingsService.php
│   │   │
│   │   └── Security/                         # Segurança
│   │       ├── SecurityService.php
│   │       ├── TwoFactorService.php
│   │       ├── SecureTokenService.php
│   │       └── PasswordResetService.php
│   │
│   └── Controllers/
│       └── (apenas controllers SEO)
│
├── bin/
│   ├── seo-analyzer.php                      # Nova CLI SEO
│   └── test-seo-system.php                   # Testes SEO
│
├── database/
│   └── migrations/
│       └── (apenas migrations SEO)
│
├── storage/
│   ├── cache/
│   │   └── seo/                              # Cache SEO
│   └── logs/
│       └── seo.log
│
└── docs/
    ├── SEO_SYSTEM.md                         # Nova doc
    ├── SEO_API_REFERENCE.md
    └── README_SEO.md
```

---

## 📝 PLANO DE EXECUÇÃO

### Fase 1: Backup 🔒
```bash
# Criar backup completo antes de remover
cd /home/eskill/htdocs/eskill.com.br
tar -czf backup_pre_seo_cleanup_$(date +%Y%m%d).tar.gz \
  app/Services/MercadoLivre*.php \
  app/Services/VideoCreation/ \
  app/Jobs/MercadoLivreSyncJob.php \
  bin/sync-ml.php \
  bin/test-ml-integration.php \
  bin/create-video.php \
  bin/test-video-creation.php \
  database/migrations/100_create_ml_messages_table.sql \
  database/migrations/101_create_ml_webhooks_and_logs_table.sql \
  public/webhook-ml.php \
  MERCADOLIVRE_INTEGRATION.md \
  INTEGRACAO_MERCADOLIVRE_SUMARIO.md \
  VIDEO_CREATION_SYSTEM.md \
  VIDEO_CREATION_SUMARIO.md \
  VIDEO_CREATION_SYSTEM_PRODUCT_BACKLOG.md
```

### Fase 2: Remoção Segura 🗑️

#### 2.1 Remover Mercado Livre
```bash
rm -f app/Services/MercadoLivre*.php
rm -f app/Jobs/MercadoLivreSyncJob.php
rm -f bin/sync-ml.php
rm -f bin/test-ml-integration.php
rm -f database/migrations/100_create_ml_messages_table.sql
rm -f database/migrations/101_create_ml_webhooks_and_logs_table.sql
rm -f public/webhook-ml.php
rm -f MERCADOLIVRE_INTEGRATION.md
rm -f INTEGRACAO_MERCADOLIVRE_SUMARIO.md
rm -f cron-mercadolivre.txt
```

#### 2.2 Remover Video Creation
```bash
rm -rf app/Services/VideoCreation/
rm -f bin/create-video.php
rm -f bin/test-video-creation.php
rm -f VIDEO_CREATION_SYSTEM.md
rm -f VIDEO_CREATION_SUMARIO.md
rm -f VIDEO_CREATION_SYSTEM_PRODUCT_BACKLOG.md
```

#### 2.3 Remover Módulos Não-SEO
```bash
# E-commerce
rm -f app/Services/OrderService.php
rm -f app/Services/ReturnService.php
rm -f app/Services/ClaimsService.php
rm -f app/Services/MessageService.php
rm -f app/Services/FinancialService.php
rm -f app/Services/RepricingService.php
rm -f app/Services/PriceHistoryService.php
rm -f app/Services/PricingStrategyService.php
rm -f app/Services/MercadoPagoService.php

# EAN
rm -f app/Services/Ean*.php

# Monitoramento
rm -f app/Services/AdvancedHealthCheckService.php
rm -f app/Services/PerformanceMetricsService.php
rm -f app/Services/PerformanceMonitoringService.php
rm -f app/Services/ErrorTrackingService.php
rm -f app/Services/AdvancedAnalyticsService.php

# Notificações
rm -f app/Services/TelegramService.php
rm -f app/Services/RealTimeNotificationService.php
rm -f app/Services/NotificationBroadcaster.php
rm -f app/Services/EmailSchedulerService.php

# Outros
rm -f app/Services/FlexService.php
rm -f app/Services/GapHunterService.php
rm -f app/Services/ListingBuilderService.php
rm -f app/Services/ExportService.php
rm -f app/Services/PollingService.php
rm -f app/Services/ProxyService.php
rm -f app/Services/WebhookProcessorService.php
rm -f app/Services/CatalogCloneMonitoringService.php
rm -f app/Services/CompetitorMonitoringService.php
rm -f app/Services/AutomationOrchestratorService.php
rm -f app/Services/AutonomousAgentService.php
rm -rf app/Services/Agent/
```

### Fase 3: Reorganização 📂
```bash
# Criar nova estrutura de diretórios
mkdir -p app/Services/SEO
mkdir -p app/Services/Analysis
mkdir -p app/Services/Core
mkdir -p app/Services/Security

# Mover serviços SEO
mv app/Services/SeoService.php app/Services/SEO/
mv app/Services/SeoAnalyzerService.php app/Services/SEO/
mv app/Services/AISEOOptimizerService.php app/Services/SEO/
mv app/Services/TitleOptimizerService.php app/Services/SEO/
mv app/Services/AIContentGeneratorService.php app/Services/SEO/

# Mover serviços de análise
mv app/Services/CompetitorAnalysisService.php app/Services/Analysis/
mv app/Services/DeepResearchService.php app/Services/Analysis/
mv app/Services/SearchService.php app/Services/Analysis/
mv app/Services/AlternativeSearchService.php app/Services/Analysis/
mv app/Services/CategoryService.php app/Services/Analysis/
mv app/Services/BrandAnalyzerService.php app/Services/Analysis/

# Mover serviços core
mv app/Services/UnifiedAIService.php app/Services/Core/
mv app/Services/CacheService.php app/Services/Core/
mv app/Services/LoggingService.php app/Services/Core/
mv app/Services/EncryptionService.php app/Services/Core/
mv app/Services/UserService.php app/Services/Core/
mv app/Services/SettingsService.php app/Services/Core/

# Mover serviços de segurança
mv app/Services/SecurityService.php app/Services/Security/
mv app/Services/TwoFactorService.php app/Services/Security/
mv app/Services/SecureTokenService.php app/Services/Security/
mv app/Services/PasswordResetService.php app/Services/Security/
```

### Fase 4: Criar CLI SEO 🛠️
```bash
# Criar nova CLI focada em SEO
touch bin/seo-analyzer.php
touch bin/test-seo-system.php
chmod +x bin/seo-analyzer.php
chmod +x bin/test-seo-system.php
```

### Fase 5: Documentação 📚
```bash
# Remover docs antigas
rm -f README_SYSTEMS.md

# Criar novas docs
touch docs/SEO_SYSTEM.md
touch docs/SEO_API_REFERENCE.md
touch docs/README_SEO.md
```

### Fase 6: Limpeza Final 🧹
```bash
# Limpar cache
rm -rf storage/temp/job_*
rm -rf storage/cache/tts/
rm -rf storage/assets/

# Limpar logs não relacionados
rm -f storage/logs/mercadolivre.log
rm -f storage/logs/video_creation.log
```

---

## ✅ CHECKLIST DE VALIDAÇÃO

Após execução do plano:

- [ ] Backup completo criado
- [ ] Módulos Mercado Livre removidos
- [ ] Módulos Video Creation removidos
- [ ] Módulos não-SEO removidos
- [ ] Estrutura de diretórios reorganizada
- [ ] Namespaces atualizados nos arquivos movidos
- [ ] CLI SEO criada
- [ ] Testes SEO funcionando
- [ ] Documentação atualizada
- [ ] README_SEO.md criado
- [ ] Sistema funcional apenas com módulos SEO
- [ ] Cache e logs limpos

---

## 🎯 RESULTADO ESPERADO

Sistema limpo e focado exclusivamente em:

1. **Otimização SEO de Títulos e Descrições**
2. **Análise de Palavras-chave**
3. **Pesquisa de Concorrentes**
4. **Geração de Conteúdo com IA**
5. **Análise de Categorias e Marcas**
6. **Otimização de Imagens**
7. **Deep Research**
8. **Cache e Performance**

**Redução estimada**: ~70% do código removido
**Foco**: 100% SEO

---

## ⚠️ AVISOS IMPORTANTES

1. **Backup obrigatório** antes de qualquer remoção
2. **Testar após cada fase** de remoção
3. **Atualizar namespaces** após reorganização
4. **Verificar dependências** entre serviços
5. **Manter versionamento** (git commit após cada fase)

---

**Documento criado**: 08/01/2026
**Objetivo**: Sistema SEO-Only focado e otimizado
