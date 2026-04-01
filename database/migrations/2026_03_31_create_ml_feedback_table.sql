-- Tabela de feedbacks/avaliações sincronizados via webhook do Mercado Livre
-- Populated by MercadoLivreWebhookService::persistFeedback()
-- Topic: feedback / created_in_feedback  |  Resource: /feedback/{feedbackId}
CREATE TABLE IF NOT EXISTS ml_feedback (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    ml_account_id   INT              NOT NULL,
    feedback_id     VARCHAR(64)      NOT NULL,
    order_id        VARCHAR(64)          NULL DEFAULT NULL,
    rating          TINYINT UNSIGNED     NULL DEFAULT NULL COMMENT '1-5 star rating from ML',
    message         TEXT                 NULL DEFAULT NULL,
    status          VARCHAR(50)          NULL DEFAULT NULL,
    fulfilled       TINYINT(1)           NULL DEFAULT NULL COMMENT '1 = buyer received item',
    data            JSON                 NULL COMMENT 'Raw ML API payload',
    feedback_date   DATETIME             NULL DEFAULT NULL,
    created_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_account_feedback (ml_account_id, feedback_id),
    INDEX idx_order_id      (order_id),
    INDEX idx_rating        (rating),
    INDEX idx_feedback_date (feedback_date),
    CONSTRAINT fk_ml_feedback_account
        FOREIGN KEY (ml_account_id) REFERENCES ml_accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
