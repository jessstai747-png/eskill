# Tasks: Stabilization & Production Readiness

## Phase 1: Critical Security & Integrity (Blocker)
- [x] Audit and fix SQL Injection risks in `CompetitorMonitorController` (and other manual query locations) <!-- id: 1 -->
- [x] Refactor Runtime DDL (Schema-on-read) to explicit Migrations script (Fix `SEOKillerController::getSettings`) <!-- id: 2 -->
- [x] Secure `QuestionController` auth logic (remove/validate `X-Account-Id` backdoor) <!-- id: 3 -->
- [x] Verify strictly typed inputs in all Controllers (replace `$_GET`/`json_decode` loose typing) <!-- id: 4 -->

## Phase 2: Technical Debt & Reliability (High)
- [x] Refactor `MercadoLivreClient` to use Guzzle instead of raw cURL <!-- id: 5 -->
- [x] Fix `StructuredLogService::search` memory leak (implement pagination or file streaming) <!-- id: 6 -->
- [x] Extract AI Prompts from `AISEOOptimizerService` to dedicated Prompt classes/files <!-- id: 7 -->
- [x] Renaming Cleanup: Resolve `SEOController` vs `SeoController` conflict <!-- id: 8 -->

## Phase 3: Scalability (Medium)
- [x] Implement Redis-backed Job Queue for `BulkOptimizer` (currently likely synchronous or weak async) <!-- id: 9 -->
- [x] Add Database Indexes for `competitor_tracking` and `logs` tables <!-- id: 10 -->

## Phase 4: Verification & Standardization (Final)
- [x] Verify Code Integrity (Linting/Syntax Check) <!-- id: 11 -->
- [x] Verify `SeoController.php` removal/renaming <!-- id: 12 -->
- [x] Run System Verification Scripts (`bin/validate-system.php`) <!-- id: 13 -->
