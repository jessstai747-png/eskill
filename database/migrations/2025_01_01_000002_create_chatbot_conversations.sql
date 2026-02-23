-- =====================================================================
-- Chatbot Conversations Table
-- Armazena conversas do assistente de IA
-- =====================================================================

CREATE TABLE IF NOT EXISTS chatbot_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    conversation_id VARCHAR(64) NOT NULL COMMENT 'UUID da conversa (agrupamento)',
    user_message TEXT NOT NULL,
    bot_response TEXT NOT NULL,
    context JSON DEFAULT NULL COMMENT 'Contexto da mensagem (page, feature, data)',
    suggested_actions JSON DEFAULT NULL COMMENT 'Ações sugeridas extraídas da resposta',
    tokens_used INT DEFAULT NULL COMMENT 'Tokens consumidos da OpenAI API',
    processing_time_ms INT DEFAULT NULL COMMENT 'Tempo de processamento em ms',
    feedback ENUM('positive', 'negative', 'neutral') DEFAULT NULL COMMENT 'Feedback do usuário',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_account_id (account_id),
    INDEX idx_conversation_id (conversation_id),
    INDEX idx_created_at (created_at),
    INDEX idx_account_conversation (account_id, conversation_id),
    INDEX idx_feedback (feedback)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comentários nas colunas
ALTER TABLE chatbot_conversations COMMENT = 'Histórico de conversas com o assistente de IA';

-- =====================================================================
-- Conversation Sessions Table (opcional - para analytics)
-- =====================================================================

CREATE TABLE IF NOT EXISTS chatbot_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    conversation_id VARCHAR(64) NOT NULL UNIQUE,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    message_count INT DEFAULT 0,
    total_tokens INT DEFAULT 0,
    avg_response_time_ms INT DEFAULT NULL,
    satisfaction_score DECIMAL(3,2) DEFAULT NULL COMMENT '0.00-1.00',
    
    INDEX idx_account_id (account_id),
    INDEX idx_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE chatbot_sessions COMMENT = 'Sessões de conversa agrupadas para analytics';
