<?php

/**
 * Migration: Criar tabela de proxies
 */

use App\Database;

return new class {
    public function up(): void
    {
        $db = Database::getInstance();

        $db->exec("
            CREATE TABLE IF NOT EXISTS ml_proxies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type ENUM('http', 'https', 'socks4', 'socks5') NOT NULL DEFAULT 'http',
                host VARCHAR(255) NOT NULL,
                port VARCHAR(10) NOT NULL DEFAULT '8080',
                username VARCHAR(255) DEFAULT NULL,
                password VARCHAR(255) DEFAULT NULL,
                country CHAR(2) DEFAULT 'BR',
                priority INT DEFAULT 50,
                status ENUM('active', 'inactive', 'testing') DEFAULT 'active',
                success_count INT DEFAULT 0,
                failure_count INT DEFAULT 0,
                last_used_at DATETIME DEFAULT NULL,
                last_success_at DATETIME DEFAULT NULL,
                last_failure_at DATETIME DEFAULT NULL,
                last_error TEXT DEFAULT NULL,
                avg_response_time INT DEFAULT NULL COMMENT 'em milissegundos',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_proxy (host, port),
                INDEX idx_status (status),
                INDEX idx_priority (priority DESC),
                INDEX idx_country (country)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Criar tabela de logs de uso de proxy
        $db->exec("
            CREATE TABLE IF NOT EXISTS ml_proxy_logs (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                proxy_id INT NOT NULL,
                endpoint VARCHAR(500) NOT NULL,
                status_code INT DEFAULT NULL,
                response_time INT DEFAULT NULL COMMENT 'em milissegundos',
                success TINYINT(1) DEFAULT 0,
                error_message TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_proxy_id (proxy_id),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (proxy_id) REFERENCES ml_proxies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $db = Database::getInstance();
        $db->exec("DROP TABLE IF EXISTS ml_proxy_logs");
        $db->exec("DROP TABLE IF EXISTS ml_proxies");
    }
};
