#!/usr/bin/env php
<?php
/**
 * AI Optimization Database Migration Runner
 * Executes SQL migration files for AI optimization tables
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

echo "🚀 AI Optimization Database Migration\n";
echo "=====================================\n\n";

// Migration files to run
$migrations = [
    '020_create_ai_optimization_queue_table.sql',
    '021_create_ai_ab_tests_tables.sql',
    '022_create_ai_audit_log_table.sql',
    '023_create_ai_performance_tracking_table.sql'
];

$migrationsPath = __DIR__ . '/../database/migrations/';
$db = Database::getInstance();

$success = 0;
$failed = 0;

foreach ($migrations as $migration) {
    $filePath = $migrationsPath . $migration;
    
    if (!file_exists($filePath)) {
        echo "❌ Migration file not found: $migration\n";
        $failed++;
        continue;
    }
    
    echo "⏳ Running: $migration ... ";
    
    try {
        $sql = file_get_contents($filePath);
        
        // Execute SQL (handle multiple statements)
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($stmt) => !empty($stmt) && !preg_match('/^--/', $stmt)
        );
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $db->query($statement);
            }
        }
        
        echo "✅ Success\n";
        $success++;
        
    } catch (Exception $e) {
        echo "❌ Failed\n";
        echo "   Error: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n=====================================\n";
echo "📊 Migration Summary\n";
echo "   Successful: $success\n";
echo "   Failed: $failed\n";
echo "   Total: " . count($migrations) . "\n";

if ($failed === 0) {
    echo "\n✅ All migrations completed successfully!\n";
    exit(0);
} else {
    echo "\n⚠️  Some migrations failed. Please check the errors above.\n";
    exit(1);
}
