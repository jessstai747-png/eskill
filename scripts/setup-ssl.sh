#!/bin/bash

# SSL Setup Script with Let's Encrypt
# Usage: sudo ./setup-ssl.sh yourdomain.com

set -e

DOMAIN=$1
EMAIL="admin@${DOMAIN}"

if [ -z "$DOMAIN" ]; then
    echo "Usage: $0 <domain>"
    echo "Example: $0 example.com"
    exit 1
fi

echo "🔒 Setting up SSL for ${DOMAIN}"

# Install certbot if not present
if ! command -v certbot &> /dev/null; then
    echo "Installing certbot..."
    apt-get update
    apt-get install -y certbot python3-certbot-nginx
fi

# Obtain certificate
echo "Obtaining SSL certificate..."
certbot --nginx \
    -d ${DOMAIN} \
    -d www.${DOMAIN} \
    --non-interactive \
    --agree-tos \
    --email ${EMAIL} \
    --redirect

# Setup auto-renewal
echo "Setting up auto-renewal..."
(crontab -l 2>/dev/null; echo "0 3 * * * certbot renew --quiet --post-hook 'systemctl reload nginx'") | crontab -

# Test renewal
certbot renew --dry-run

echo "✅ SSL configured successfully!"
echo "Certificate will auto-renew every 90 days"
