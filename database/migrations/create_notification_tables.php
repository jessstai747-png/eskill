<?php

/**
 * Migration: Criar tabelas de notificações
 * Data: 31/12/2025
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Load .env
if (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
}

use App\Database;

try {
    $db = Database::getInstance();
    
    // Tabela de preferências de notificação
    $db->exec("
        CREATE TABLE IF NOT EXISTS notification_preferences (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            
            -- Canais ativos
            email_alerts BOOLEAN DEFAULT 1,
            whatsapp_alerts BOOLEAN DEFAULT 0,
            sms_alerts BOOLEAN DEFAULT 0,
            
            -- Threshold de prioridade
            alert_priority_threshold VARCHAR(20) DEFAULT 'medium',
            
            -- Horários permitidos
            quiet_hours_start TIME,
            quiet_hours_end TIME,
            
            -- Frequência de relatórios
            daily_report BOOLEAN DEFAULT 0,
            weekly_report BOOLEAN DEFAULT 1,
            monthly_report BOOLEAN DEFAULT 1,
            
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            UNIQUE KEY unique_user (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Tabela de logs de notificações
    $db->exec("
        CREATE TABLE IF NOT EXISTS notification_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            
            -- Tipo e destinatário
            type VARCHAR(20) NOT NULL,
            recipient VARCHAR(255) NOT NULL,
            subject TEXT,
            
            -- Status
            status VARCHAR(20) NOT NULL,
            error_message TEXT,
            
            -- Metadata
            metadata JSON,
            
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_type (type),
            INDEX idx_status (status),
            INDEX idx_created (created_at),
            INDEX idx_recipient (recipient)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✅ Tabelas de notificações criadas com sucesso!\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
