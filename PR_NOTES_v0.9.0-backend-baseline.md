# PR: v0.9.0-backend-baseline

Summary
-------
This PR finalizes backend hardening and scheduler validation required before Phase 3 (OAuth) work.

Why
---
- Stabilize authentication flows and refresh token lifecycle.
- Ensure background jobs and cron scripts are safe to run in production (no .env fallback, explicit env required).
- Improve observability via structured logs and health endpoints.

What changed
------------
- Auth hardening: Tests updated to use isolated MySQL test DB; `App\Database` enforces MySQL in tests.
- Refresh token rotation: `RefreshTokenService` implements token creation, rotation, revocation and cleanup; fixed SQL placeholder bug for MySQL.
- Scheduler/cron: `bin/cleanup-refresh-tokens.php` now refuses to load `.env` in production; scheduler validated by executing test job and cleanup runs.
- Logging & Health: `StructuredLogService` writes JSON, masks sensitive fields, and `HealthController` exposes ready/check endpoints.

Validation
----------
- Full PHPUnit suite executed against MySQL test DB (green).
- Cleanup job executed in production mode against test DB; removed test tokens and proved idempotency.
- Logs were inspected; no secrets leaked; correct log levels observed.

Known constraints
-----------------
- OAuth (Mercado Livre) integration not yet implemented (Phase 3).
- DB migrations were not edited as part of this PR; please validate migration order when deploying.

Deployment notes
----------------
- Ensure cron/pm2 systemd unit provides explicit DB and secret env vars; do NOT rely on local `.env` files in production.
- Tagging: `v0.9.0-backend-baseline` created for rollback.
