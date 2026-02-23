-- Migration: Database Performance Optimization
-- Criado em: 2025-12-25
-- Descrição: Adiciona índices para otimizar queries mais frequentes

-- Índices para ml_orders
ALTER TABLE `ml_orders` 
ADD INDEX IF NOT EXISTS `idx_date_created` (`date_created`),
ADD INDEX IF NOT EXISTS `idx_status_date` (`status`, `date_created`),
ADD INDEX IF NOT EXISTS `idx_account_buyer` (`account_id`, `buyer_id`);

-- Índices para items
ALTER TABLE `items` 
ADD INDEX IF NOT EXISTS `idx_status_account` (`status`, `account_id`),
ADD INDEX IF NOT EXISTS `idx_category_account` (`category_id`, `account_id`),
ADD INDEX IF NOT EXISTS `idx_price` (`price`),
ADD INDEX IF NOT EXISTS `idx_available_quantity` (`available_quantity`),
ADD INDEX IF NOT EXISTS `idx_sold_quantity` (`sold_quantity`);

-- Índices para questions
ALTER TABLE `questions` 
ADD INDEX IF NOT EXISTS `idx_status_date` (`status`, `date_created`),
ADD INDEX IF NOT EXISTS `idx_seller_status` (`seller_id`, `status`),
ADD INDEX IF NOT EXISTS `idx_item_id` (`item_id`);

-- Índices para ml_accounts
ALTER TABLE `ml_accounts` 
ADD INDEX IF NOT EXISTS `idx_active_expires` (`is_active`, `token_expires_at`),
ADD INDEX IF NOT EXISTS `idx_ml_user_id` (`ml_user_id`);

-- Índices para ai_audit_log (se existir)
-- ALTER TABLE `ai_audit_log` 
-- ADD INDEX IF NOT EXISTS `idx_account_item` (`account_id`, `item_id`),
-- ADD INDEX IF NOT EXISTS `idx_created_at` (`created_at`),
-- ADD INDEX IF NOT EXISTS `idx_score_before` (`score_before`),
-- ADD INDEX IF NOT EXISTS `idx_score_after` (`score_after`);

-- Índices para ai_optimization_queue (se existir)
-- ALTER TABLE `ai_optimization_queue` 
-- ADD INDEX IF NOT EXISTS `idx_status_priority` (`status`, `priority`),
-- ADD INDEX IF NOT EXISTS `idx_created_at` (`created_at`),
-- ADD INDEX IF NOT EXISTS `idx_account_item` (`account_id`, `item_id`);

-- Otimizar tabelas após criar índices
OPTIMIZE TABLE `ml_orders`;
OPTIMIZE TABLE `items`;
OPTIMIZE TABLE `questions`;
OPTIMIZE TABLE `ml_accounts`;
