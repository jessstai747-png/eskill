<?php

/**
 * Run SEO Intelligence Tables Migration
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$db = App\Database::getInstance();

echo "🔄 Running SEO Intelligence Tables Migration...\n";

$sql = file_get_contents(__DIR__ . '/../database/migrations/030_create_seo_intelligence_tables.sql');

// Normalize line endings
$sql = str_replace(["\r\n", "\r"], "\n", $sql);

// Split into statements
$statements = [];
$buffer = '';
$lines = explode("\n", $sql);

foreach ($lines as $line) {
    $trimLine = trim($line);
    
    // Skip comments
    if (strpos($trimLine, '--') === 0 || empty($trimLine)) {
        continue;
    }
    
    $buffer .= $line . "\n";
    
    // Check if statement is complete
    $trimmedBuffer = trim($buffer);
    if (!empty($trimmedBuffer) && substr($trimmedBuffer, -1) === ';') {
        $statement = substr($trimmedBuffer, 0, -1);
        if (!empty(trim($statement))) {
            $statements[] = $statement;
        }
        $buffer = '';
    }
}

$success = 0;
$errors = 0;

foreach ($statements as $statement) {
    try {
        $db->exec($statement);
        $success++;
        
        // Extract table name for logging
        if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
            echo "✅ Created table: {$matches[1]}\n";
        }
    } catch (Exception $e) {
        $errors++;
        echo "❌ Error: " . $e->getMessage() . "\n";
        echo "Statement preview: " . substr($statement, 0, 100) . "...\n\n";
    }
}

echo "\n";
echo "✅ Successfully executed $success statements\n";
if ($errors > 0) {
    echo "❌ Failed: $errors statements\n";
}
echo "🎉 Migration completed!\n";
