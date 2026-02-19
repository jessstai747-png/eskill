<?php

/**
 * Migration: Create ml_accounts table
 *
 * This table stores Mercado Livre account information including encrypted tokens
 * for API authentication and refresh token functionality.
 */

use App\Database;

// Get the database instance
$db = Database::getInstance();

// Create ml_accounts table
$db->exec("
CREATE TABLE IF NOT EXISTS ml_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    ml_user_id VARCHAR(50) UNIQUE,
    nickname VARCHAR(100),
    email VARCHAR(255),
    site_id VARCHAR(10) DEFAULT 'MLB',
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at DATETIME,
    tokens_encrypted TINYINT(1) DEFAULT 0,
    status ENUM('active', 'inactive', 'expired', 'disconnected') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ml_user_id (ml_user_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_token_expires_at (token_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "✅ Table 'ml_accounts' created successfully!\n";

// Verify the table was created
try {
    $result = $db->query("DESCRIBE ml_accounts");
    if (!empty($result)) {
        echo "✅ Table structure verified successfully!\n";
        
        // Show the table structure
        echo "\n📋 ml_accounts table structure:\n";
        foreach ($result as $column) {
            echo "   - {$column['Field']} ({$column['Type']}, {$column['Null']}, {$column['Key']})\n";
        }
    }
} catch (Exception $e) {
    echo "⚠️ Could not verify table structure: " . $e->getMessage() . "\n";
}

echo "\n🎉 ml_accounts table migration completed successfully!\n";
echo "\nThis table supports:\n";
echo "- Storing Mercado Livre account information\n";
echo "- Encrypted access and refresh tokens\n";
echo "- Automatic token refresh functionality\n";
echo "- Account status tracking\n";
echo "- Foreign key relationship with users table\n";