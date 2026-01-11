# v0.9.0-backend-baseline

Release baseline capturing backend hardening and job scheduler validation.

Highlights
- JWT + refresh tokens implemented with rotation and server-side storage.
- Refresh token cleanup job (`bin/cleanup-refresh-tokens.php`) implemented and validated.
- Scheduler (`TechSheetSchedulerService`) validated via test jobs; run/dry-run behavior confirmed.
- Structured JSON logging enhanced; sensitive values are masked before writing.
- Health endpoints validated (liveness/readiness/full check).
- Security scan (Trivy) and local validations completed in test harness.

Known constraints / Notes
- OAuth (Mercado Livre) not implemented yet; planned for Phase 3.
- Database migrations were not modified in this release; ensure migrations are reviewed before production rollout.
- Tests were executed against an isolated MySQL test DB (`.env.testing`).

If you need to rollback, use tag `v0.9.0-backend-baseline` created in the repo.
