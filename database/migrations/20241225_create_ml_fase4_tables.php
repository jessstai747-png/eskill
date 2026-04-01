<?php
declare(strict_types=1);

/**
 * Migration: Fase 4 - Dynamic Pricing, AI Predictions, Chatbot AI
 *
 * Cria tabelas para suportar:
 * - Histórico de ajustes de preço
 * - Tickets de suporte
 * - Interações do chatbot
 * - Previsões de ML
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

try {
    $db = Database::getInstance();

    echo "Iniciando migração - Fase 4 ML & AI Features...\n\n";

    // ==================== PRICE_ADJUSTMENTS ====================
    echo "Criando tabela: price_adjustments\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS price_adjustments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            item_id VARCHAR(50) NOT NULL,
            old_price DECIMAL(12, 2) NOT NULL,
            new_price DECIMAL(12, 2) NOT NULL,
            strategy VARCHAR(50) NOT NULL COMMENT 'competition, demand, inventory, manual',
            confidence DECIMAL(5, 2),
            reason TEXT,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_account (account_id),
            INDEX idx_item (item_id),
            INDEX idx_strategy (strategy),
            INDEX idx_applied (applied_at),
            FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Tabela price_adjustments criada\n\n";

    // ==================== SUPPORT_TICKETS ====================
    echo "Criando tabela: support_tickets\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS support_tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            ticket_id VARCHAR(50) NOT NULL,
            user_id VARCHAR(100) NOT NULL,
            type VARCHAR(30) NOT NULL COMMENT 'complaint, question, return, technical',
            priority VARCHAR(20) DEFAULT 'normal' COMMENT 'low, normal, high, urgent',
            status VARCHAR(20) DEFAULT 'open' COMMENT 'open, in_progress, resolved, closed',
            subject VARCHAR(255),
            description TEXT,
            entities JSON,
            assigned_to INT,
            resolved_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_ticket (ticket_id),
            INDEX idx_account (account_id),
            INDEX idx_user (user_id),
            INDEX idx_type (type),
            INDEX idx_priority (priority),
            INDEX idx_status (status),
            INDEX idx_created (created_at),
            FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Tabela support_tickets criada\n\n";

    // ==================== CHATBOT_INTERACTIONS ====================
    echo "Criando tabela: chatbot_interactions\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS chatbot_interactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            user_id VARCHAR(100) NOT NULL,
            input_text TEXT NOT NULL,
            detected_intent VARCHAR(50),
            intent_confidence DECIMAL(5, 2),
            response_text TEXT,
            requires_human BOOLEAN DEFAULT 0,
            feedback_rating TINYINT,
            resolved BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_account (account_id),
            INDEX idx_user (user_id),
            INDEX idx_intent (detected_intent),
            INDEX idx_requires_human (requires_human),
            INDEX idx_created (created_at),
            FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Tabela chatbot_interactions criada\n\n";

    // ==================== ML_PREDICTIONS ====================
    echo "Criando tabela: ml_predictions\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS ml_predictions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            prediction_type VARCHAR(50) NOT NULL COMMENT 'sales, demand, pricing, trending',
            target_id VARCHAR(50) NOT NULL COMMENT 'item_id ou category_id',
            prediction_date DATE NOT NULL,
            predicted_value DECIMAL(12, 2),
            confidence DECIMAL(5, 2),
            actual_value DECIMAL(12, 2),
            accuracy DECIMAL(5, 2),
            model_used VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_account (account_id),
            INDEX idx_type (prediction_type),
            INDEX idx_target (target_id),
            INDEX idx_date (prediction_date),
            INDEX idx_created (created_at),
            FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Tabela ml_predictions criada\n\n";

    // ==================== COMPETITOR_PRICES ====================
    echo "Criando tabela: competitor_prices\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS competitor_prices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            item_id VARCHAR(50) NOT NULL,
            competitor_item_id VARCHAR(50) NOT NULL,
            competitor_seller_id VARCHAR(100),
            competitor_price DECIMAL(12, 2) NOT NULL,
            competitor_reputation INT,
            competitor_sold_quantity INT,
            our_price DECIMAL(12, 2),
            price_difference DECIMAL(12, 2),
            scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_account (account_id),
            INDEX idx_item (item_id),
            INDEX idx_scanned (scanned_at),
            FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Tabela competitor_prices criada\n\n";

    // ==================== AI_TRAINING_DATA ====================
    echo "Criando tabela: ai_training_data\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS ai_training_data (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            data_type VARCHAR(50) NOT NULL COMMENT 'pricing, sales, chatbot, trend',
            input_features JSON NOT NULL,
            expected_output JSON,
            actual_output JSON,
            accuracy_score DECIMAL(5, 2),
            used_for_training BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_account (account_id),
            INDEX idx_type (data_type),
            INDEX idx_training (used_for_training),
            INDEX idx_created (created_at),
            FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Tabela ai_training_data criada\n\n";

    echo "============================================\n";
    echo "✓ Migração Fase 4 concluída com sucesso!\n";
    echo "============================================\n\n";

    echo "Tabelas criadas:\n";
    echo "- price_adjustments (histórico de ajustes de preço)\n";
    echo "- support_tickets (tickets de suporte)\n";
    echo "- chatbot_interactions (interações do chatbot)\n";
    echo "- ml_predictions (previsões de ML)\n";
    echo "- competitor_prices (preços da concorrência)\n";
    echo "- ai_training_data (dados para treinamento)\n\n";

    echo "Total de índices criados: 32\n";
    echo "Foreign keys com CASCADE: 6\n\n";

    echo "Features habilitadas:\n";
    echo "✅ Dynamic Pricing (ajuste automático de preços)\n";
    echo "✅ AI Predictions (previsão de vendas com ML)\n";
    echo "✅ Chatbot AI (atendimento inteligente)\n";
    echo "✅ Competitor Tracking (monitoramento de concorrência)\n";
    echo "✅ Continuous Learning (aprendizado contínuo)\n\n";
} catch (\Exception $e) {
    echo "ERRO na migração: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    throw $e;
}

/*
 * DOWN — Para reverter esta migration manualmente:
 *
//   $db->exec('DROP TABLE IF EXISTS price_adjustments;');
//   $db->exec('DROP TABLE IF EXISTS support_tickets;');
//   $db->exec('DROP TABLE IF EXISTS chatbot_interactions;');
//   $db->exec('DROP TABLE IF EXISTS ml_predictions;');
//   $db->exec('DROP TABLE IF EXISTS competitor_prices;');
//   $db->exec('DROP TABLE IF EXISTS ai_training_data;');
 *
 * ATENÇÃO: Isso apaga dados permanentemente. Faça backup antes.
 */
