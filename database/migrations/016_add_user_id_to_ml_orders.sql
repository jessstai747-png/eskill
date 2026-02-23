-- Adicionar user_id à tabela ml_orders
-- Idempotent: Verifica se coluna, constraint e índice existem

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND COLUMN_NAME = 'user_id');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE ml_orders ADD COLUMN user_id INT AFTER ml_account_id', 
    'SELECT ''Column exists''');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND CONSTRAINT_NAME = 'fk_ml_orders_user_id');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE ml_orders ADD CONSTRAINT fk_ml_orders_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE', 
    'SELECT ''Constraint exists''');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND INDEX_NAME = 'idx_user_id');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_user_id ON ml_orders(user_id)', 'SELECT ''Index exists''');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Populate user_id from ml_accounts (seguro para rodar múltiplas vezes)
UPDATE ml_orders o
JOIN ml_accounts a ON o.ml_account_id = a.id
SET o.user_id = a.user_id
WHERE o.user_id IS NULL;
