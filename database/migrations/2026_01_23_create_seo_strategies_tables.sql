-- ============================================================================
-- Migration: Criação das tabelas para Sistema SEO Strategies
-- Data: 2026-01-23
-- Fase: 1 - Fundação (Sinônimos + Score Semântico)
-- NOTA: seo_synonym_hierarchy e seo_use_contexts já criadas em 2026_01_22
-- ============================================================================

-- Garantir coluna source em seo_synonym_hierarchy (adicionada no schema canônico 2026_01_22)
-- Se a tabela foi criada antes da consolidação, essa coluna pode faltar
ALTER TABLE seo_synonym_hierarchy ADD COLUMN IF NOT EXISTS source ENUM('manual', 'ai', 'ml_api', 'imported') DEFAULT 'manual' COMMENT 'Origem do dado' AFTER is_active;

-- ============================================================================
-- TABELA 3: Cache de Keywords
-- Armazena cache de keywords da arquitetura híbrida
-- ============================================================================
CREATE TABLE IF NOT EXISTS seo_keyword_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id VARCHAR(20) NOT NULL,
    base_keyword VARCHAR(255) NOT NULL COMMENT 'Keyword base da busca',
    keyword VARCHAR(255) NOT NULL COMMENT 'Keyword encontrada',
    type ENUM('core', 'suporte', 'tecnica', 'contexto', 'trending', 'autocomplete', 'competitor') DEFAULT 'core',
    weight DECIMAL(3,2) DEFAULT 1.00,
    source ENUM('database', 'ml_api', 'ai', 'unknown') DEFAULT 'unknown',
    is_valid BOOLEAN DEFAULT TRUE,
    expires_at TIMESTAMP NULL COMMENT 'Quando o cache expira',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Índices
    UNIQUE KEY uk_category_base_keyword (category_id, base_keyword(100), keyword(100)),
    INDEX idx_category (category_id),
    INDEX idx_valid_expires (is_valid, expires_at),
    INDEX idx_type (type),
    INDEX idx_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Cache de keywords da arquitetura híbrida';

