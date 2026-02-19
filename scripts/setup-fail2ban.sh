#!/bin/bash

# Fail2Ban Setup Script
# Protects against brute force attacks

set -e

echo "🛡️  Setting up Fail2Ban"

# Install Fail2Ban
if ! command -v fail2ban-client &> /dev/null; then
    echo "Installing Fail2Ban..."
    apt-get update
    apt-get install -y fail2ban
fi

# Create custom jail configuration
cat > /etc/fail2ban/jail.local << 'EOF'
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5
destemail = admin@localhost
sendername = Fail2Ban
action = %(action_mwl)s

[sshd]
enabled = true
port = ssh
logpath = /var/log/auth.log
maxretry = 3
bantime = 7200

[nginx-http-auth]
enabled = true
port = http,https
logpath = /var/log/nginx/error.log
maxretry = 5

[nginx-noscript]
enabled = true
port = http,https
logpath = /var/log/nginx/access.log
maxretry = 6

[nginx-badbots]
enabled = true
port = http,https
logpath = /var/log/nginx/access.log
maxretry = 2

[nginx-noproxy]
enabled = true
port = http,https
logpath = /var/log/nginx/access.log
maxretry = 2

[php-url-fopen]
enabled = true
port = http,https
logpath = /var/log/nginx/access.log
maxretry = 3
EOF

# Create custom filter for PHP attacks
cat > /etc/fail2ban/filter.d/php-url-fopen.conf << 'EOF'
[Definition]
failregex = ^<HOST> -.*"(GET|POST).*(\.php|\.asp|\.exe|\.pl|\.cgi|\.scgi)
ignoreregex =
EOF

# Restart Fail2Ban
systemctl restart fail2ban
systemctl enable fail2ban

# Show status
fail2ban-client status

echo "✅ Fail2Ban configured successfully!"
echo "Banned IPs will be automatically released after bantime expires"
