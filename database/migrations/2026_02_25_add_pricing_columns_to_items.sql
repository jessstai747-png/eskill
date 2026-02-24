-- Migration: Adicionar colunas de precificação e custo à tabela items
-- Data: 2026-02-25
-- Contexto: Código em ItemService, SEOMonitoringJob, SniperAgent, ItemController,
--           AdvancedReportController, DashboardApiController, QualityController,
--           StatisticsService, AdsWizardService, AIChatbotService
--           referencia colunas que nunca foram criadas na migração original 008

-- Coluna SKU (usada em saveItemToDatabase)
ALTER TABLE items ADD COLUMN IF NOT EXISTS sku VARCHAR(100) NULL AFTER catalog_product_id;

-- Coluna sold_quantity (usada em AdvancedReportController, DashboardApiController, QualityController, GuardianAgent)
ALTER TABLE items ADD COLUMN IF NOT EXISTS sold_quantity INT NOT NULL DEFAULT 0 AFTER available_quantity;

-- Coluna thumbnail (usada em StatisticsService, AdsWizardService, AIChatbotService, AIOptimizationController)
ALTER TABLE items ADD COLUMN IF NOT EXISTS thumbnail VARCHAR(500) NULL AFTER sku;

-- Coluna permalink (usada em Views e ItemService)
ALTER TABLE items ADD COLUMN IF NOT EXISTS permalink VARCHAR(500) NULL AFTER thumbnail;

-- Colunas de custo (usadas em updateItemCost e getItem)
ALTER TABLE items ADD COLUMN IF NOT EXISTS cost_price DECIMAL(12,2) NULL AFTER data;
ALTER TABLE items ADD COLUMN IF NOT EXISTS tax_rate DECIMAL(5,2) NULL AFTER cost_price;

-- Colunas de pricing/reprificação (usadas em updateItemPricing, SEOMonitoringJob, SniperAgent)
ALTER TABLE items ADD COLUMN IF NOT EXISTS pricing_strategy VARCHAR(50) NULL AFTER tax_rate;
ALTER TABLE items ADD COLUMN IF NOT EXISTS min_price DECIMAL(12,2) NULL AFTER pricing_strategy;
ALTER TABLE items ADD COLUMN IF NOT EXISTS max_price DECIMAL(12,2) NULL AFTER min_price;
ALTER TABLE items ADD COLUMN IF NOT EXISTS auto_reprice TINYINT(1) NOT NULL DEFAULT 0 AFTER max_price;
ALTER TABLE items ADD COLUMN IF NOT EXISTS auto_negotiate TINYINT(1) NOT NULL DEFAULT 0 AFTER auto_reprice;

-- Índices para queries frequentes
ALTER TABLE items ADD INDEX IF NOT EXISTS idx_auto_reprice (auto_reprice);
ALTER TABLE items ADD INDEX IF NOT EXISTS idx_sku (sku);
ALTER TABLE items ADD INDEX IF NOT EXISTS idx_sold_quantity (sold_quantity);
