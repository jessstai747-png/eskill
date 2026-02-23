-- Migration: Otimização de índices para melhor performance
-- Data: 2024-12-15
-- Idempotent: Usa IF NOT EXISTS para segurança

-- Índices adicionais para tabela ml_orders
-- Verifica se índice existe antes de criar
SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND INDEX_NAME = 'idx_status_date');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE ml_orders ADD INDEX idx_status_date (status, date_created)', 'SELECT ''Index exists''');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND INDEX_NAME = 'idx_account_status');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE ml_orders ADD INDEX idx_account_status (ml_account_id, status)', 'SELECT ''Index exists''');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Índices adicionais para tabela items (se existir)
-- Nota: Execute apenas se a tabela items já foi criada
-- ALTER TABLE items ADD INDEX idx_category_status (category_id, status);
-- ALTER TABLE items ADD INDEX idx_price_range (price);
-- ALTER TABLE items ADD INDEX idx_created_updated (created_at, updated_at);

-- Índices adicionais para tabela alerts
SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alerts' AND INDEX_NAME = 'idx_account_type');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE alerts ADD INDEX idx_account_type (ml_account_id, type)', 'SELECT ''Index exists''');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alerts' AND INDEX_NAME = 'idx_type_created');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE alerts ADD INDEX idx_type_created (type, created_at)', 'SELECT ''Index exists''');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alerts' AND INDEX_NAME = 'idx_unread');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE alerts ADD INDEX idx_unread (read_at, created_at)', 'SELECT ''Index exists''');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Índices adicionais para tabela price_history
SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'price_history' AND INDEX_NAME = 'idx_category_brand_date');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE price_history ADD INDEX idx_category_brand_date (category_id, brand, recorded_at)', 'SELECT ''Index exists''');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'price_history' AND INDEX_NAME = 'idx_date_desc');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE price_history ADD INDEX idx_date_desc (recorded_at DESC)', 'SELECT ''Index exists''');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Índices adicionais para tabela ml_accounts
SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_accounts' AND INDEX_NAME = 'idx_user_status');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE ml_accounts ADD INDEX idx_user_status (user_id, status)', 'SELECT ''Index exists''');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_accounts' AND INDEX_NAME = 'idx_token_expires');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE ml_accounts ADD INDEX idx_token_expires (token_expires_at)', 'SELECT ''Index exists''');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Otimização: Adicionar índice composto para busca de pedidos por período
-- ALTER TABLE ml_orders 
-- ADD INDEX idx_account_date_range (ml_account_id, date_created, status);

-- Análise de tabelas para otimização
-- Execute manualmente: ANALYZE TABLE ml_orders, items, alerts, price_history, ml_accounts;
