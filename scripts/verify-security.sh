#!/bin/bash

# Security Verification Script
# Checks if all security measures are in place

echo "🔒 Security Verification Checklist"
echo "=================================="
echo ""

# Check HTTPS
echo "1. HTTPS Configuration"
if [ -d "/etc/letsencrypt/live" ]; then
    echo "   ✅ SSL certificates found"
else
    echo "   ❌ No SSL certificates (run setup-ssl.sh)"
fi

# Check Firewall
echo ""
echo "2. Firewall (UFW)"
if command -v ufw &> /dev/null; then
    if ufw status | grep -q "Status: active"; then
        echo "   ✅ UFW is active"
        ufw status numbered | head -10
    else
        echo "   ❌ UFW is not active (run setup-firewall.sh)"
    fi
else
    echo "   ❌ UFW not installed"
fi

# Check Fail2Ban
echo ""
echo "3. Fail2Ban"
if command -v fail2ban-client &> /dev/null; then
    if systemctl is-active --quiet fail2ban; then
        echo "   ✅ Fail2Ban is running"
        fail2ban-client status | grep "Jail list"
    else
        echo "   ❌ Fail2Ban not running"
    fi
else
    echo "   ❌ Fail2Ban not installed (run setup-fail2ban.sh)"
fi

# Check PHP Security
echo ""
echo "4. PHP Security Settings"
php -i | grep -E "expose_php|display_errors|allow_url_fopen" | head -3

# Check File Permissions
echo ""
echo "5. File Permissions"
if [ -w "/var/www" ]; then
    echo "   ⚠️  /var/www is writable (review permissions)"
else
    echo "   ✅ /var/www has restricted permissions"
fi

# Check Security Headers
echo ""
echo "6. Security Headers (Test with curl)"
echo "   Run: curl -I https://yourdomain.com"

echo ""
echo "=================================="
echo "Security verification complete!"