-- ============================================================================
-- TABELA 4: Performance de Keywords
-- Armazena histórico de performance para cálculo de scores
-- ============================================================================
CREATE TABLE IF NOT EXISTS seo_keyword_performance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id VARCHAR(20) NOT NULL,
    keyword VARCHAR(255) NOT NULL,
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    conversions INT DEFAULT 0,
    click_rate DECIMAL(5,2) DEFAULT 0.00 COMMENT 'CTR em %',
    conversion_rate DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Taxa de conversão em %',
    recorded_at DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Índices
    UNIQUE KEY uk_category_keyword_date (category_id, keyword(100), recorded_at),
    INDEX idx_category (category_id),
    INDEX idx_recorded_at (recorded_at),
    INDEX idx_performance (click_rate, conversion_rate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Performance histórica de keywords para scoring';

-- ============================================================================
-- TABELA 5: Configurações por Categoria
-- Armazena configurações customizadas de hierarquia por categoria
-- ============================================================================
CREATE TABLE IF NOT EXISTS seo_category_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id VARCHAR(20) NOT NULL,
    config_key VARCHAR(100) NOT NULL COMMENT 'Chave da configuração',
    config_value JSON NOT NULL COMMENT 'Valor em JSON',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Índices
    UNIQUE KEY uk_category_key (category_id, config_key),
    INDEX idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Configurações customizadas de SEO por categoria';

-- ============================================================================
-- DADOS PILOTO: Categoria Baús/Bagageiros (MLB3530)
-- NOTA: Estes são dados de exemplo para a categoria piloto.
-- Para outras categorias, o sistema gera automaticamente via AI + ML API.
-- ============================================================================

-- Nível 1 - Genérico (TÍTULO)
INSERT INTO seo_synonym_hierarchy (category_id, level, word, weight, destination, source) VALUES
('MLB3530', 'nivel_1', 'bauleto', 1.00, 'title', 'manual'),
('MLB3530', 'nivel_1', 'baú', 1.00, 'title', 'manual'),
('MLB3530', 'nivel_1', 'bagageiro', 1.00, 'title', 'manual'),
('MLB3530', 'nivel_1', 'maleiro', 0.90, 'title', 'manual'),
('MLB3530', 'nivel_1', 'caixa', 0.80, 'title', 'manual'),
('MLB3530', 'nivel_1', 'top case', 0.85, 'title', 'manual')
ON DUPLICATE KEY UPDATE weight = VALUES(weight), updated_at = NOW();

-- Nível 2 - Qualificado (MODELO)
INSERT INTO seo_synonym_hierarchy (category_id, level, word, weight, destination, source) VALUES
('MLB3530', 'nivel_2', 'bau traseiro', 0.90, 'model', 'manual'),
('MLB3530', 'nivel_2', 'porta objetos', 0.85, 'model', 'manual'),
('MLB3530', 'nivel_2', 'caixa traseira', 0.85, 'model', 'manual'),
('MLB3530', 'nivel_2', 'compartimento moto', 0.80, 'model', 'manual'),
('MLB3530', 'nivel_2', 'bauleto universal', 0.90, 'model', 'manual'),
('MLB3530', 'nivel_2', 'bagageiro moto', 0.85, 'model', 'manual')
ON DUPLICATE KEY UPDATE weight = VALUES(weight), updated_at = NOW();

-- Nível 3 - Contexto (MODELO + DESCRIÇÃO)
INSERT INTO seo_synonym_hierarchy (category_id, level, word, weight, destination, source) VALUES
('MLB3530', 'nivel_3', 'bau moto delivery', 0.80, 'description', 'manual'),
('MLB3530', 'nivel_3', 'bagageiro para motoboy', 0.80, 'description', 'manual'),
('MLB3530', 'nivel_3', 'maleiro motocicleta', 0.75, 'description', 'manual'),
('MLB3530', 'nivel_3', 'bauleto para viagem', 0.75, 'description', 'manual'),
('MLB3530', 'nivel_3', 'caixa transporte moto', 0.70, 'description', 'manual')
ON DUPLICATE KEY UPDATE weight = VALUES(weight), updated_at = NOW();

-- Nível 4 - Long Tail (DESCRIÇÃO + KEYWORDS)
INSERT INTO seo_synonym_hierarchy (category_id, level, word, weight, destination, source) VALUES
('MLB3530', 'nivel_4', 'bauleto para motoboy entrega delivery', 0.70, 'keywords', 'manual'),
('MLB3530', 'nivel_4', 'baú entrega delivery moto', 0.70, 'keywords', 'manual'),
('MLB3530', 'nivel_4', 'bagageiro viagem longa distância', 0.65, 'keywords', 'manual'),
('MLB3530', 'nivel_4', 'bauleto 41 litros cabe capacete', 0.75, 'keywords', 'manual'),
('MLB3530', 'nivel_4', 'top case moto universal honda yamaha', 0.70, 'keywords', 'manual')
ON DUPLICATE KEY UPDATE weight = VALUES(weight), updated_at = NOW();

-- Contextos de uso para MLB3530
INSERT INTO seo_use_contexts (category_id, context_type, keyword, weight) VALUES
('MLB3530', 'profissional', 'delivery', 1.20),
('MLB3530', 'profissional', 'motoboy', 1.20),
('MLB3530', 'profissional', 'entrega', 1.15),
('MLB3530', 'profissional', 'trabalho', 1.10),
('MLB3530', 'profissional', 'ifood', 1.15),
('MLB3530', 'profissional', 'uber eats', 1.10),
('MLB3530', 'profissional', 'rappi', 1.10),
('MLB3530', 'lazer', 'viagem', 1.00),
('MLB3530', 'lazer', 'passeio', 0.95),
('MLB3530', 'lazer', 'turismo', 0.95),
('MLB3530', 'lazer', 'trilha', 0.90),
('MLB3530', 'urbano', 'cidade', 0.90),
('MLB3530', 'urbano', 'dia a dia', 0.90),
('MLB3530', 'urbano', 'diário', 0.85),
('MLB3530', 'carga', 'capacete', 1.15),
('MLB3530', 'carga', 'transporte', 1.00),
('MLB3530', 'carga', 'bagagem', 1.00),
('MLB3530', 'carga', 'compras', 0.90)
ON DUPLICATE KEY UPDATE weight = VALUES(weight);

-- ============================================================================
-- VIEWS ÚTEIS
-- ============================================================================

-- View: Resumo de sinônimos por categoria
CREATE OR REPLACE VIEW v_seo_synonym_summary AS
SELECT
    category_id,
    level,
    COUNT(*) as total_synonyms,
    AVG(weight) as avg_weight,
    destination
FROM seo_synonym_hierarchy
WHERE is_active = 1
GROUP BY category_id, level, destination;

-- View: Keywords ativas do cache
CREATE OR REPLACE VIEW v_seo_active_keywords AS
SELECT
    category_id,
    keyword,
    type,
    weight,
    source,
    expires_at
FROM seo_keyword_cache
WHERE is_valid = 1
AND (expires_at IS NULL OR expires_at > NOW());

-- ============================================================================
-- FIM DA MIGRATION
-- ============================================================================
