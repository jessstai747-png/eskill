# 🚀 AI Optimization System - Deployment Guide

## Quick Start (5 minutes)

### 1. Configure API Keys

```bash
# Copy environment template
cp .env.ai.example .env.ai

# Edit and add your API keys
nano .env.ai
```

Required keys:
- `OPENAI_API_KEY` - Get from https://platform.openai.com/api-keys
- OR `ANTHROPIC_API_KEY` - Get from https://console.anthropic.com/

### 2. Run Database Migrations

```bash
php scripts/migrate.php
```

Expected output:
```
✅ 020_create_ai_optimization_queue_table.sql executado com sucesso
✅ 021_create_ai_ab_tests_tables.sql executado com sucesso
✅ 022_create_ai_audit_log_table.sql executado com sucesso
✅ 023_create_ai_performance_tracking_table.sql executado com sucesso
```

### 3. Start Background Worker (Optional)

For batch processing:

```bash
# Run in background
nohup php bin/ai-worker.php > storage/logs/ai-worker.log 2>&1 &

# Or using systemd (recommended for production)
sudo systemctl start ai-worker
```

### 4. Access Dashboard

Navigate to: `https://your-domain.com/dashboard/ai-optimization`

---

## System Requirements

- **PHP:** 8.0+
- **MySQL:** 5.7+
- **Extensions:** curl, json, mbstring
- **Memory:** 256MB minimum
- **Disk:** 100MB for logs/cache

---

## Configuration Options

### AI Providers

**OpenAI (Recommended)**
```env
OPENAI_API_KEY=sk-proj-...
AI_DEFAULT_MODEL=gpt-4o
AI_TEMPERATURE=0.7
AI_MAX_TOKENS=4000
```

**Anthropic Claude (Cost-effective)**
```env
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-3-5-haiku-20241022
```

### Budget Management

```env
AI_MONTHLY_BUDGET=500.00        # Monthly limit in USD
AI_ALERT_THRESHOLD=400.00       # Alert at 80%
AI_MAX_COST_PER_OPTIMIZATION=1.00
```

### Worker Configuration

```env
AI_WORKER_CHECK_INTERVAL=5      # Seconds between checks
AI_WORKER_MAX_JOBS_PER_RUN=10   # Items per cycle
AI_RATE_LIMIT_REQUESTS=60       # Max requests per minute
```

---

## Production Deployment

### 1. Using Systemd (Linux)

Create `/etc/systemd/system/ai-worker.service`:

```ini
[Unit]
Description=AI Optimization Worker
After=mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html/eskill.com.br
ExecStart=/usr/bin/php bin/ai-worker.php
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Enable and start:
```bash
sudo systemctl enable ai-worker
sudo systemctl start ai-worker
sudo systemctl status ai-worker
```

### 2. Using Supervisor (Alternative)

Create `/etc/supervisor/conf.d/ai-worker.conf`:

```ini
[program:ai-worker]
command=php /var/www/html/eskill.com.br/bin/ai-worker.php
directory=/var/www/html/eskill.com.br
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/html/eskill.com.br/storage/logs/ai-worker.log
```

Start:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start ai-worker
```

### 3. Using Cron (Simple)

Add to crontab:
```cron
*/5 * * * * cd /var/www/html/eskill.com.br && php bin/ai-worker.php >> storage/logs/ai-worker.log 2>&1
```

---

## Monitoring

### Check Worker Status

```bash
# View logs
tail -f storage/logs/ai-worker.log

# Check queue status
php -r "require 'vendor/autoload.php'; echo json_encode((new App\Services\AI\Core\BatchOptimizationQueue())->getQueueStats());"
```

### Monitor Costs

```bash
# Get current month costs
curl -s http://localhost/api/ai/analytics/costs?days=30 | jq
```

### Health Check

Visit: `/api/health/check`

Expected response:
```json
{
  "status": "healthy",
  "ai_providers": "ok",
  "database": "ok",
  "queue": "ok"
}
```

