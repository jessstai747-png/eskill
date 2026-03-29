-- =============================================================================
-- Migration: Add completed_with_errors status to catalog_clone_jobs
-- Description: Adds new status enum value and a dedicated batch_jobs view
-- Date: 2026-03-29
-- =============================================================================

-- Add completed_with_errors to catalog_clone_jobs.status enum
ALTER TABLE catalog_clone_jobs
    MODIFY COLUMN status ENUM(
        'pending',
        'queued',
        'processing',
        'completed',
        'completed_with_errors',
        'failed',
        'cancelled'
    ) NOT NULL DEFAULT 'pending';

-- Add source_account_nickname for display convenience (nullable, populated on job creation)
ALTER TABLE catalog_clone_jobs
    ADD COLUMN IF NOT EXISTS source_account_nickname VARCHAR(100) NULL
        COMMENT 'Nickname of source ML account for display' AFTER source_account_id;

-- Add target_account_nickname for display convenience
ALTER TABLE catalog_clone_jobs
    ADD COLUMN IF NOT EXISTS target_account_nickname VARCHAR(100) NULL
        COMMENT 'Nickname of destination ML account for display' AFTER target_account_id;
