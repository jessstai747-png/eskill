# Proxy Configuration Fallback Strategy

## Overview
This document outlines the fallback proxy configuration strategy for Mercado Livre API access when direct IP connectivity is unavailable.

## Architecture

### Primary Connection (Preferred)
```
Application → Direct Internet → ML API
          (72.62.14.91)
```

### Fallback Connection (Secondary)
```
Application → Proxy Server → ML API
          (External IP)
```

### Emergency Connection (Tertiary)
```
Application → Multi-region Proxy Pool → ML API
          (Rotating IPs)
```

## Configuration Options

### Option 1: HTTP Proxy
```bash
# Environment Configuration
ML_PROXY_ENABLED=true
ML_PROXY_TYPE=http
ML_PROXY_HOST=proxy.example.com
ML_PROXY_PORT=8080
ML_PROXY_USER=proxy_username
ML_PROXY_PASS=proxy_password
```

**Pros**:
- Simple configuration
- Widely supported
- Low latency

**Cons**:
- Single point of failure
- Requires authentication management

### Option 2: SOCKS5 Proxy
```bash
# Environment Configuration
ML_PROXY_ENABLED=true
ML_PROXY_TYPE=socks5
ML_PROXY_HOST=socks.example.com
ML_PROXY_PORT=1080
ML_PROXY_USER=socks_username
ML_PROXY_PASS=socks_password
```

**Pros**:
- Better security features
- Supports UDP
- Less overhead than HTTP tunneling

**Cons**:
- More complex setup
- May not be supported by all clients

### Option 3: Cloud-based Proxy Pool
```bash
# Environment Configuration
ML_PROXY_ENABLED=true
ML_PROXY_TYPE=pool
ML_PROXY_HOST=pool.example.com
ML_PROXY_PORT=8080
ML_PROXY_API_KEY=pool_api_key
```

**Pros**:
- High availability
- Automatic failover
- Load balancing
- Geographic distribution

**Cons**:
- Higher cost
- Dependency on third party
- More complex integration

## Implementation

### Proxy Service Integration
```php
// Enhanced proxy support in MercadoLivreAuthService
private function applyCurlProxyOptions(array &$curlOptions): void
{
    $enabled = filter_var($_ENV['ML_PROXY_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if (!$enabled) {
        return;
    }

    $type = strtolower($_ENV['ML_PROXY_TYPE'] ?? 'http');
    $host = $_ENV['ML_PROXY_HOST'] ?? '';
    $port = $_ENV['ML_PROXY_PORT'] ?? '';
    $user = $_ENV['ML_PROXY_USER'] ?? '';
    $pass = $_ENV['ML_PROXY_PASS'] ?? '';

    if (empty($host) || empty($port)) {
        throw new \InvalidArgumentException('Proxy configuration incomplete');
    }

    // Set proxy server
    $curlOptions[CURLOPT_PROXY] = $host . ':' . $port;

    // Configure proxy type
    switch ($type) {
        case 'socks5':
            $curlOptions[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
            break;
        case 'socks5h':
            $curlOptions[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5_HOSTNAME;
            break;
        case 'http':
        default:
            $curlOptions[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
            break;
    }

    // Set authentication if provided
    if (!empty($user)) {
        $curlOptions[CURLOPT_PROXYUSERPWD] = $user . ':' . $pass;
        $curlOptions[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC;
    }

    // Additional proxy options
    $curlOptions[CURLOPT_HTTPPROXYTUNNEL] = true; // Tunnel through proxy
    $curlOptions[CURLOPT_TIMEOUT] = 60; // Extended timeout for proxy
}
```

