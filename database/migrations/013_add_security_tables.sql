-- ====================================
-- MIGRAÇÃO: Adicionar suporte a tokens criptografados
-- ====================================

-- Adicionar coluna tokens_encrypted (MySQL-compatible, ignora se já existe)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_accounts' AND COLUMN_NAME = 'tokens_encrypted');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE ml_accounts ADD COLUMN tokens_encrypted TINYINT(1) DEFAULT 0 COMMENT ''Indica se os tokens estão criptografados (1=sim, 0=não)''',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Aumentar tamanho das colunas de token para comportar dados criptografados
ALTER TABLE ml_accounts 
MODIFY COLUMN access_token TEXT NULL,
MODIFY COLUMN refresh_token TEXT NULL;

-- Adicionar index (MySQL-compatible)
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_accounts' AND INDEX_NAME = 'idx_tokens_encrypted');
SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE ml_accounts ADD INDEX idx_tokens_encrypted (tokens_encrypted)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Tabela de auditoria de segurança
CREATE TABLE IF NOT EXISTS security_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL COMMENT 'Tipo de evento (login, token_refresh, failed_login, etc)',
    user_id INT NULL COMMENT 'ID do usuário (se aplicável)',
    account_id INT NULL COMMENT 'ID da conta ML (se aplicável)',
    ip_address VARCHAR(45) NOT NULL COMMENT 'Endereço IP',
    user_agent TEXT NULL COMMENT 'User Agent do navegador',
    details JSON NULL COMMENT 'Detalhes adicionais em JSON',
    severity ENUM('info', 'warning', 'critical') DEFAULT 'info',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_event_type (event_type),
    INDEX idx_user_id (user_id),
    INDEX idx_ip_address (ip_address),
    INDEX idx_created_at (created_at),
    INDEX idx_severity (severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de IPs bloqueados
CREATE TABLE IF NOT EXISTS blocked_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    reason VARCHAR(255) NOT NULL,
    blocked_by VARCHAR(50) DEFAULT 'system' COMMENT 'Quem bloqueou (system, admin, fail2ban)',
    blocked_until TIMESTAMP NULL COMMENT 'NULL = permanente',
    attempts INT DEFAULT 0 COMMENT 'Número de tentativas antes do bloqueio',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_ip_address (ip_address),
    INDEX idx_blocked_until (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de sessões ativas
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) NOT NULL PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    payload TEXT NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP EVENT IF EXISTS cleanup_security_data;
DROP PROCEDURE IF EXISTS cleanup_old_sessions;

-- Eventos individuais para limpeza automática diária
CREATE EVENT IF NOT EXISTS cleanup_user_sessions
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
    DELETE FROM user_sessions
    WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR);

CREATE EVENT IF NOT EXISTS cleanup_security_logs
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
    DELETE FROM security_audit_log
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

CREATE EVENT IF NOT EXISTS cleanup_expired_ip_blocks
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
    DELETE FROM blocked_ips
    WHERE blocked_until IS NOT NULL AND blocked_until < NOW();
