-- Migration: Database Performance Optimization
-- Criado em: 2025-12-25
-- Descrição: Adiciona índices para otimizar queries mais frequentes
--
-- Nota (correção): as ALTER TABLE originais assumiam colunas/tabelas que só
-- passaram a existir em migrations posteriores na ordenação alfabética do
-- runner (items.sold_quantity é criada em 2026_02_25_add_pricing_columns_to_items.sql,
-- que já cria seu próprio índice; a tabela `questions` nunca chegou a existir,
-- apenas `ml_questions`; e `ml_accounts` nunca teve coluna `is_active`, apenas
-- `status` ENUM). Por isso cada ALTER agora é guardado por verificação em
-- INFORMATION_SCHEMA, seguindo o mesmo padrão usado em
-- 2026_02_25_reconcile_pricing_rules.sql.

DELIMITER //

DROP PROCEDURE IF EXISTS run_026_performance_optimization_indexes //

CREATE PROCEDURE run_026_performance_optimization_indexes()
BEGIN
    -- Índices para ml_orders
    IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders') THEN
        IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND INDEX_NAME = 'idx_date_created') THEN
            ALTER TABLE `ml_orders` ADD INDEX `idx_date_created` (`date_created`);
        END IF;
        IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND INDEX_NAME = 'idx_status_date') THEN
            ALTER TABLE `ml_orders` ADD INDEX `idx_status_date` (`status`, `date_created`);
        END IF;
        -- account_id/buyer_id só existem a partir de 2026_02_15_expand_ml_orders_table.sql
        IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND COLUMN_NAME = 'account_id')
           AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND COLUMN_NAME = 'buyer_id')
           AND NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND INDEX_NAME = 'idx_account_buyer') THEN
            ALTER TABLE `ml_orders` ADD INDEX `idx_account_buyer` (`account_id`, `buyer_id`);
        END IF;
    END IF;

    -- Índices para items (sold_quantity só existe a partir de 2026_02_25_add_pricing_columns_to_items.sql)
    IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items') THEN
        IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND INDEX_NAME = 'idx_status_account') THEN
            ALTER TABLE `items` ADD INDEX `idx_status_account` (`status`, `account_id`);
        END IF;
        IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND INDEX_NAME = 'idx_category_account') THEN
            ALTER TABLE `items` ADD INDEX `idx_category_account` (`category_id`, `account_id`);
        END IF;
        IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND INDEX_NAME = 'idx_price') THEN
            ALTER TABLE `items` ADD INDEX `idx_price` (`price`);
        END IF;
        IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND INDEX_NAME = 'idx_available_quantity') THEN
            ALTER TABLE `items` ADD INDEX `idx_available_quantity` (`available_quantity`);
        END IF;
        IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND COLUMN_NAME = 'sold_quantity')
           AND NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND INDEX_NAME = 'idx_sold_quantity') THEN
            ALTER TABLE `items` ADD INDEX `idx_sold_quantity` (`sold_quantity`);
        END IF;
    END IF;

    -- Índices para questions (tabela nunca foi criada neste projeto; guarda por segurança)
    IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions') THEN
        IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions' AND INDEX_NAME = 'idx_status_date') THEN
            ALTER TABLE `questions` ADD INDEX `idx_status_date` (`status`, `date_created`);
        END IF;
        IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions' AND INDEX_NAME = 'idx_seller_status') THEN
            ALTER TABLE `questions` ADD INDEX `idx_seller_status` (`seller_id`, `status`);
        END IF;
        IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions' AND INDEX_NAME = 'idx_item_id') THEN
            ALTER TABLE `questions` ADD INDEX `idx_item_id` (`item_id`);
        END IF;
    END IF;

    -- Índices para ml_accounts (is_active nunca existiu; a coluna real é `status`)
    IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_accounts') THEN
        IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_accounts' AND COLUMN_NAME = 'is_active')
           AND NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_accounts' AND INDEX_NAME = 'idx_active_expires') THEN
            ALTER TABLE `ml_accounts` ADD INDEX `idx_active_expires` (`is_active`, `token_expires_at`);
        END IF;
        IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_accounts' AND INDEX_NAME = 'idx_ml_user_id') THEN
            ALTER TABLE `ml_accounts` ADD INDEX `idx_ml_user_id` (`ml_user_id`);
        END IF;
    END IF;
END //

DELIMITER ;

CALL run_026_performance_optimization_indexes();
DROP PROCEDURE IF EXISTS run_026_performance_optimization_indexes;

-- Otimizar tabelas após criar índices (apenas se existirem)
SET @has_ml_orders := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders');
SET @sql_opt_ml_orders := IF(@has_ml_orders > 0, 'OPTIMIZE TABLE `ml_orders`', 'SELECT ''ml_orders não existe''');
PREPARE stmt_opt_ml_orders FROM @sql_opt_ml_orders;
EXECUTE stmt_opt_ml_orders;
DEALLOCATE PREPARE stmt_opt_ml_orders;

SET @has_items := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items');
SET @sql_opt_items := IF(@has_items > 0, 'OPTIMIZE TABLE `items`', 'SELECT ''items não existe''');
PREPARE stmt_opt_items FROM @sql_opt_items;
EXECUTE stmt_opt_items;
DEALLOCATE PREPARE stmt_opt_items;

SET @has_questions := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions');
SET @sql_opt_questions := IF(@has_questions > 0, 'OPTIMIZE TABLE `questions`', 'SELECT ''questions não existe''');
PREPARE stmt_opt_questions FROM @sql_opt_questions;
EXECUTE stmt_opt_questions;
DEALLOCATE PREPARE stmt_opt_questions;

SET @has_ml_accounts := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_accounts');
SET @sql_opt_ml_accounts := IF(@has_ml_accounts > 0, 'OPTIMIZE TABLE `ml_accounts`', 'SELECT ''ml_accounts não existe''');
PREPARE stmt_opt_ml_accounts FROM @sql_opt_ml_accounts;
EXECUTE stmt_opt_ml_accounts;
DEALLOCATE PREPARE stmt_opt_ml_accounts;
