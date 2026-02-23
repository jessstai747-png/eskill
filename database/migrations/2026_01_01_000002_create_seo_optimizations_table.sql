-- =====================================================
-- Migration: Create seo_optimizations table
-- Description: Tabela para rastrear otimizações SEO feitas pela IA
-- Date: 2026-01-01
-- =====================================================

CREATE TABLE IF NOT EXISTS `seo_optimizations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `account_id` INT UNSIGNED NOT NULL,
    `item_id` VARCHAR(50) NOT NULL COMMENT 'ID do item no Mercado Livre (MLB123456)',
    `optimization_type` ENUM('title', 'description', 'attributes', 'images', 'price', 'shipping', 'full') DEFAULT 'full',
    `score_before` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Score SEO antes da otimização',
    `score_after` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Score SEO após otimização',
    `score_improvement` DECIMAL(5,2) GENERATED ALWAYS AS (score_after - score_before) STORED COMMENT 'Melhoria no score',
    `views_before` INT UNSIGNED DEFAULT 0,
    `views_after` INT UNSIGNED DEFAULT 0,
    `views_increase` INT GENERATED ALWAYS AS (views_after - views_before) STORED,
    `sales_before` INT UNSIGNED DEFAULT 0,
    `sales_after` INT UNSIGNED DEFAULT 0,
    `sales_increase` INT GENERATED ALWAYS AS (sales_after - sales_before) STORED,
    `changes_applied` JSON COMMENT 'Detalhes das mudanças aplicadas',
    `ai_suggestions` JSON COMMENT 'Sugestões da IA',
    `status` ENUM('pending', 'applied', 'reverted', 'failed') DEFAULT 'pending',
    `applied_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_account_id` (`account_id`),
    INDEX `idx_item_id` (`item_id`),
    INDEX `idx_optimization_type` (`optimization_type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_account_created` (`account_id`, `created_at`),
    INDEX `idx_account_type_created` (`account_id`, `optimization_type`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabela de alertas de concorrentes (se não existir)
-- =====================================================
CREATE TABLE IF NOT EXISTS `competitor_alerts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `account_id` INT UNSIGNED NOT NULL,
    `competitor_id` VARCHAR(100),
    `alert_type` ENUM('price_drop', 'price_increase', 'stock_out', 'new_listing', 'promotion', 'other') DEFAULT 'other',
    `item_id` VARCHAR(50),
    `message` TEXT,
    `data` JSON,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_account_read` (`account_id`, `is_read`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Inserir dados de exemplo para demonstração
-- =====================================================
INSERT INTO `seo_optimizations` (`account_id`, `item_id`, `optimization_type`, `score_before`, `score_after`, `views_before`, `views_after`, `sales_before`, `sales_after`, `status`, `applied_at`, `created_at`) VALUES
(1, 'MLB123456789', 'title', 45.00, 72.00, 100, 185, 5, 12, 'applied', NOW(), DATE_SUB(NOW(), INTERVAL 25 DAY)),
(1, 'MLB123456790', 'description', 52.00, 78.00, 80, 150, 3, 8, 'applied', NOW(), DATE_SUB(NOW(), INTERVAL 22 DAY)),
(1, 'MLB123456791', 'full', 38.00, 85.00, 120, 340, 8, 22, 'applied', NOW(), DATE_SUB(NOW(), INTERVAL 20 DAY)),
(1, 'MLB123456792', 'images', 55.00, 70.00, 90, 130, 4, 7, 'applied', NOW(), DATE_SUB(NOW(), INTERVAL 18 DAY)),
(1, 'MLB123456793', 'price', 60.00, 68.00, 200, 280, 15, 23, 'applied', NOW(), DATE_SUB(NOW(), INTERVAL 15 DAY)),
(1, 'MLB123456794', 'title', 42.00, 75.00, 70, 160, 2, 9, 'applied', NOW(), DATE_SUB(NOW(), INTERVAL 12 DAY)),
(1, 'MLB123456795', 'full', 35.00, 82.00, 50, 190, 1, 11, 'applied', NOW(), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(1, 'MLB123456796', 'attributes', 48.00, 65.00, 110, 145, 6, 10, 'applied', NOW(), DATE_SUB(NOW(), INTERVAL 8 DAY)),
(1, 'MLB123456797', 'description', 50.00, 73.00, 95, 160, 4, 9, 'applied', NOW(), DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 'MLB123456798', 'full', 40.00, 88.00, 75, 220, 3, 14, 'applied', NOW(), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1, 'MLB123456799', 'title', 55.00, 80.00, 130, 200, 7, 12, 'applied', NOW(), DATE_SUB(NOW(), INTERVAL 1 DAY));
