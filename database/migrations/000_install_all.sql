-- Script completo de instalação do banco de dados
-- Execute este arquivo para criar todas as tabelas

-- Tabela de usuários do sistema
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'support', 'user', 'viewer') DEFAULT 'admin',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
    two_factor_secret VARCHAR(255) NULL,
    remember_token VARCHAR(100) NULL,
    email_verified_at TIMESTAMP NULL,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de contas do Mercado Livre vinculadas
CREATE TABLE IF NOT EXISTS ml_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    ml_user_id VARCHAR(50) NOT NULL,
    nickname VARCHAR(100) NOT NULL,
    email VARCHAR(255) NULL,
    site_id VARCHAR(10) DEFAULT 'MLB',
    access_token TEXT NOT NULL,
    refresh_token TEXT NOT NULL,
    token_expires_at DATETIME NOT NULL,
    status ENUM('active', 'inactive', 'expired', 'disconnected') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_ml_user (ml_user_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_token_expires (token_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de logs de sincronização
CREATE TABLE IF NOT EXISTS sync_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ml_account_id INT NOT NULL,
    sync_type VARCHAR(50) NOT NULL,
    status ENUM('success', 'error', 'pending') DEFAULT 'pending',
    message TEXT NULL,
    data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ml_account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE,
    INDEX idx_account_id (ml_account_id),
    INDEX idx_sync_type (sync_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Worker execution audit (used by monitoring workers)
CREATE TABLE IF NOT EXISTS worker_execution_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    worker_name VARCHAR(120) NOT NULL,
    stats JSON NULL,
    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_worker_execution_logs_name (worker_name),
    INDEX idx_worker_execution_logs_executed_at (executed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clone health check results (used by clone-health-monitor)
CREATE TABLE IF NOT EXISTS clone_health_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    status ENUM('healthy', 'warning', 'critical') NOT NULL DEFAULT 'healthy',
    issues_count INT UNSIGNED NOT NULL DEFAULT 0,
    check_data JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_clone_health_logs_created_at (created_at),
    INDEX idx_clone_health_logs_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clone item duplicate tracking (used by CloneDuplicateDetectionService)
CREATE TABLE IF NOT EXISTS clone_duplicate_registry (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    source_item_id VARCHAR(50) NOT NULL,
    target_item_id VARCHAR(50) NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    job_id VARCHAR(64) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_source_account_status (source_item_id, account_id, status),
    INDEX idx_target_status (target_item_id, status),
    INDEX idx_account_created (account_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clone sync event log (used by clone-sync-worker)
CREATE TABLE IF NOT EXISTS clone_sync_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    sync_type VARCHAR(50) NOT NULL,
    sync_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_account (account_id),
    INDEX idx_item (item_id),
    INDEX idx_type (sync_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
