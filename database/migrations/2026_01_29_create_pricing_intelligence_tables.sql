-- =====================================================
-- MÓDULO DE PRECIFICAÇÃO INTELIGENTE
-- Criado em: 2026-01-29
-- Descrição: Tabelas para gestão de preços e margens
-- =====================================================

-- Tabela principal de custos por produto/SKU
CREATE TABLE IF NOT EXISTS product_costs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL COMMENT 'MLB ID do anúncio',
    sku VARCHAR(100) COMMENT 'SKU interno do produto',
    
    -- Custos diretos
    custo_producao DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Custo de aquisição/produção',
    custo_embalagem DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Embalagem por unidade',
    custo_etiqueta DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Etiqueta/tag',
    custo_frete_entrada DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Frete de fornecedor',
    
    -- Custos variáveis ML
    taxa_comissao_ml DECIMAL(5,2) DEFAULT 0.00 COMMENT '% comissão ML (varia por categoria)',
    taxa_imposto DECIMAL(5,2) DEFAULT 0.00 COMMENT '% impostos (Simples/Presumido)',
    acos_medio DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Custo médio de Ads %',
    custo_frete_gratis DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Custo de frete grátis assumido',
    
    -- Configurações de margem
    margem_minima DECIMAL(5,2) DEFAULT 10.00 COMMENT 'Margem mínima aceitável %',
    margem_alvo DECIMAL(5,2) DEFAULT 20.00 COMMENT 'Margem alvo desejada %',
    
    -- Campos calculados (atualizados por trigger/service)
    preco_minimo_calculado DECIMAL(12,2) COMMENT 'Preço mínimo para margem mínima',
    preco_alvo_calculado DECIMAL(12,2) COMMENT 'Preço para atingir margem alvo',
    
    -- Metadados
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_account_item (account_id, item_id),
    INDEX idx_account_sku (account_id, sku),
    INDEX idx_item_id (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Histórico de alterações de preços
CREATE TABLE IF NOT EXISTS pricing_history (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    
    -- Preços
    preco_anterior DECIMAL(12,2) NOT NULL,
    preco_novo DECIMAL(12,2) NOT NULL,
    percentual_mudanca DECIMAL(6,2) NOT NULL,
    
    -- Contexto da mudança
    origem ENUM('manual', 'auto', 'promocao', 'concorrencia', 'demanda', 'liquidacao') DEFAULT 'manual',
    motivo VARCHAR(255) COMMENT 'Razão da alteração',
    estrategia_usada VARCHAR(50) COMMENT 'Estratégia aplicada',
    
    -- Dados de concorrência no momento
    preco_concorrente_min DECIMAL(12,2) COMMENT 'Menor preço concorrente',
    preco_concorrente_medio DECIMAL(12,2) COMMENT 'Preço médio concorrentes',
    qtd_concorrentes INT DEFAULT 0,
    
    -- Margem calculada
    margem_anterior DECIMAL(6,2) COMMENT 'Margem antes da mudança %',
    margem_nova DECIMAL(6,2) COMMENT 'Margem após mudança %',
    lucro_unitario_novo DECIMAL(12,2) COMMENT 'Lucro por unidade R$',
    
    -- Impacto (preenchido depois)
    vendas_antes_7d INT COMMENT 'Vendas nos 7 dias anteriores',
    vendas_depois_7d INT COMMENT 'Vendas nos 7 dias posteriores',
    visitas_antes_7d INT,
    visitas_depois_7d INT,
    
    -- Alerta de ranking
    alerta_ranking ENUM('verde', 'amarelo', 'vermelho') DEFAULT 'verde',
    
    data_mudanca TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_account_item (account_id, item_id),
    INDEX idx_data (data_mudanca),
    INDEX idx_origem (origem),
    INDEX idx_account_date (account_id, data_mudanca)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Regras de precificação automática por conta/categoria
CREATE TABLE IF NOT EXISTS pricing_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    
    -- Escopo da regra
    aplica_categoria VARCHAR(50) COMMENT 'Categoria específica ou NULL = todas',
    aplica_marca VARCHAR(100) COMMENT 'Marca específica ou NULL = todas',
    aplica_item_ids JSON COMMENT 'Array de item_ids específicos',
    
    -- Tipo de estratégia
    estrategia ENUM('competitivo', 'agressivo', 'premium', 'valor', 'liquidacao', 'custom') NOT NULL DEFAULT 'competitivo',
    
    -- Parâmetros da regra
    margem_minima DECIMAL(5,2) DEFAULT 10.00,
    margem_alvo DECIMAL(5,2) DEFAULT 20.00,
    desconto_maximo DECIMAL(5,2) DEFAULT 30.00 COMMENT 'Máximo desconto permitido %',
    aumento_maximo DECIMAL(5,2) DEFAULT 15.00 COMMENT 'Máximo aumento de uma vez %',
    
    -- Limites de ranking (evitar penalização)
    limite_aumento_ranking DECIMAL(5,2) DEFAULT 8.00 COMMENT 'Aumento máximo sem impactar rank %',
    
    -- Automação
    ativo TINYINT(1) DEFAULT 1,
    execucao_automatica TINYINT(1) DEFAULT 0 COMMENT 'Aplicar preço automaticamente',
    intervalo_verificacao INT DEFAULT 24 COMMENT 'Horas entre verificações',
    ultima_execucao TIMESTAMP NULL,
    
    -- Metadados
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_account (account_id),
    INDEX idx_ativo (ativo),
    INDEX idx_categoria (aplica_categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Análise de concorrentes armazenada (cache)
CREATE TABLE IF NOT EXISTS competitor_pricing_cache (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    category_id VARCHAR(50) NOT NULL,
    
    -- Dados de mercado
    preco_minimo DECIMAL(12,2),
    preco_maximo DECIMAL(12,2),
    preco_medio DECIMAL(12,2),
    preco_mediano DECIMAL(12,2),
    qtd_concorrentes INT DEFAULT 0,
    
    -- Top concorrentes (JSON com array)
    top_concorrentes JSON COMMENT '[{id, titulo, preco, vendedor, reputacao}]',
    
    -- Posição do nosso produto
    nossa_posicao_preco INT COMMENT 'Ranking de preço (1 = mais barato)',
    percentil_preco DECIMAL(5,2) COMMENT 'Percentil de preço (0-100)',
    
    -- Tendência
    tendencia_7d DECIMAL(6,2) COMMENT 'Variação média de preço 7 dias %',
    tendencia_30d DECIMAL(6,2) COMMENT 'Variação média de preço 30 dias %',
    
    -- Cache control
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expira_em TIMESTAMP NULL,
    
    UNIQUE KEY uk_account_item (account_id, item_id),
    INDEX idx_category (category_id),
    INDEX idx_expira (expira_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Simulações de promoção salvas
CREATE TABLE IF NOT EXISTS promotion_simulations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    
    -- Dados do anúncio no momento
    preco_original DECIMAL(12,2) NOT NULL,
    titulo VARCHAR(255),
    
    -- Simulação
    desconto_percentual DECIMAL(5,2) NOT NULL,
    preco_promocional DECIMAL(12,2) NOT NULL,
    
    -- Breakdown financeiro
    custo_total DECIMAL(12,2),
    margem_promocao DECIMAL(6,2),
    lucro_unitario_promocao DECIMAL(12,2),
    
    -- Análise de viabilidade
    desconto_maximo_seguro DECIMAL(5,2) COMMENT 'Desconto máximo mantendo margem 5%',
    viavel TINYINT(1) DEFAULT 1,
    alerta VARCHAR(255),
    
    -- Projeções
    vendas_estimadas_aumento INT COMMENT 'Aumento estimado de vendas %',
    receita_projetada DECIMAL(14,2),
    lucro_projetado DECIMAL(14,2),
    
    -- Status
    aplicada TINYINT(1) DEFAULT 0,
    aplicada_em TIMESTAMP NULL,
    
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_account_item (account_id, item_id),
    INDEX idx_account_date (account_id, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Alertas de ranqueamento
CREATE TABLE IF NOT EXISTS pricing_ranking_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    
    -- Alerta
    tipo_alerta ENUM('aumento_preco', 'queda_vendas', 'perda_posicao', 'concorrente_agressivo') NOT NULL,
    nivel ENUM('verde', 'amarelo', 'vermelho') NOT NULL,
    mensagem TEXT NOT NULL,
    
    -- Contexto
    preco_atual DECIMAL(12,2),
    preco_recomendado DECIMAL(12,2),
    variacao_detectada DECIMAL(6,2),
    
    -- Status
    lido TINYINT(1) DEFAULT 0,
    acao_tomada VARCHAR(255),
    resolvido TINYINT(1) DEFAULT 0,
    resolvido_em TIMESTAMP NULL,
    
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_account (account_id),
    INDEX idx_account_item (account_id, item_id),
    INDEX idx_nivel (nivel),
    INDEX idx_nao_lido (account_id, lido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Taxas de comissão do Mercado Livre por categoria (cache)
CREATE TABLE IF NOT EXISTS ml_category_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id VARCHAR(50) NOT NULL,
    category_name VARCHAR(255),
    
    -- Taxas
    taxa_classico DECIMAL(5,2) NOT NULL COMMENT '% comissão clássico',
    taxa_premium DECIMAL(5,2) NOT NULL COMMENT '% comissão premium',
    taxa_full DECIMAL(5,2) COMMENT '% comissão Full (se diferente)',
    
    -- Frete grátis threshold
    frete_gratis_min DECIMAL(12,2) COMMENT 'Valor mínimo para frete grátis',
    
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Inserir algumas taxas padrão do ML (2026)
INSERT INTO ml_category_fees (category_id, category_name, taxa_classico, taxa_premium, frete_gratis_min) VALUES
('MLB1648', 'Computação', 14.00, 17.00, 79.00),
('MLB1051', 'Celulares e Telefones', 13.00, 16.00, 79.00),
('MLB1144', 'Games', 14.00, 17.00, 79.00),
('MLB1000', 'Eletrônicos, Áudio e Vídeo', 14.00, 17.00, 79.00),
('MLB1574', 'Casa, Móveis e Decoração', 16.00, 19.00, 79.00),
('MLB1276', 'Esportes e Fitness', 16.00, 19.00, 79.00),
('MLB1168', 'Música, Filmes e Seriados', 16.00, 19.00, 79.00),
('MLB1132', 'Brinquedos e Hobbies', 16.00, 19.00, 79.00),
('MLB1196', 'Beleza e Cuidado Pessoal', 16.00, 19.00, 79.00),
('MLB1953', 'Mais Categorias', 16.00, 19.00, 79.00)
ON DUPLICATE KEY UPDATE 
    taxa_classico = VALUES(taxa_classico),
    taxa_premium = VALUES(taxa_premium);