---

## Common Issues & Solutions

### Issue: "OpenAI API key not configured"

**Solution:** Add key to `.env.ai` and restart worker
```bash
echo "OPENAI_API_KEY=sk-proj-..." >> .env.ai
sudo systemctl restart ai-worker
```

### Issue: "Queue not processing"

**Solution:** Check worker is running
```bash
ps aux | grep ai-worker
sudo systemctl status ai-worker
```

### Issue: "Database connection failed"

**Solution:** Verify database credentials in `config/database.php`

### Issue: "Rate limit exceeded"

**Solution:** Reduce rate in settings or increase provider limits

---

## Security Best Practices

1. **Never commit API keys** - Use `.env.ai` (gitignored)
2. **Use environment variables** in production
3. **Restrict worker access** - Run as non-root user
4. **Enable HTTPS** for dashboard access
5. **Set budget limits** to prevent overspending
6. **Monitor costs daily** via dashboard

---

## Performance Optimization

### Database Indexing

Already optimized in migrations. Verify:
```sql
SHOW INDEX FROM ai_optimization_queue;
SHOW INDEX FROM ai_audit_log;
```

### Caching

Enable Redis (optional) for better performance:
```env
AI_CACHE_ENABLED=true
AI_CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### Worker Scaling

For high volume (>1000 items/hour):
- Run multiple workers
- Increase `AI_WORKER_MAX_JOBS_PER_RUN`
- Use queue priorities

---

## Cost Optimization

### Tips to Reduce Costs

1. **Use cheaper models** for simple tasks:
   - `gpt-4o-mini` instead of `gpt-4o`
   - `claude-3-5-haiku` for descriptions

2. **Enable caching**:
   ```env
   AI_CACHE_ENABLED=true
   AI_CACHE_TTL=3600
   ```

3. **Batch processing** - More efficient than individual
4. **Set daily limits** in automation rules
5. **Use title-only** optimization for quick wins

### Cost Breakdown

| Operation | GPT-4o | GPT-4o Mini | Claude Haiku |
|-----------|--------|-------------|--------------|
| Title | R$ 0.03 | R$ 0.01 | R$ 0.008 |
| Description | R$ 0.05 | R$ 0.02 | R$ 0.015 |
| Complete | R$ 0.15 | R$ 0.05 | R$ 0.04 |

---

## Backup & Recovery

### Backup Queue Data

```bash
mysqldump -u root -p eskill ai_optimization_queue > backup_queue.sql
```

### Restore from Backup

```bash
mysql -u root -p eskill < backup_queue.sql
```

### Export Audit Log

Via dashboard: History → Export → CSV

---

## Scaling for Enterprise

### Multi-Server Setup

1. **Separate worker servers** - Dedicated queue processing
2. **Load balancer** - Distribute API requests
3. **Redis cluster** - Shared caching
4. **Database replication** - Read replicas for analytics

### Estimated Capacity

| Setup | Items/Hour | Monthly Volume | Cost/Month |
|-------|-----------|----------------|------------|
| Single | ~600 | ~14,000 | R$ 700 |
| 3 Workers | ~1,800 | ~43,000 | R$ 2,100 |
| 5 Workers | ~3,000 | ~72,000 | R$ 3,600 |

---

## Support & Troubleshooting

### Logs Location

- Worker: `storage/logs/ai-worker.log`
- API: `storage/logs/api.log`
- Errors: `storage/logs/error.log`

### Debug Mode

Enable detailed logging:
```env
APP_DEBUG=true
AI_DEBUG_MODE=true
```

### Contact

For issues, check:
1. Logs first
2. Dashboard → History for errors
3. API status endpoints

---

## Next Steps

1. ✅ Configure API keys
2. ✅ Run migrations
3. ✅ Test with 1-5 items
4. ✅ Enable automation (optional)
5. ✅ Monitor costs daily
6. ✅ Scale as needed

**System is ready for production!** 🚀

---

*Last updated: December 25, 2025*
