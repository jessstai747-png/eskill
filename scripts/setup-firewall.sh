#!/bin/bash

# Firewall Setup Script (UFW)
# Configures firewall for production server

set -e

echo "🔥 Configuring Firewall (UFW)"

# Install UFW if not present
if ! command -v ufw &> /dev/null; then
    echo "Installing UFW..."
    apt-get update
    apt-get install -y ufw
fi

# Reset UFW to defaults
ufw --force reset

# Default policies
ufw default deny incoming
ufw default allow outgoing

# Allow SSH (IMPORTANT: Do this first!)
ufw allow 22/tcp comment 'SSH'

# Allow HTTP and HTTPS
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'

# Allow MySQL from localhost only
ufw allow from 127.0.0.1 to any port 3306 comment 'MySQL localhost'

# Allow Redis from localhost only
ufw allow from 127.0.0.1 to any port 6379 comment 'Redis localhost'

# Rate limiting for SSH (prevent brute force)
ufw limit 22/tcp

# Enable UFW
ufw --force enable

# Show status
ufw status verbose

echo "✅ Firewall configured successfully!"
