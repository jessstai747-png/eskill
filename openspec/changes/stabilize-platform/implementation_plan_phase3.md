# Implementation Plan: Stabilization Phase 3 (Scalability)

This plan addresses the Scalability phase of the stabilization effort, focusing on database performance and asynchronous processing.

## Phase 3: Scalability

### 1. Database Indexes
**Goal**: Optimize critical queries in `CompetitorMonitor` and `Log` services.

**Changes**:
- Create migration `database/migrations/performance_indexes_v2.sql`.
- Add composite index `idx_account_last_checked` to `competitor_tracking` table `(account_id, last_checked)`.
- Add composite index `idx_account_created` to `audit_logs` table `(ml_account_id, created_at)`.
- Add composite index `idx_error_monitor_account_created` to `error_monitoring` table `(account_id, created_at)`.

### 2. Redis-backed Job Queue for BulkOptimizer
**Goal**: Ensure `BulkOptimizer` uses the async Job Queue and that the queue is backed by Redis (or a robust fallback).

**Analysis**:
- `JobService` already attempts to push to `QueueService` (Redis).
- Need to verify `BulkOptimizer` uses `JobService`.
- Need to verify `QueueService` implementation exists and works.

**Changes**:
- If `BulkOptimizer` uses synchronous processing, refactor to `JobService`.
- Implement `App\Jobs\BulkOptimizerJob` logic if not present in `JobService::executeJob`.
- Ensure `QueueService` is implemented using `predis/predis` or similar.

## Verification Plan

### Automated Tests
- **Indexes**: Run migration and check `SHOW INDEX FROM table`.
- **Queue**:
    - Dispath a bulk optimization job.
    - Verify it appears in `jobs` table.
    - Verify it is processed (simulating a worker or running `php cli.php queue:work` if available).

### Manual Verification
- Access `SEOKiller` -> Bulk Optimization.
- Start a bulk job.
- Check "Jobs" dashboard or `jobs` table for status `pending` -> `processing` -> `completed`.
