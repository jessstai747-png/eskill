-- Tabela de pagamentos sincronizados via webhook do Mercado Livre
-- Populated by MercadoLivreWebhookService::persistPayment()
-- Topic: payment / payments  |  Resource: /collections/{paymentId}
CREATE TABLE IF NOT EXISTS ml_payments (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    ml_account_id   INT              NOT NULL,
    payment_id      VARCHAR(64)      NOT NULL,
    order_id        VARCHAR(64)          NULL DEFAULT NULL,
    status          VARCHAR(50)          NULL DEFAULT NULL,
    amount          DECIMAL(12,2)        NULL DEFAULT NULL,
    currency_id     VARCHAR(10)          NULL DEFAULT NULL,
    payment_method  VARCHAR(50)          NULL DEFAULT NULL,
    data            JSON                 NULL COMMENT 'Raw ML API payload',
    paid_at         DATETIME             NULL DEFAULT NULL,
    created_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_account_payment (ml_account_id, payment_id),
    INDEX idx_order_id   (order_id),
    INDEX idx_status     (status),
    INDEX idx_paid_at    (paid_at),
    CONSTRAINT fk_ml_payments_account
        FOREIGN KEY (ml_account_id) REFERENCES ml_accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
