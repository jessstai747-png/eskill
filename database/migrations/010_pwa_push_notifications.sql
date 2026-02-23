-- Migration: Push Notifications e PWA
-- Data: 2025-01-18
-- Descrição: Tabelas para suporte a PWA e Push Notifications

-- Tabela de subscriptions de push notification
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    endpoint VARCHAR(500) NOT NULL UNIQUE,
    p256dh_key TEXT,
    auth_key VARCHAR(255),
    user_agent VARCHAR(500),
    last_notified_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_endpoint (endpoint(191)),
    INDEX idx_last_notified (last_notified_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de notificações push enviadas (log)
CREATE TABLE IF NOT EXISTS push_notification_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    subscription_id INT,
    title VARCHAR(255) NOT NULL,
    body TEXT,
    payload JSON,
    status ENUM('pending', 'sent', 'failed', 'expired') DEFAULT 'pending',
    error_message TEXT,
    sent_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_subscription_id (subscription_id),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES push_subscriptions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de configurações PWA do usuário
CREATE TABLE IF NOT EXISTS pwa_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    push_enabled BOOLEAN DEFAULT TRUE,
    push_sales BOOLEAN DEFAULT TRUE,
    push_stock BOOLEAN DEFAULT TRUE,
    push_alerts BOOLEAN DEFAULT TRUE,
    push_system BOOLEAN DEFAULT TRUE,
    offline_mode BOOLEAN DEFAULT TRUE,
    installed BOOLEAN DEFAULT FALSE,
    install_date DATETIME,
    last_sync_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de dados offline (para sincronização)
CREATE TABLE IF NOT EXISTS offline_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    payload JSON,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    error_message TEXT,
    processed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar coluna para tracking de instalação PWA em users (se não existir)
-- ALTER TABLE users ADD COLUMN pwa_installed BOOLEAN DEFAULT FALSE AFTER updated_at;
-- ALTER TABLE users ADD COLUMN pwa_install_date DATETIME AFTER pwa_installed;
