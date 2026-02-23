-- Migration: Normalizar sources de sugestões da Ficha Técnica
-- Data: 2026-01-29
-- Descrição: Unifica as fontes de sugestões para usar nomenclatura canônica

-- 1. Primeiro, expandir ENUM para incluir todas as fontes canônicas
-- (MySQL requer recriar o ENUM com todos os valores)
ALTER TABLE tech_sheet_suggestions 
MODIFY COLUMN source VARCHAR(50) DEFAULT 'inference';

-- 2. Normalizar 'title_extraction' -> 'title'
UPDATE tech_sheet_suggestions 
SET source = 'title', 
    meta = JSON_SET(COALESCE(meta, '{}'), '$.original_source', 'title_extraction')
WHERE source = 'title_extraction';

-- 3. Normalizar 'competitor' -> 'benchmark' (nomenclatura canônica)
UPDATE tech_sheet_suggestions 
SET source = 'benchmark', 
    meta = JSON_SET(COALESCE(meta, '{}'), '$.original_source', 'competitor')
WHERE source = 'competitor';

-- 4. Normalizar valores legados não mapeados -> 'inference'
UPDATE tech_sheet_suggestions 
SET source = 'inference', 
    meta = JSON_SET(COALESCE(meta, '{}'), '$.original_source', source)
WHERE source NOT IN ('title', 'benchmark', 'ai', 'inference', 'default', 'manual')
  AND source IS NOT NULL;

-- 5. Adicionar índice composto para performance de queries no auto-optimize
-- (usa IGNORE para não falhar se já existir)
ALTER TABLE tech_sheet_suggestions 
ADD INDEX idx_auto_optimize (account_id, status, confidence, source);

