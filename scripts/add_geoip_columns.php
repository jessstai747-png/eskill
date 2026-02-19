#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Add GeoIP columns to database tables
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

echo "🌍 Adicionando colunas GeoIP ao banco de dados...\n\n";

$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbName = $_ENV['DB_DATABASE'] ?? 'meli';
$dbUser = $_ENV['DB_USERNAME'] ?? 'root';
$dbPass = $_ENV['DB_PASSWORD'] ?? '';

try {
    $db = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Conectado ao banco de dados\n\n";
    
    // Adicionar colunas à tabela auth_failure_log
    echo "📊 Atualizando tabela auth_failure_log...\n";
    
    $alterations = [
        "ADD COLUMN country_code CHAR(2) NULL COMMENT 'ISO country code' AFTER ip_address",
        "ADD COLUMN country_name VARCHAR(100) NULL COMMENT 'Country name' AFTER country_code",
        "ADD COLUMN city VARCHAR(100) NULL COMMENT 'City name' AFTER country_name",
        "ADD COLUMN latitude DECIMAL(10, 7) NULL COMMENT 'Latitude' AFTER city",
        "ADD COLUMN longitude DECIMAL(10, 7) NULL COMMENT 'Longitude' AFTER latitude",
        "ADD INDEX idx_country (country_code)",
        "ADD INDEX idx_city (city)"
    ];
    
    foreach ($alterations as $alter) {
        try {
            $db->exec("ALTER TABLE auth_failure_log $alter");
            echo "  ✓ " . explode(' ', $alter)[0] . " " . explode(' ', $alter)[2] . "\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "  ⚠️  Coluna já existe: " . explode(' ', $alter)[2] . "\n";
            } else {
                throw $e;
            }
        }
    }
    
    echo "\n📊 Atualizando tabela auth_blocked_ips...\n";
    
    $alterations = [
        "ADD COLUMN country_code CHAR(2) NULL COMMENT 'ISO country code' AFTER ip_address",
        "ADD COLUMN country_name VARCHAR(100) NULL COMMENT 'Country name' AFTER country_code",
        "ADD COLUMN city VARCHAR(100) NULL COMMENT 'City name' AFTER country_name",
        "ADD INDEX idx_country (country_code)"
    ];
    
    foreach ($alterations as $alter) {
        try {
            $db->exec("ALTER TABLE auth_blocked_ips $alter");
            echo "  ✓ " . explode(' ', $alter)[0] . " " . explode(' ', $alter)[2] . "\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "  ⚠️  Coluna já existe: " . explode(' ', $alter)[2] . "\n";
            } else {
                throw $e;
            }
        }
    }
    
    echo "\n✅ Estrutura do banco de dados atualizada com sucesso!\n\n";
    
    // Mostrar estrutura atualizada
    echo "📋 Estrutura atualizada:\n\n";
    
    $stmt = $db->query("DESCRIBE auth_failure_log");
    echo "auth_failure_log:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
    
    echo "\n";
    
    $stmt = $db->query("DESCRIBE auth_blocked_ips");
    echo "auth_blocked_ips:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
    
    echo "\n✅ Pronto! Agora o sistema pode armazenar informações de geolocalização.\n";
    
} catch (PDOException $e) {
    echo "❌ Erro: {$e->getMessage()}\n";
    exit(1);
}
