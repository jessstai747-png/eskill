-- ============================================================================
-- Migration: Create ml_questions table
-- Date: 2026-02-16
-- Description: Formal migration for ml_questions table previously created
--              inline in bin/migrate_questions.php
--              Referenced by MLAnalyticsIntelligenceService, QABotService
-- ============================================================================

CREATE TABLE IF NOT EXISTS ml_questions (
    question_id BIGINT PRIMARY KEY COMMENT 'ML question ID',
    account_id INT NULL COMMENT 'References ml_accounts.id',
    seller_id BIGINT NULL COMMENT 'ML seller ID',
    item_id VARCHAR(50) NULL COMMENT 'ML item ID (e.g., MLB123456)',
    status VARCHAR(50) NULL COMMENT 'UNANSWERED, ANSWERED, CLOSED_UNANSWERED, etc.',
    question_text TEXT NULL,
    answer_text TEXT NULL,
    from_user_id BIGINT NULL COMMENT 'ML user ID who asked the question',
    date_created DATETIME NULL,
    answer_date DATETIME NULL,
    updated_at DATETIME NULL,

    -- AI columns (added by bin/migrate_questions.php)
    ai_draft TEXT NULL COMMENT 'AI-generated draft answer',
    sentiment VARCHAR(20) NULL COMMENT 'positive, negative, neutral',
    intent VARCHAR(50) NULL COMMENT 'price_inquiry, stock_check, shipping, etc.',
    urgency INT DEFAULT 0 COMMENT 'Urgency score 0-10',
    confidence_score INT DEFAULT 0 COMMENT 'AI confidence 0-100',

    INDEX idx_status (status),
    INDEX idx_item (item_id),
    INDEX idx_account (account_id),
    INDEX idx_seller (seller_id),
    INDEX idx_from_user (from_user_id),
    INDEX idx_date_created (date_created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
