# 🚀 AI Optimization System - Quick Reference

## One-Command Access

```bash
# Make executable (first time only)
chmod +x bin/ai.sh

# Show all commands
./bin/ai.sh help
```

## Common Commands

### System Status
```bash
./bin/ai.sh status          # Full system check
./bin/ai.sh queue           # Monitor queue
./bin/ai.sh costs           # Cost report (30 days)
./bin/ai.sh costs 7         # Cost report (7 days)
```

### Worker Management
```bash
./bin/ai.sh worker start    # Start background worker
./bin/ai.sh worker stop     # Stop worker
./bin/ai.sh worker logs     # View logs (50 lines)
./bin/ai.sh worker logs 100 # View logs (100 lines)
```

### Maintenance
```bash
./bin/ai.sh setup           # Run setup verification
./bin/ai.sh migrate         # Run DB migrations
./bin/ai.sh clean           # Clean failed queue items
```

## Direct Script Usage

### Cost Reporting
```bash
# Basic report
php bin/ai-cost-report.php

# Last 7 days
php bin/ai-cost-report.php 7

# Export to CSV  
php bin/ai-cost-report.php 30 --export
```

### Queue Monitoring
```bash
# One-time check
php bin/ai-queue-monitor.php

# Live monitoring (updates every 5s)
watch -n 5 php bin/ai-queue-monitor.php
```

### Setup Verification
```bash
php bin/ai-setup-check.php
```

## Web Dashboard

```
http://your-domain.com/dashboard/ai-optimization
```

**Views Available:**
- `/dashboard/ai-optimization` - Main dashboard
- `/dashboard/ai-optimization/item/{id}` - Item editor
- `/dashboard/ai-optimization/batch` - Batch processing
- `/dashboard/ai-optimization/history` - History & audit
- `/dashboard/ai-optimization/settings` - Configuration

## API Endpoints

### Quick Test
```bash
# System info
curl http://localhost/api/ai/info

# Queue stats
curl http://localhost/api/ai/queue/stats | jq

# Provider status
curl http://localhost/api/ai/providers/status | jq

# Dashboard metrics
curl http://localhost/api/ai/analytics/dashboard?days=30 | jq
```

## Logs

```bash
# Worker logs
tail -f storage/logs/ai-worker.log

# API logs
tail -f storage/logs/api.log

# Error logs
tail -f storage/logs/error.log

# All logs
tail -f storage/logs/*.log
```

## Database Queries

```bash
# Queue status
mysql -u root -p eskill -e "
SELECT status, COUNT(*) as count 
FROM ai_optimization_queue 
GROUP BY status"

# Recent optimizations
mysql -u root -p eskill -e "
SELECT item_id, action, cost, created_at 
FROM ai_audit_log 
ORDER BY created_at DESC 
LIMIT 10"

# Today's costs
mysql -u root -p eskill -e "
SELECT SUM(cost) as total 
FROM ai_audit_log 
WHERE DATE(created_at) = CURDATE()"
```

## Troubleshooting

### Worker Not Processing
```bash
# Check if running
ps aux | grep ai-worker

# Restart
./bin/ai.sh worker stop
./bin/ai.sh worker start

# Check logs
./bin/ai.sh worker logs
```

### High Costs
```bash
# View cost breakdown
./bin/ai.sh costs

# Check by provider
php bin/ai-cost-report.php 30

# Set lower budget
# Edit .env.ai: AI_MONTHLY_BUDGET=100
```

### Queue Stuck
```bash
# Check queue
./bin/ai.sh queue

# Clean failed items
./bin/ai.sh clean

# Reset processing items
mysql -u root -p eskill -e "
UPDATE ai_optimization_queue 
SET status='pending' 
WHERE status='processing' 
AND updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
```

## Production Deployment

### First Time Setup
```bash
# 1. Configure environment
cp .env.ai.example .env.ai
nano .env.ai  # Add API key

# 2. Run migrations
./bin/ai.sh migrate

# 3. Verify setup
./bin/ai.sh setup

# 4. Start worker
./bin/ai.sh worker start

# 5. Access dashboard
open http://your-domain.com/dashboard/ai-optimization
```

### Daily Operations
```bash
# Morning: Check status
./bin/ai.sh status
./bin/ai.sh costs 1  # Yesterday's costs

# As needed: Monitor queue
watch -n 10 ./bin/ai.sh queue

# Evening: Review results
./bin/ai.sh costs 7
```

### Weekly Maintenance
```bash
# Export cost report
php bin/ai-cost-report.php 7 --export

# Clean old data (optional)
mysql -u root -p eskill -e "
DELETE FROM ai_performance_tracking 
WHERE date < DATE_SUB(CURDATE(), INTERVAL 90 DAY)"

# Backup database
mysqldump -u root -p eskill > backup_$(date +%Y%m%d).sql
```

## Environment Variables

**Required:**
```env
# At least one of these
OPENAI_API_KEY=sk-proj-...
ANTHROPIC_API_KEY=sk-ant-...
```

**Recommended:**
```env
AI_MONTHLY_BUDGET=500.00
AI_ALERT_THRESHOLD=400.00
AI_WORKER_MAX_JOBS_PER_RUN=10
```

**Optional:**
```env
AI_CACHE_ENABLED=true
AI_RATE_LIMIT_REQUESTS=60
```

## Performance Tips

### Reduce Costs
- Use `gpt-4o-mini` instead of `gpt-4o`
- Enable caching: `AI_CACHE_ENABLED=true`
- Optimize only critical items first

### Speed Up Processing
- Increase worker jobs: `AI_WORKER_MAX_JOBS_PER_RUN=20`
- Run multiple workers
- Use faster model: `claude-3-5-haiku`

### Improve Quality
- Use `gpt-4o` for best results
- Lower temperature for consistency: `AI_TEMPERATURE=0.5`
- Review suggestions before auto-applying

## Support

1. **Check logs first:** `./bin/ai.sh worker logs`
2. **Run diagnostics:** `./bin/ai.sh setup`
3. **Monitor queue:** `./bin/ai.sh queue`
4. **Review costs:** `./bin/ai.sh costs`

---

**Quick Links:**
- Full README: [AI_OPTIMIZATION_README.md](AI_OPTIMIZATION_README.md)
- Deployment Guide: [docs/AI_OPTIMIZATION_DEPLOYMENT.md](docs/AI_OPTIMIZATION_DEPLOYMENT.md)
- System Status: [AI_OPTIMIZATION_STATUS.txt](AI_OPTIMIZATION_STATUS.txt)