### Proxy Health Monitoring
```php
// ProxyHealthMonitor.php
class ProxyHealthMonitor
{
    private array $proxies;
    private StructuredLogService $logger;
    
    public function __construct()
    {
        $this->logger = new StructuredLogService();
        $this->loadProxyConfiguration();
    }
    
    public function testAllProxies(): array
    {
        $results = [];
        
        foreach ($this->proxies as $name => $config) {
            $results[$name] = $this->testProxy($name, $config);
        }
        
        return $results;
    }
    
    private function testProxy(string $name, array $config): array
    {
        $startTime = microtime(true);
        
        try {
            $ch = curl_init('https://api.mercadolibre.com/users/me');
            
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_PROXY => $config['host'] . ':' . $config['port'],
                CURLOPT_PROXYTYPE => $this->getCurlProxyType($config['type']),
            ];
            
            if (!empty($config['username'])) {
                $options[CURLOPT_PROXYUSERPWD] = $config['username'] . ':' . $config['password'];
                $options[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC;
            }
            
            curl_setopt_array($ch, $options);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            $endTime = microtime(true);
            $duration = ($endTime - $startTime) * 1000;
            
            if ($httpCode === 200 || $httpCode === 401) { // 401 is OK (just need auth)
                $status = 'healthy';
                $message = 'Proxy responding normally';
            } else {
                $status = 'unhealthy';
                $message = "HTTP {$httpCode}: {$error}";
            }
            
        } catch (\Exception $e) {
            $status = 'error';
            $message = $e->getMessage();
            $duration = 0;
        }
        
        $result = [
            'status' => $status,
            'message' => $message,
            'latency_ms' => round($duration, 2),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $this->logger->info("Proxy health check: {$name}", $result);
        
        return $result;
    }
    
    private function loadProxyConfiguration(): void
    {
        $this->proxies = [
            'primary' => [
                'host' => $_ENV['ML_PROXY_PRIMARY_HOST'] ?? '',
                'port' => $_ENV['ML_PROXY_PRIMARY_PORT'] ?? '',
                'type' => $_ENV['ML_PROXY_PRIMARY_TYPE'] ?? 'http',
                'username' => $_ENV['ML_PROXY_PRIMARY_USER'] ?? '',
                'password' => $_ENV['ML_PROXY_PRIMARY_PASS'] ?? '',
            ],
            'secondary' => [
                'host' => $_ENV['ML_PROXY_SECONDARY_HOST'] ?? '',
                'port' => $_ENV['ML_PROXY_SECONDARY_PORT'] ?? '',
                'type' => $_ENV['ML_PROXY_SECONDARY_TYPE'] ?? 'http',
                'username' => $_ENV['ML_PROXY_SECONDARY_USER'] ?? '',
                'password' => $_ENV['ML_PROXY_SECONDARY_PASS'] ?? '',
            ],
        ];
    }
}
```

## Recommended Proxy Providers

### 1. AWS Solutions
**Elastic Load Balancer + NAT Gateway**
```bash
# Configuration
ML_PROXY_HOST=nat-xxxxx.elb.amazonaws.com
ML_PROXY_PORT=8080
ML_PROXY_TYPE=http
```

**Benefits**:
- AWS infrastructure integration
- High availability
- Scalable
- Managed service

**Cost**: ~$45/month + data transfer

### 2. DigitalOcean
**Floating IP + Droplet**
```bash
# Configuration
ML_PROXY_HOST=xxx.xxx.xxx.xxx
ML_PROXY_PORT=8080
ML_PROXY_TYPE=http
```

**Benefits**:
- Static IP address
- Full control
- Simple setup
- Cost-effective

**Cost**: ~$20/month + data transfer

### 3. Cloudflare Workers
**Serverless Proxy**
```bash
# Configuration
ML_PROXY_HOST=proxy.your-domain.workers.dev
ML_PROXY_PORT=443
ML_PROXY_TYPE=https
```

**Benefits**:
- Global distribution
- No server management
- Auto-scaling
- DDoS protection

**Cost**: ~$5-50/month based on usage

### 4. Commercial Proxy Services
**Bright Data, Oxylabs, etc.**
```bash
# Configuration (example with Bright Data)
ML_PROXY_HOST=xxxxx.zproxy.lum-superproxy.io
ML_PROXY_PORT=22225
ML_PROXY_TYPE=http
```

**Benefits**:
- Professional service
- 24/7 support
- High reliability
- Advanced features

**Cost**: ~$500-2000/month

## Failover Logic

### Automatic Proxy Failover
```php
class ProxyFailoverService
{
    private array $proxyChain;
    private int $currentProxyIndex = 0;
    
    public function executeWithFailover(callable $operation)
    {
        $maxAttempts = count($this->proxyChain);
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            try {
                // Set current proxy
                $this->setCurrentProxy($this->proxyChain[$this->currentProxyIndex]);
                
                // Execute operation
                $result = $operation();
                
                // Success - reset to primary proxy
                $this->currentProxyIndex = 0;
                return $result;
                
            } catch (\Exception $e) {
                $this->logger->warning('Proxy failed, trying next', [
                    'proxy' => $this->proxyChain[$this->currentProxyIndex]['name'],
                    'error' => $e->getMessage()
                ]);
                
                // Move to next proxy
                $this->currentProxyIndex = ($this->currentProxyIndex + 1) % $maxAttempts;
                $attempt++;
            }
        }
        
        throw new \Exception('All proxies failed');
    }
    
    private function setCurrentProxy(array $proxyConfig): void
    {
        // Set environment variables for current proxy
        $_ENV['ML_PROXY_ENABLED'] = 'true';
        $_ENV['ML_PROXY_TYPE'] = $proxyConfig['type'];
        $_ENV['ML_PROXY_HOST'] = $proxyConfig['host'];
        $_ENV['ML_PROXY_PORT'] = $proxyConfig['port'];
        $_ENV['ML_PROXY_USER'] = $proxyConfig['username'];
        $_ENV['ML_PROXY_PASS'] = $proxyConfig['password'];
    }
}
```

