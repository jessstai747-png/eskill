# Production Readiness Checklist — Mercado Livre Manager

Última atualização: 2026-02-14 (v1.8.3)

---

## 1. Environment

- [x] Ensure `.env` contains valid `APP_KEY` (>=32 chars) and production values.
  - `install.sh` agora valida APP_KEY >= 32 chars automaticamente.
- [x] Ensure DB credentials (`DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) are correct.
  - `config/database.php` agora lança `RuntimeException` se `DB_PASSWORD` estiver vazio ou `CHANGE_ME` em produção.
- [x] Set `APP_ENV=production` and `APP_DEBUG=false`.
  - Defaults corretos em `config/app.php`.

## 2. Database

- [x] Run migrations: `php bin/migrate.php` (aplica todas as pendentes — .sql e .php).
  - **NÃO use** `bin/apply-migrations.php` (deprecated — aplica apenas 1 migration hardcoded).
- [ ] Verify all tables have expected indexes (run `php bin/migrate.php --status`).
- [ ] Verify `refresh_tokens` table exists with indexes `idx_selector` and `idx_user`.

## 3. Jobs & Maintenance

- [x] Add cron entries for `bin/cleanup-refresh-tokens.php` — daily at 03:30.
- [x] Add cron entries for `bin/monitor-auth-failures.php` — every 15 min.
- [x] Add cron entries for `bin/pricing-worker.php` — hourly.
- [x] Add cron entries for `bin/bulk-seo-worker.php` — every 2 min + recover hourly.
- [x] Add cron entries for `bin/auto-token-refresh-worker.php` — every 30 min.
- [x] Add cron entries for clone advanced workers (ab-testing, roi-sync, seller-recommendations, automation, alert-monitor).
- [x] Add cron entries for EAN system (expire purchases, daily report).
- [ ] Install crontab: `crontab current_crontab` (or `bash update_crontab.sh`).

## 4. Logging & Secrets

- [x] Ensure `LOG_PATH` points to a secure folder outside webroot (`storage/logs/`).
- [x] Confirm `StructuredLogService` masks sensitive values (`password`, `token`, `secret`, etc.).
- [x] Do NOT commit `.env` to Git (`.gitignore` line 6).

## 5. Security Scans

- [ ] Run Trivy: `trivy fs --severity HIGH,CRITICAL .` and address findings.
- [ ] Run Codacy/Static analysis per organization policy.

## 6. Monitoring & Alerts

- [x] Configure alerts for high rates of auth failures (`bin/monitor-auth-failures.php` active).
- [x] Configure application monitoring (`scripts/monitor_system.php` hourly + `scripts/cron_health_check.php` every 10 min).
- [ ] Configure external uptime monitoring / PagerDuty-style alerts.

## 7. Deployment

- [x] Docker production stack: `docker-compose.production.yml` (app + mysql + redis + cron).
- [x] `install.sh` validates APP_KEY, DB credentials, runs migrations, creates storage dirs.
- [ ] Configure reverse proxy (nginx) with SSL termination in front of Docker.
- [ ] Rotate secrets and strong `APP_KEY` generation.
- [x] Validate HTTPS enforcement and secure cookie flags.
  - HTTPS enforcement in `public/index.php` + `SecurityMiddleware`.
  - Secure cookies in `Dockerfile` php.ini config.

## 8. Queue Configuration

- [ ] Set `QUEUE_CONNECTION=database` (or `redis`) in `.env` for async job processing.
  - Default `sync` executes jobs during HTTP request — not suitable for production.

---

## Quick Commands

```bash
# Installation
bash install.sh

# Migrations
php bin/migrate.php                # Apply all pending
php bin/migrate.php --status       # Check status
php bin/migrate.php --dry-run      # Preview changes

# Docker production
docker compose -f docker-compose.production.yml up -d
docker compose -f docker-compose.production.yml logs -f app

# Crontab
crontab current_crontab

# Tests
composer test
php vendor/bin/phpunit --testsuite Integration
```

---

## Deprecated Scripts

Scripts abaixo foram substituídos por `php bin/migrate.php`:
- ~~`bin/apply-migrations.php`~~ — aplica apenas 1 migration hardcoded
- ~~`bin/apply-health-migration.php`~~ — aplica apenas 1 migration hardcoded
- ~~`bin/apply-pricing-migration.php`~~ — aplica apenas 1 migration hardcoded

Cron redundante removido:
- ~~`scripts/renew_tokens.php`~~ — substituído por `scripts/refresh_ml_tokens.php` + `bin/auto-token-refresh-worker.php`
