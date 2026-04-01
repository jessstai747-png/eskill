-- ============================================================================
-- Migration: Padronizar colunas created_at/updated_at de DATETIME para TIMESTAMP
-- Data: 2026-04-01
-- Descrição: Todas as tabelas devem usar TIMESTAMP para consistência de timezone.
--            TIMESTAMP armazena em UTC e converte; DATETIME armazena literal.
-- Tabelas afetadas: competitor_tracking, competitor_alerts, competitor_alert_history,
--   pricing_rules, pricing_rule_executions, pricing_schedules, pricing_campaigns,
--   pricing_bulk_batches, pricing_analytics_snapshots, pricing_elasticity_data,
--   notification_channels, notification_subscriptions, push_notification_queue,
--   tech_sheet_execution_log, tech_sheet_scheduled_jobs, tech_sheet_webhook_configs,
--   tech_sheet_alert_rules, tech_sheet_alert_recipients, tech_sheet_alerts,
--   clone_progress_tracking, clone_progress_history, ml_questions
-- ============================================================================

SET @db = DATABASE();

-- ============================================================================
-- competitor_tracking (criada em 2025_01_01_000004)
-- ============================================================================
SET @col = (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='competitor_tracking' AND COLUMN_NAME='created_at');
SET @sql = IF(@col='datetime',
    'ALTER TABLE competitor_tracking MODIFY COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, MODIFY COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================================
-- competitor_alerts
-- ============================================================================
SET @col = (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='competitor_alerts' AND COLUMN_NAME='created_at');
SET @sql = IF(@col='datetime',
    'ALTER TABLE competitor_alerts MODIFY COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================================
-- competitor_alert_history
-- ============================================================================
SET @col = (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='competitor_alert_history' AND COLUMN_NAME='checked_at');
SET @sql = IF(@col='datetime',
    'ALTER TABLE competitor_alert_history MODIFY COLUMN checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================================
-- pricing_rules (criada em 2024_06_01 com DATETIME)
-- ============================================================================
SET @col = (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pricing_rules' AND COLUMN_NAME='created_at');
SET @sql = IF(@col='datetime',
    'ALTER TABLE pricing_rules MODIFY COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, MODIFY COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================================
-- pricing_rule_executions
-- ============================================================================
SET @col = (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pricing_rule_executions' AND COLUMN_NAME='executed_at');
SET @sql = IF(@col='datetime',
    'ALTER TABLE pricing_rule_executions MODIFY COLUMN executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================================
-- pricing_schedules
-- ============================================================================
SET @col = (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pricing_schedules' AND COLUMN_NAME='created_at');
SET @sql = IF(@col='datetime',
    'ALTER TABLE pricing_schedules MODIFY COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, MODIFY COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================================
-- pricing_campaigns
-- ============================================================================
SET @col = (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pricing_campaigns' AND COLUMN_NAME='created_at');
SET @sql = IF(@col='datetime',
    'ALTER TABLE pricing_campaigns MODIFY COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, MODIFY COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================================
-- pricing_bulk_batches
-- ============================================================================
SET @col = (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pricing_bulk_batches' AND COLUMN_NAME='created_at');
SET @sql = IF(@col='datetime',
    'ALTER TABLE pricing_bulk_batches MODIFY COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, MODIFY COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================================
-- notification_channels
-- ============================================================================
SET @col = (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='notification_channels' AND COLUMN_NAME='created_at');
SET @sql = IF(@col='datetime',
    'ALTER TABLE notification_channels MODIFY COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, MODIFY COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================================
-- notification_subscriptions
-- ============================================================================
SET @col = (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='notification_subscriptions' AND COLUMN_NAME='created_at');
SET @sql = IF(@col='datetime',
    'ALTER TABLE notification_subscriptions MODIFY COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================================
-- tech_sheet_execution_log
-- ============================================================================
SET @col = (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='tech_sheet_execution_log' AND COLUMN_NAME='created_at');
SET @sql = IF(@col='datetime',
    'ALTER TABLE tech_sheet_execution_log MODIFY COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, MODIFY COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================================
-- tech_sheet_scheduled_jobs
-- ============================================================================
SET @col = (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='tech_sheet_scheduled_jobs' AND COLUMN_NAME='created_at');
SET @sql = IF(@col='datetime',
    'ALTER TABLE tech_sheet_scheduled_jobs MODIFY COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, MODIFY COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================================
-- tech_sheet_webhook_configs
-- ============================================================================
SET @col = (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='tech_sheet_webhook_configs' AND COLUMN_NAME='created_at');
SET @sql = IF(@col='datetime',
    'ALTER TABLE tech_sheet_webhook_configs MODIFY COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, MODIFY COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================================
-- tech_sheet_alert_rules
-- ============================================================================
SET @col = (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='tech_sheet_alert_rules' AND COLUMN_NAME='created_at');
SET @sql = IF(@col='datetime',
    'ALTER TABLE tech_sheet_alert_rules MODIFY COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, MODIFY COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================================
-- clone_progress_tracking
-- ============================================================================
SET @col = (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='clone_progress_tracking' AND COLUMN_NAME='created_at');
SET @sql = IF(@col='datetime',
    'ALTER TABLE clone_progress_tracking MODIFY COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, MODIFY COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================================
-- clone_progress_history
-- ============================================================================
SET @col = (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='clone_progress_history' AND COLUMN_NAME='created_at');
SET @sql = IF(@col='datetime',
    'ALTER TABLE clone_progress_history MODIFY COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================================
-- ml_questions
-- ============================================================================
SET @col = (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ml_questions' AND COLUMN_NAME='created_at');
SET @sql = IF(@col='datetime',
    'ALTER TABLE ml_questions MODIFY COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, MODIFY COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
