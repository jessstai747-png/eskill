<?php

declare(strict_types=1);

/**
 * Migration: Create governance_diagnostic_history table
 * Date: 2025-02-20
 * 
 * Stores periodic governance diagnostics for each ML account
 * to enable trend analysis and health monitoring over time.
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

    // Main diagnostic history table
    $db->exec("
        CREATE TABLE IF NOT EXISTS governance_diagnostic_history (
            id INT PRIMARY KEY AUTO_INCREMENT,
            account_id INT NOT NULL COMMENT 'FK to mercadolivre_auth.id',
            
            -- Summary metrics for quick queries
            account_status VARCHAR(50) NOT NULL COMMENT 'active/inactive/mixed',
            total_items INT DEFAULT 0,
            healthy_items INT DEFAULT 0,
            problem_items INT DEFAULT 0,
            critical_actions INT DEFAULT 0 COMMENT 'Number of critical priority actions',
            
            -- JSON data for detailed analysis
            top_causes JSON COMMENT 'Top health issue causes',
            executive_summary JSON COMMENT 'Key metrics summary',
            full_result JSON COMMENT 'Complete diagnostic result',
            
            -- Timestamps
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            
            -- Indexes for common queries
            INDEX idx_account_id (account_id),
            INDEX idx_created_at (created_at),
            INDEX idx_account_created (account_id, created_at),
            INDEX idx_critical (account_id, critical_actions)
            
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Governance diagnostic history for trend analysis'
    ");

    echo "Created table: governance_diagnostic_history\n";

    // Optional: Create view for latest diagnostics per account
    $db->exec("
        CREATE OR REPLACE VIEW v_latest_governance_diagnostic AS
        SELECT gdh.*
        FROM governance_diagnostic_history gdh
        INNER JOIN (
            SELECT account_id, MAX(created_at) as max_created
            FROM governance_diagnostic_history
            GROUP BY account_id
        ) latest ON gdh.account_id = latest.account_id 
                 AND gdh.created_at = latest.max_created
    ");

    echo "Created view: v_latest_governance_diagnostic\n";

    echo "\nMigration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    throw $e;
}
