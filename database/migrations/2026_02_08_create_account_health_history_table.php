<?php
declare(strict_types=1);

/**
 * Migration: Create account_health_history table
 *
 * This table stores historical account health scores for trend analysis
 * Used by AccountHealthService to track changes over time and calculate deltas.
 */

use App\Database;

// Get the database instance
$db = Database::getInstance();

// Create account_health_history table
$db->exec("
CREATE TABLE IF NOT EXISTS account_health_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    overall_score INT NOT NULL,
    reputation_score INT NOT NULL,
    seo_score INT NOT NULL,
    competitiveness_score INT NOT NULL,
    operation_score INT NOT NULL,
    sales_score INT NOT NULL,
    action_count INT NOT NULL DEFAULT 0,
    critical_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_account_id (account_id),
    INDEX idx_created_at (created_at),
    INDEX idx_account_created (account_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "✅ Table 'account_health_history' created successfully!\n";

// Verify the table was created
try {
    $result = $db->query("DESCRIBE account_health_history");
    if (!empty($result)) {
        echo "✅ Table structure verified successfully!\n";
        
        // Show the table structure
        echo "\n📋 account_health_history table structure:\n";
        foreach ($result as $column) {
            echo "   - {$column['Field']} ({$column['Type']}, {$column['Null']}, {$column['Key']})\n";
        }
    }
} catch (Exception $e) {
    echo "⚠️ Could not verify table structure: " . $e->getMessage() . "\n";
}

echo "\n🎉 account_health_history table migration completed successfully!\n";
echo "\nThis table supports:\n";
echo "- Storing historical account health scores\n";
echo "- Trend analysis for account performance\n";
echo "- Delta calculation between snapshots\n";
echo "- Data for the AccountHealthService\n";

/*
 * DOWN — Para reverter esta migration manualmente:
 *
//   $db->exec('DROP TABLE IF EXISTS account_health_history;');
 *
 * ATENÇÃO: Isso apaga dados permanentemente. Faça backup antes.
 */
