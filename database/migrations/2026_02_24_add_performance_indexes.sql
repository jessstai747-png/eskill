-- Performance Optimization: Add Missing Database Indexes
-- Target: Improve dashboard metrics loading time from 7-14s to <2s
-- Idempotent: Todos os índices verificam existência antes de criar

-- Helper procedure para criar índice se não existir
DELIMITER //
DROP PROCEDURE IF EXISTS CreateIndexIfNotExists//
CREATE PROCEDURE CreateIndexIfNotExists(
    IN tableName VARCHAR(64),
    IN indexName VARCHAR(64),
    IN indexColumns VARCHAR(255)
)
BEGIN
    DECLARE indexExists INT DEFAULT 0;
    SELECT COUNT(*) INTO indexExists 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = tableName 
    AND INDEX_NAME = indexName;
    
    IF indexExists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE ', tableName, ' ADD INDEX ', indexName, ' (', indexColumns, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//
DELIMITER ;

-- ml_items table indexes for common queries
CALL CreateIndexIfNotExists('ml_items', 'idx_status_items', 'status');
CALL CreateIndexIfNotExists('ml_items', 'idx_created_at_items', 'created_at');
CALL CreateIndexIfNotExists('ml_items', 'idx_price_items', 'price');
CALL CreateIndexIfNotExists('ml_items', 'idx_account_status_items', 'account_id, status');
CALL CreateIndexIfNotExists('ml_items', 'idx_updated_at_items', 'updated_at');

-- ml_questions table indexes
CALL CreateIndexIfNotExists('ml_questions', 'idx_status_questions', 'status');
CALL CreateIndexIfNotExists('ml_questions', 'idx_date_created_questions', 'date_created');
CALL CreateIndexIfNotExists('ml_questions', 'idx_account_id_questions', 'account_id');

-- users table indexes  
CALL CreateIndexIfNotExists('users', 'idx_email_users', 'email');
CALL CreateIndexIfNotExists('users', 'idx_created_at_users', 'created_at');

-- ml_accounts table indexes
CALL CreateIndexIfNotExists('ml_accounts', 'idx_user_id_accounts', 'user_id');
CALL CreateIndexIfNotExists('ml_accounts', 'idx_status_accounts', 'status');

-- Cleanup procedure
DROP PROCEDURE IF EXISTS CreateIndexIfNotExists;