### Configuration Priority
```php
$proxyChain = [
    [
        'name' => 'direct',
        'enabled' => true,
        'priority' => 1
    ],
    [
        'name' => 'primary_proxy',
        'enabled' => true,
        'priority' => 2
    ],
    [
        'name' => 'secondary_proxy',
        'enabled' => true,
        'priority' => 3
    ],
    [
        'name' => 'emergency_pool',
        'enabled' => false,
        'priority' => 4
    ]
];
```

## Testing and Validation

### Proxy Test Script
```bash
#!/bin/bash
# proxy_test.sh

echo "Testing Proxy Configuration..."

# Test 1: Direct connection
echo "1. Testing direct connection..."
curl -s --connect-timeout 5 https://api.mercadolibre.com/oauth/token > /dev/null
if [ $? -eq 0 ]; then
    echo "✅ Direct connection OK"
    DIRECT_OK=true
else
    echo "❌ Direct connection FAILED"
    DIRECT_OK=false
fi

# Test 2: Primary proxy
if [ ! -z "$ML_PROXY_HOST" ]; then
    echo "2. Testing primary proxy..."
    curl -s --connect-timeout 5 --proxy "$ML_PROXY_TYPE://$ML_PROXY_HOST:$ML_PROXY_PORT" \
         https://api.mercadolibre.com/oauth/token > /dev/null
    if [ $? -eq 0 ]; then
        echo "✅ Primary proxy OK"
        PROXY_OK=true
    else
        echo "❌ Primary proxy FAILED"
        PROXY_OK=false
    fi
else
    echo "2. No proxy configured"
    PROXY_OK=false
fi

# Summary
echo ""
echo "Summary:"
if [ "$DIRECT_OK" = true ]; then
    echo "✅ Direct connectivity available - no proxy needed"
elif [ "$PROXY_OK" = true ]; then
    echo "✅ Proxy connectivity available"
else
    echo "❌ No connectivity available - check configuration"
    exit 1
fi
```

## Monitoring and Alerting

### Proxy Health Dashboard
```php
// ProxyHealthDashboard.php
class ProxyHealthDashboard
{
    public function generateReport(): array
    {
        $monitor = new ProxyHealthMonitor();
        $results = $monitor->testAllProxies();
        
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'overall_status' => 'healthy',
            'proxies' => [],
            'recommendations' => []
        ];
        
        foreach ($results as $name => $result) {
            $report['proxies'][$name] = $result;
            
            if ($result['status'] !== 'healthy') {
                $report['overall_status'] = 'degraded';
                
                if ($name === 'primary') {
                    $report['recommendations'][] = "Primary proxy failed - check configuration";
                } else {
                    $report['recommendations'][] = "Backup proxy {$name} unavailable";
                }
            }
        }
        
        if ($report['overall_status'] === 'healthy') {
            $report['recommendations'][] = "All systems operational";
        }
        
        return $report;
    }
}
```

### Alert Thresholds
```php
$alertThresholds = [
    'proxy_latency_ms' => [
        'warning' => 2000,
        'critical' => 5000
    ],
    'proxy_failure_rate' => [
        'warning' => 0.1,  // 10%
        'critical' => 0.3  // 30%
    ],
    'proxy_availability' => [
        'warning' => 0.95, // 95%
        'critical' => 0.9  // 90%
    ]
];
```

## Emergency Procedures

### Immediate Response (0-5 minutes)
1. **Detect**: Health monitor alerts trigger
2. **Assess**: Check which proxy/connection is failing
3. **Switch**: Manually activate next proxy in chain
4. **Notify**: Alert team of connectivity issue

### Short-term Response (5-60 minutes)
1. **Investigate**: Check proxy service status
2. **Contact**: Notify proxy provider if service issue
3. **Monitor**: Continuous health checks
4. **Document**: Log all actions and observations

### Long-term Resolution (1-24 hours)
1. **Permanent Fix**: Resolve root cause
2. **Review**: Analyze incident and improve procedures
3. **Test**: Validate all failover mechanisms
4. **Update**: Documentation and configurations

---

**Version**: 1.0  
**Created**: 2026-02-08  
**Review Date**: 2026-03-08
