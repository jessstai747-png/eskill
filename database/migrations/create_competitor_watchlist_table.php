<?php

/**
 * Migration: Criar tabela de watchlist de concorrentes
 * Data: 31/12/2025
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;

try {
    $db = Database::getInstance();

    // Tabela de watchlist
    $db->exec("
        CREATE TABLE IF NOT EXISTS competitor_watchlist (
            id INT PRIMARY KEY AUTO_INCREMENT,
            account_id INT NOT NULL,
            competitor_item_id VARCHAR(50) NOT NULL,
            competitor_seller_id VARCHAR(50),
            nickname VARCHAR(255),

            -- Snapshot atual
            title TEXT,
            price DECIMAL(10,2),
            sold_quantity INT DEFAULT 0,
            available_quantity INT DEFAULT 0,
            listing_type VARCHAR(20),
            `condition` VARCHAR(20),

            -- SEO metrics
            seo_score INT,
            title_length INT,
            pictures_count INT DEFAULT 0,
            attributes_filled INT DEFAULT 0,

            -- Tracking
            free_shipping BOOLEAN DEFAULT 0,
            shipping_mode VARCHAR(50),
            status VARCHAR(20) DEFAULT 'active',

            -- Metadata
            category_id VARCHAR(50),
            tags TEXT,
            notes TEXT,
            alert_on_changes BOOLEAN DEFAULT 1,

            -- Timestamps
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_checked_at DATETIME,

            INDEX idx_account (account_id),
            INDEX idx_competitor_item (competitor_item_id),
            INDEX idx_seller (competitor_seller_id),
            INDEX idx_status (status),
            UNIQUE KEY unique_account_item (account_id, competitor_item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Tabela de histórico de mudanças
    $db->exec("
        CREATE TABLE IF NOT EXISTS competitor_history (
            id INT PRIMARY KEY AUTO_INCREMENT,
            watchlist_id INT NOT NULL,

            -- Changed fields
            field_changed VARCHAR(50) NOT NULL,
            old_value TEXT,
            new_value TEXT,

            -- Metadata
            change_type VARCHAR(20),
            detected_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_watchlist (watchlist_id),
            INDEX idx_field (field_changed),
            INDEX idx_detected (detected_at),
            FOREIGN KEY (watchlist_id) REFERENCES competitor_watchlist(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Tabela de alertas
    $db->exec("
        CREATE TABLE IF NOT EXISTS competitor_alerts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            account_id INT NOT NULL,
            watchlist_id INT,

            -- Alert details
            alert_type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT,

            -- Priority and status
            priority VARCHAR(20) DEFAULT 'medium',
            status VARCHAR(20) DEFAULT 'unread',
            action_url TEXT,

            -- Timestamps
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME,

            INDEX idx_account (account_id),
            INDEX idx_status (status),
            INDEX idx_priority (priority),
            INDEX idx_watchlist (watchlist_id),
            FOREIGN KEY (watchlist_id) REFERENCES competitor_watchlist(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✅ Tabelas de watchlist criadas com sucesso!\n";
} catch (Exception $e) {
    $errCode = $e instanceof \PDOException ? ($e->errorInfo[1] ?? 0) : 0;
    if (in_array((int)$errCode, [1050, 1060, 1061], true)) {
        echo "⚠ Já existe: " . $e->getMessage() . "\n";
    } else {
        echo "❌ Erro: " . $e->getMessage() . "\n";
        throw $e;
    }
}
