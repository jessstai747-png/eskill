-- Adicionar campos status e last_login à tabela users
-- Idempotent: Verifica se colunas existem antes de adicionar

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'status');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE users ADD COLUMN status ENUM(''active'', ''inactive'', ''suspended'') DEFAULT ''active'' AFTER password', 
    'SELECT ''Column exists''');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'last_login');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL AFTER updated_at', 
    'SELECT ''Column exists''');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Criar índice para status (se não existir)
SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_status');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_status ON users(status)', 'SELECT ''Index exists''');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;
