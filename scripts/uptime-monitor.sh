#!/bin/bash

# Uptime Monitoring Script
# Checks if application is responding

set -e

# Configuration
APP_URL="${APP_URL:-http://localhost}"
HEALTH_ENDPOINT="${APP_URL}/health"
TIMEOUT=10
ALERT_EMAIL="${ALERT_EMAIL:-admin@localhost}"
ALERT_WEBHOOK="${ALERT_WEBHOOK}"

echo "🏥 Checking application health: ${HEALTH_ENDPOINT}"

# Make HTTP request
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time ${TIMEOUT} ${HEALTH_ENDPOINT} || echo "000")

if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ Application is UP (HTTP ${HTTP_CODE})"
    exit 0
else
    echo "❌ Application is DOWN (HTTP ${HTTP_CODE})"
    
    # Send alert
    if [ -n "$ALERT_WEBHOOK" ]; then
        curl -X POST ${ALERT_WEBHOOK} \
            -H "Content-Type: application/json" \
            -d "{\"text\":\"🚨 Application DOWN! HTTP ${HTTP_CODE}\",\"url\":\"${APP_URL}\"}" \
            --max-time 5 || true
    fi
    
    # Log to file
    echo "[$(date)] Application DOWN - HTTP ${HTTP_CODE}" >> /var/log/uptime-monitor.log
    
    exit 1
fi
