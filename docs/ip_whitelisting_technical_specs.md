# IP Whitelisting Technical Specifications

## Overview
This document provides technical specifications for IP whitelisting requirements for Mercado Livre API access.

## Current Infrastructure

### Server Information
- **Primary Server IP**: 72.62.14.91/32
- **Hostname**: eskill.com.br
- **Datacenter**: [To be confirmed]
- **ISP**: [To be confirmed]

### Network Architecture
```
Internet Gateway
    ↓
Firewall (allows 80/443)
    ↓
Load Balancer (if applicable)
    ↓
Web Server (72.62.14.91)
    ↓
Application Layer
    ↓
Database Layer
```

## IP Whitelisting Requirements

### Primary IP Range
- **IP Address**: 72.62.14.91
- **CIDR Notation**: 72.62.14.91/32
- **Purpose**: OAuth token refresh operations
- **Protocols**: 
  - HTTPS (443) - Primary API communication
  - HTTP (80) - Redirect handling

### Traffic Patterns
```
Token Refresh Traffic:
├── Frequency: Every 30 minutes
├── Volume: ~50 requests per batch
├── Duration: 2-5 seconds per request
├── Protocol: HTTPS
└── User-Agent: SEO-Optimizer/1.0

OAuth Flow Traffic:
├── Frequency: User-initiated
├── Volume: Variable (10-50 per day)
├── Duration: 1-3 seconds per request
├── Protocol: HTTPS
└── User-Agent: SEO-Optimizer/1.0
```

## Security Considerations

### Firewall Configuration
```bash
# Current firewall rules (example)
ufw allow 80/tcp
ufw allow 443/tcp
ufw deny 22/tcp  # SSH restricted to management IPs
```

### SSL/TLS Configuration
- **TLS Version**: 1.2+
- **Cipher Suites**: Modern secure suites
- **Certificate**: Valid SSL certificate installed
- **HSTS**: Enabled with proper headers

## Fallback Strategy

### Proxy Configuration
```bash
# Primary: Direct IP access
ML_PROXY_ENABLED=false

# Fallback 1: Cloud proxy service
ML_PROXY_ENABLED=true
ML_PROXY_TYPE=http
ML_PROXY_HOST=proxy1.example.com
ML_PROXY_PORT=8080

# Fallback 2: Regional proxy rotation
ML_PROXY_ENABLED=true
ML_PROXY_TYPE=socks5
ML_PROXY_HOST=proxy2.example.com
ML_PROXY_PORT=1080
```

### Proxy Providers (Options)
1. **AWS Elastic IP**: Static IP with backup zones
2. **DigitalOcean Floating IP**: Manual failover capability
3. **Cloudflare Workers**: Serverless proxy solution
4. **Dedicated Proxy Service**: Commercial proxy provider

## Implementation Timeline

### Phase 1: Preparation (Days 1-2)
- [ ] Verify current server IP
- [ ] Test network connectivity
- [ ] Document firewall rules
- [ ] Prepare proxy configuration

### Phase 2: Submission (Days 3-4)
- [ ] Prepare CSV submission file
- [ ] Submit to ML DevCenter
- [ ] Monitor approval status
- [ ] Test API access with whitelisted IP

### Phase 3: Validation (Days 5-7)
- [ ] Verify token refresh operations
- [ ] Test OAuth flow
- [ ] Validate fallback proxy
- [ ] Document results

## Monitoring and Maintenance

### IP Health Checks
```bash
# Script to verify IP connectivity
#!/bin/bash
IP="72.62.14.91"
ML_API="api.mercadolibre.com"

# Check if our IP is accessible
curl -s --connect-timeout 5 https://$ML_API/oauth/token > /dev/null
if [ $? -eq 0 ]; then
    echo "✅ IP connectivity OK"
else
    echo "❌ IP connectivity FAILED"
    # Trigger alert
fi
```

### Proxy Failover Testing
```bash
# Test proxy failover
test_proxy() {
    local proxy_host=$1
    local proxy_port=$2
    
    curl -s --connect-timeout 5 --proxy $proxy_host:$proxy_port \
         https://api.mercadolibre.com/oauth/token > /dev/null
    
    return $?
}

# Test each proxy in sequence
proxies=(
    "proxy1.example.com:8080"
    "proxy2.example.com:1080"
)

for proxy in "${proxies[@]}"; do
    if test_proxy $proxy; then
        echo "✅ Proxy $proxy OK"
    else
        echo "❌ Proxy $proxy FAILED"
    fi
done
```

## Compliance and Documentation

### ML DevCenter Requirements
1. **Application Status**: Must be "listed integrator"
2. **IP Format**: CIDR notation only
3. **Documentation**: Technical justification required
4. **Approval Process**: Manual review by ML team
5. **Propagation Delay**: Up to 24 hours

### Internal Documentation
- Network diagrams
- Firewall configuration
- Proxy authentication details
- Emergency contact procedures
- Rollback procedures

## Risk Assessment

### High Risk
- IP change without notice from hosting provider
- ML DevCenter application suspension
- Network infrastructure failure

### Medium Risk
- Proxy service downtime
- Firewall configuration errors
- SSL certificate expiration

### Low Risk
- Temporary network congestion
- API rate limiting
- Scheduled maintenance

## Emergency Procedures

### IP Change Procedure
1. **Immediate**: Activate fallback proxy
2. **Within 1 hour**: Notify ML DevCenter
3. **Within 4 hours**: Submit new IP for whitelisting
4. **Within 24 hours**: Complete IP migration

### Service Interruption
1. **Detection**: Monitoring alerts trigger
2. **Assessment**: Identify root cause
3. **Mitigation**: Activate fallback mechanisms
4. **Resolution**: Restore primary service
5. **Post-mortem**: Document and improve

---

**Version**: 1.0  
**Created**: 2026-02-08  
**Review Date**: 2026-03-08
