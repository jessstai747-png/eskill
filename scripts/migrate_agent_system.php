<?php

/**
 * Migration: Criar tabelas para sistema de Long-Running Agents
 * 
 * Execução:
 * php scripts/migrate_agent_system.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use App\Database;

echo "=== Criando tabelas do sistema de Agents ===\n\n";

try {
    $db = Database::getInstance();
    
    // 1. Tabela de projetos
    echo "Criando tabela agent_projects...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS agent_projects (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            category VARCHAR(100),
            requirements JSON,
            status ENUM('not_initialized', 'initialized', 'in_progress', 'completed', 'paused') DEFAULT 'not_initialized',
            completion_percentage DECIMAL(5,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ agent_projects criada\n\n";
    
    // 2. Tabela de features
    echo "Criando tabela agent_features...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS agent_features (
            id INT PRIMARY KEY AUTO_INCREMENT,
            project_id INT NOT NULL,
            feature_id VARCHAR(100) NOT NULL,
            category ENUM('functional', 'ui', 'performance', 'security', 'other') NOT NULL,
            description TEXT NOT NULL,
            steps JSON NOT NULL,
            passes BOOLEAN DEFAULT FALSE,
            priority ENUM('high', 'medium', 'low') DEFAULT 'medium',
            tested_at TIMESTAMP NULL,
            test_results JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_feature (project_id, feature_id),
            FOREIGN KEY (project_id) REFERENCES agent_projects(id) ON DELETE CASCADE,
            INDEX idx_priority (priority),
            INDEX idx_passes (passes)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ agent_features criada\n\n";
    
    // 3. Tabela de sessões
    echo "Criando tabela agent_sessions...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS agent_sessions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            project_id INT NOT NULL,
            session_type ENUM('initializer', 'coding', 'testing', 'cleanup') NOT NULL,
            feature_id VARCHAR(100),
            status ENUM('running', 'completed', 'failed') DEFAULT 'running',
            context_tokens INT,
            result_data JSON,
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            FOREIGN KEY (project_id) REFERENCES agent_projects(id) ON DELETE CASCADE,
            INDEX idx_project (project_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ agent_sessions criada\n\n";
    
    // 4. Tabela de progress log
    echo "Criando tabela agent_progress_log...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS agent_progress_log (
            id INT PRIMARY KEY AUTO_INCREMENT,
            project_id INT NOT NULL,
            session_type ENUM('initializer', 'coding', 'testing', 'cleanup') NOT NULL,
            feature_id VARCHAR(100) NULL,
            status ENUM('completed', 'in_progress', 'failed') NOT NULL,
            summary TEXT,
            data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES agent_projects(id) ON DELETE CASCADE,
            INDEX idx_project (project_id),
            INDEX idx_type (session_type),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ agent_progress_log criada\n\n";
    
    // 5. Criar diretório de projetos
    echo "Criando diretório storage/agent_projects...\n";
    $projectsDir = __DIR__ . '/../storage/agent_projects';
    if (!is_dir($projectsDir)) {
        mkdir($projectsDir, 0755, true);
        echo "✓ Diretório criado: {$projectsDir}\n\n";
    } else {
        echo "✓ Diretório já existe: {$projectsDir}\n\n";
    }
    
    echo "=== Migration completa! ===\n";
    echo "\nPróximos passos:\n";
    echo "1. Criar projeto: POST /api/agent/projects/start\n";
    echo "2. Executar sessão: POST /api/agent/projects/{id}/session\n";
    echo "3. Ver status: GET /api/agent/projects/{id}/status\n";
    echo "\nVeja docs/LONG_RUNNING_AGENTS.md para mais detalhes.\n";
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
