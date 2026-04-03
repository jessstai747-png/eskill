-- ============================================================================
-- Migration: Adicionar updated_at faltando em tabelas operacionais
-- Data: 2026-04-01
-- Descrição: Padroniza timestamps em tabelas que tinham apenas created_at
-- ============================================================================

-- autopilot_execution_sessions: tabela operacional que muda de status
ALTER TABLE autopilot_execution_sessions
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- autopilot_execution_records: tabela operacional que muda de status
ALTER TABLE autopilot_execution_records
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- performance_monitoring_schedule: tabela operacional que muda de status
ALTER TABLE performance_monitoring_schedule
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- autopilot_cycles: tabela operacional que muda de status
ALTER TABLE autopilot_cycles
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER started_at;

-- active_optimizations: tabela operacional que muda de status e progresso
ALTER TABLE active_optimizations
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER started_at;

-- market_intelligence: padronizar last_updated → manter e adicionar created_at
ALTER TABLE market_intelligence
    ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER confidence;

-- model_performance: padronizar last_updated → manter e adicionar created_at
ALTER TABLE model_performance
    ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER validation_accuracy;

-- performance_metrics: já tem created_at e não tem market_share — ignorado
-- ADD COLUMN created_at omitido (coluna já existe)

-- competitor_alerts: adicionar updated_at (tabela que muda is_read/read_at)
ALTER TABLE competitor_alerts
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- seo_use_contexts: adicionar updated_at (se faltava na versão original)
ALTER TABLE seo_use_contexts
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- NOTA: Tabelas de log puro (optimization_change_log, autopilot_execution_errors,
-- learning_insights) intencionalmente sem updated_at — são append-only.
