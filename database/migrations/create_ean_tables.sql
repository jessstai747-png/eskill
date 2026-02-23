-- =====================================================
-- Sistema de Venda de EANs por Pacotes
-- Migration: create_ean_tables.sql
-- Data: 2024-12-22
-- =====================================================

-- Pacotes disponíveis para venda
CREATE TABLE IF NOT EXISTS ean_packages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    price_per_ean DECIMAL(10,2) NOT NULL,
    discount_percent INT DEFAULT 0,
    description TEXT,
    badge VARCHAR(50),
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estoque de EANs (comprados do fornecedor)
CREATE TABLE IF NOT EXISTS ean_inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ean VARCHAR(13) NOT NULL UNIQUE,
    status ENUM('available', 'reserved', 'sold') DEFAULT 'available',
    purchase_batch VARCHAR(50),
    cost DECIMAL(10,2) DEFAULT 0,
    supplier VARCHAR(100),
    notes TEXT,
    reserved_at TIMESTAMP NULL,
    sold_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_batch (purchase_batch)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compras de pacotes pelos sellers
CREATE TABLE IF NOT EXISTS ean_purchases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    package_id INT,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    discount_applied DECIMAL(10,2) DEFAULT 0,
    payment_method ENUM('pix', 'credit_card', 'boleto', 'mercado_pago') DEFAULT 'pix',
    payment_status ENUM('pending', 'processing', 'paid', 'failed', 'refunded', 'cancelled') DEFAULT 'pending',
    payment_id VARCHAR(100),
    payment_external_id VARCHAR(100),
    payment_url TEXT,
    payment_qr_code TEXT,
    payment_qr_code_base64 LONGTEXT,
    payment_expires_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES ean_packages(id) ON DELETE SET NULL,
    INDEX idx_account (account_id),
    INDEX idx_status (payment_status),
    INDEX idx_payment_id (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- EANs atribuídos aos sellers
CREATE TABLE IF NOT EXISTS ean_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ean_id INT NOT NULL,
    account_id INT NOT NULL,
    purchase_id INT,
    ml_item_id VARCHAR(50),
    product_title VARCHAR(200),
    product_sku VARCHAR(100),
    category_id VARCHAR(50),
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ean_id) REFERENCES ean_inventory(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (purchase_id) REFERENCES ean_purchases(id) ON DELETE SET NULL,
    UNIQUE KEY unique_ean (ean_id),
    INDEX idx_account (account_id),
    INDEX idx_ml_item (ml_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de saldo de EANs por seller (cache para performance)
CREATE TABLE IF NOT EXISTS ean_balances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL UNIQUE,
    total_purchased INT DEFAULT 0,
    total_used INT DEFAULT 0,
    available INT DEFAULT 0,
    last_purchase_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log de transações de EAN
CREATE TABLE IF NOT EXISTS ean_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    type ENUM('credit', 'debit', 'refund', 'adjustment') NOT NULL,
    quantity INT NOT NULL,
    balance_before INT NOT NULL,
    balance_after INT NOT NULL,
    reference_type VARCHAR(50),
    reference_id INT,
    description TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE,
    INDEX idx_account (account_id),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurações do sistema de EAN
CREATE TABLE IF NOT EXISTS ean_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'int', 'float', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Dados iniciais
-- =====================================================

-- Pacotes padrão
INSERT INTO ean_packages (name, slug, quantity, price, price_per_ean, discount_percent, description, badge, is_featured, sort_order) VALUES
('Starter', 'starter', 10, 149.00, 14.90, 0, 'Ideal para quem está começando. 10 códigos EAN válidos.', NULL, FALSE, 1),
('Basic', 'basic', 50, 499.00, 9.98, 33, 'Para sellers em crescimento. 50 códigos com 33% de desconto.', 'Popular', TRUE, 2),
('Pro', 'pro', 100, 799.00, 7.99, 46, 'Para operações profissionais. 100 códigos com 46% de desconto.', NULL, FALSE, 3),
('Business', 'business', 500, 2999.00, 6.00, 60, 'Para grandes operações. 500 códigos com 60% de desconto.', 'Melhor Custo', TRUE, 4),
('Enterprise', 'enterprise', 1000, 4999.00, 5.00, 66, 'Para empresas. 1000 códigos com 66% de desconto.', NULL, FALSE, 5),
('Unlimited', 'unlimited', 5000, 19999.00, 4.00, 73, 'Volume máximo. 5000 códigos com 73% de desconto.', 'Volume', FALSE, 6);

-- Configurações iniciais
INSERT INTO ean_settings (setting_key, setting_value, setting_type, description) VALUES
('mp_access_token', '', 'string', 'Access Token do Mercado Pago'),
('mp_public_key', '', 'string', 'Public Key do Mercado Pago'),
('mp_webhook_secret', '', 'string', 'Secret para validar webhooks'),
('pix_expiration_minutes', '30', 'int', 'Tempo de expiração do PIX em minutos'),
('notify_low_stock', '100', 'int', 'Notificar quando estoque abaixo de X'),
('auto_assign_on_listing', 'true', 'boolean', 'Atribuir EAN automaticamente ao criar anúncio'),
('allow_ean_return', 'false', 'boolean', 'Permitir devolução de EAN não usado');

-- =====================================================
-- Views úteis
-- =====================================================

-- View de saldo consolidado
CREATE OR REPLACE VIEW vw_ean_balance_summary AS
SELECT 
    a.id as account_id,
    a.nickname,
    COALESCE(b.total_purchased, 0) as total_purchased,
    COALESCE(b.total_used, 0) as total_used,
    COALESCE(b.available, 0) as available,
    b.last_purchase_at
FROM ml_accounts a
LEFT JOIN ean_balances b ON a.id = b.account_id;

-- View de vendas por período
CREATE OR REPLACE VIEW vw_ean_sales_summary AS
SELECT 
    DATE(created_at) as sale_date,
    COUNT(*) as total_orders,
    SUM(quantity) as total_eans,
    SUM(total_amount) as total_revenue,
    SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as paid_revenue
FROM ean_purchases
GROUP BY DATE(created_at);

-- View de estoque
CREATE OR REPLACE VIEW vw_ean_inventory_summary AS
SELECT 
    status,
    COUNT(*) as quantity,
    purchase_batch
FROM ean_inventory
GROUP BY status, purchase_batch;
