-- Tabela de hierarquia de sinônimos
CREATE TABLE IF NOT EXISTS seo_synonym_hierarchy (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id VARCHAR(20) NOT NULL,
    level ENUM('nivel_1', 'nivel_2', 'nivel_3', 'nivel_4') NOT NULL,
    word VARCHAR(100) NOT NULL,
    weight DECIMAL(3,2) DEFAULT 1.00,
    destination ENUM('title', 'model', 'description', 'keywords') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_category_level_word (category_id, level, word),
    INDEX idx_category (category_id),
    INDEX idx_level (level),
    INDEX idx_word (word)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de contextos de uso
CREATE TABLE IF NOT EXISTS seo_use_contexts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id VARCHAR(20) NOT NULL,
    context_type VARCHAR(50) NOT NULL,
    keyword VARCHAR(100) NOT NULL,
    weight DECIMAL(3,2) DEFAULT 1.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_category_context_keyword (category_id, context_type, keyword),
    INDEX idx_category_context (category_id, context_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DADOS PILOTO: Categoria Baús/Bagageiros (MLB3530)
-- NOTA: Estes são dados de exemplo para a categoria piloto.
-- Para outras categorias, o sistema gera automaticamente via AI + ML API.
-- ============================================================================
INSERT INTO seo_synonym_hierarchy (category_id, level, word, weight, destination) VALUES
-- Nível 1 - Genérico (TÍTULO)
('MLB3530', 'nivel_1', 'bauleto', 1.00, 'title'),
('MLB3530', 'nivel_1', 'baú', 1.00, 'title'),
('MLB3530', 'nivel_1', 'bagageiro', 1.00, 'title'),
('MLB3530', 'nivel_1', 'maleiro', 1.00, 'title'),
-- Nível 2 - Qualificado (MODELO)
('MLB3530', 'nivel_2', 'bau traseiro', 0.80, 'model'),
('MLB3530', 'nivel_2', 'porta objetos', 0.80, 'model'),
('MLB3530', 'nivel_2', 'caixa traseira', 0.80, 'model'),
('MLB3530', 'nivel_2', 'compartimento', 0.80, 'model'),
-- Nível 3 - Contexto (MODELO + DESCRIÇÃO)
('MLB3530', 'nivel_3', 'bau moto', 0.60, 'model'),
('MLB3530', 'nivel_3', 'bagageiro motocicleta', 0.60, 'model'),
('MLB3530', 'nivel_3', 'maleiro delivery', 0.60, 'description'),
-- Nível 4 - Long Tail (DESCRIÇÃO + KEYWORDS)
('MLB3530', 'nivel_4', 'bauleto para motoboy', 0.40, 'description'),
('MLB3530', 'nivel_4', 'baú entrega delivery', 0.40, 'keywords'),
('MLB3530', 'nivel_4', 'bagageiro viagem', 0.40, 'description');

-- Contextos de uso
INSERT INTO seo_use_contexts (category_id, context_type, keyword, weight) VALUES
('MLB3530', 'profissional', 'delivery', 1.20),
('MLB3530', 'profissional', 'motoboy', 1.20),
('MLB3530', 'profissional', 'entrega', 1.10),
('MLB3530', 'profissional', 'trabalho', 1.00),
('MLB3530', 'lazer', 'viagem', 1.00),
('MLB3530', 'lazer', 'passeio', 0.90),
('MLB3530', 'lazer', 'turismo', 0.90),
('MLB3530', 'urbano', 'cidade', 0.90),
('MLB3530', 'urbano', 'dia a dia', 0.90),
('MLB3530', 'carga', 'capacete', 1.10),
('MLB3530', 'carga', 'transporte', 1.00);