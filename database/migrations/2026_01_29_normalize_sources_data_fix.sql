-- Migration: Normalizar sources legados para valores canônicos
-- Data: 2026-01-29
-- Descrição: Atualiza sources legados (title_extraction, competitor, etc) para valores canônicos

-- 1. Normalizar title_extraction -> title
UPDATE tech_sheet_suggestions 
SET 
    source = 'title',
    meta = JSON_SET(COALESCE(meta, '{}'), '$.legacy_source', 'title_extraction')
WHERE source = 'title_extraction';

-- 2. Normalizar competitor -> benchmark
UPDATE tech_sheet_suggestions 
SET 
    source = 'benchmark',
    meta = JSON_SET(COALESCE(meta, '{}'), '$.legacy_source', 'competitor')
WHERE source = 'competitor';

-- 3. Normalizar outras fontes legadas para inference (com preservação do tipo original)
-- search_strategy, autocomplete, trends, history, description -> inference

UPDATE tech_sheet_suggestions 
SET 
    source = 'inference',
    meta = JSON_SET(COALESCE(meta, '{}'), '$.legacy_source', source, '$.inference_type', source)
WHERE source IN ('search_strategy', 'autocomplete', 'trends', 'history', 'description');

-- 4. Verificar distribuição final (para auditoria)
-- SELECT source, COUNT(*) as count FROM tech_sheet_suggestions GROUP BY source ORDER BY count DESC;
