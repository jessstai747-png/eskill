# ⚡ Guia de Performance e Otimização

## Visão Geral

Este guia documenta todas as otimizações de performance implementadas no Mercado Livre Manager.

## Índice

1. [Sistema de Cache](#sistema-de-cache)
2. [Otimização de Queries SQL](#otimização-de-queries-sql)
3. [Compressão de Assets](#compressão-de-assets)
4. [API de Monitoramento](#api-de-monitoramento)
5. [Configurações PHP](#configurações-php)
6. [Checklist de Performance](#checklist-de-performance)

---

## 1. Sistema de Cache

### Drivers Suportados

O `CacheService` suporta múltiplos backends:

| Driver | Descrição | Recomendado |
|--------|-----------|-------------|
| **Redis** | Cache distribuído em memória | ✅ Produção |
| **APCu** | Cache em memória local | Servidor único |
| **File** | Cache em arquivos | Development/Fallback |

### Configuração Redis

```env
# .env
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=sua_senha_aqui
REDIS_DATABASE=0

CACHE_PREFIX=ml_manager:
CACHE_TTL=3600
CACHE_COMPRESSION=true
```

### Instalação Redis (Ubuntu/Debian)

```bash
# Instalar Redis
sudo apt update
sudo apt install redis-server

# Configurar senha
sudo nano /etc/redis/redis.conf
# Descomentar e definir: requirepass sua_senha_segura

# Reiniciar
sudo systemctl restart redis
sudo systemctl enable redis

# Instalar extensão PHP
sudo apt install php-redis
sudo systemctl restart php-fpm
```

### Uso do Cache

```php
use App\Services\CacheService;

$cache = new CacheService();

// Cache simples
$cache->set('key', $value, 3600);
$value = $cache->get('key', 'default');

// Cache-aside pattern (recomendado)
$data = $cache->remember('expensive_query', function() {
    return $this->db->query("SELECT * FROM large_table")->fetchAll();
}, 3600, ['tag:items']);

// Múltiplos valores
$cache->setMultiple(['key1' => 'val1', 'key2' => 'val2'], 3600);
$values = $cache->getMultiple(['key1', 'key2']);

// Invalidação por tag
$cache->invalidateTag('tag:items'); // Invalida todos com essa tag

// Estatísticas
$stats = $cache->getStats();
// [driver, hits, misses, writes, hit_ratio, redis: {...}]
```

### Tags de Cache

Use tags para invalidar grupos de cache relacionados:

```php
// Ao salvar um item, cachear com tags
$cache->set("item:{$itemId}", $itemData, 3600, [
    'tag:items',
    "tag:account:{$accountId}",
    "tag:category:{$categoryId}"
]);

// Ao atualizar uma conta, invalidar todo cache relacionado
$cache->invalidateTag("tag:account:{$accountId}");
```

---

## 2. Otimização de Queries SQL

### QueryOptimizerService

```php
use App\Services\QueryOptimizerService;

$optimizer = new QueryOptimizerService();

// Query com cache automático
$items = $optimizer->cachedQuery(
    "SELECT * FROM items WHERE account_id = ?",
    [$accountId],
    300,           // TTL
    ['tag:items']  // Tags
);

// Fetch único
$item = $optimizer->fetchOne(
    "SELECT * FROM items WHERE id = ?",
    [$id],
    60  // Cache TTL
);

// Valor escalar
$count = $optimizer->fetchValue(
    "SELECT COUNT(*) FROM items WHERE status = ?",
    ['active']
);

// Batch insert (500 rows por vez)
$optimizer->batchInsert('items', $rows, 500);
```

### Análise de Queries

```php
// Analisar query com EXPLAIN
$analysis = $optimizer->analyzeQuery(
    "SELECT * FROM items WHERE account_id = ? ORDER BY created_at",
    [123]
);

// Resultado:
// [
//   'explain' => [...],
//   'issues' => ['Full table scan na tabela items'],
//   'suggestions' => ['Considere adicionar índice...']
// ]

// Obter queries lentas
$slowQueries = $optimizer->getSlowQueries(24); // últimas 24h

// Sugestões de índices
$suggestions = $optimizer->suggestIndexes();
```

### Configuração de Log de Queries

```env
SLOW_QUERY_THRESHOLD=1.0    # Segundos
QUERY_LOGGING=false          # Habilitar em dev para debug
```

### Índices Recomendados

```sql
-- Items
CREATE INDEX idx_items_account_status ON items(account_id, status);
CREATE INDEX idx_items_category ON items(category_id);
CREATE INDEX idx_items_created ON items(created_at);

-- Orders
CREATE INDEX idx_orders_account_date ON orders(account_id, date_created);
CREATE INDEX idx_orders_status ON orders(status);

-- Security
CREATE INDEX idx_security_ip_created ON security_audit_log(ip_address, created_at);
```

---

## 3. Compressão de Assets

### Script de Compressão

```bash
# Comprimir CSS e JS
php scripts/compress_assets.php

# Resultado:
# ✓ Comprimido: css/app.css (45.2 KB → 32.1 KB, 29% redução)
# ✓ Comprimido: js/dashboard.js (120.5 KB → 89.3 KB, 26% redução)
```

### Gzip/Brotli no Apache

```apache
# .htaccess
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/css
    AddOutputFilterByType DEFLATE application/javascript application/json
    AddOutputFilterByType DEFLATE application/xml text/xml
</IfModule>

# Cache de assets
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
</IfModule>
```

### Nginx

```nginx
# Gzip
gzip on;
gzip_types text/plain text/css application/json application/javascript;
gzip_min_length 1000;
gzip_comp_level 6;

# Brotli (se disponível)
brotli on;
brotli_types text/plain text/css application/json application/javascript;

# Cache de assets
location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff2)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

---

## 4. API de Monitoramento

### Endpoints Disponíveis

```bash
# Dashboard completo
GET /api/performance/dashboard?hours=24

# Estatísticas de cache
GET /api/performance/cache

# Limpar cache
POST /api/performance/cache/flush
POST /api/performance/cache/flush (tag=items)

# Queries lentas
GET /api/performance/slow-queries?hours=24&threshold=1.0

# Métricas de API do ML
GET /api/performance/api-metrics?hours=24&account_id=1

# Jobs em background
GET /api/performance/jobs?status=pending

# Otimizar tabelas
POST /api/performance/optimize (tables[]=items&tables[]=orders)

# Limpar logs antigos
POST /api/performance/cleanup (days=30)

# Configurações
GET /api/performance/config
POST /api/performance/config (key=cache_enabled&value=true)
```

### Exemplo de Dashboard

```json
{
  "success": true,
  "data": {
    "cache": {
      "driver": "redis",
      "hits": 15432,
      "misses": 234,
      "writes": 1234,
      "hit_ratio": 98.5,
      "redis": {
        "used_memory": "12.5 MB",
        "connected_clients": 5
      }
    },
    "queries": {
      "total": 5432,
      "avg_ms": 12.5,
      "max_ms": 234.5,
      "slow_count": 12
    },
    "api": {
      "total_calls": 1234,
      "avg_response_ms": 150.3,
      "errors": 5
    },
    "system": {
      "php_memory": {"current": "45 MB", "peak": "120 MB"},
      "disk": {"cache_size": "25 MB", "logs_size": "150 MB"}
    }
  }
}
```

---

## 5. Configurações PHP

### php.ini Otimizado para Produção

```ini
; Memória
memory_limit = 256M

; OPcache (CRÍTICO para performance)
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0  ; Desabilitar em produção
opcache.revalidate_freq = 0
opcache.fast_shutdown = 1
opcache.enable_cli = 1
opcache.jit_buffer_size = 100M  ; PHP 8.0+
opcache.jit = 1255

; Realpath cache
realpath_cache_size = 4096k
realpath_cache_ttl = 600

; Sessions
session.save_handler = redis
session.save_path = "tcp://127.0.0.1:6379?auth=senha"

; Upload
upload_max_filesize = 50M
post_max_size = 50M

; Timeouts
max_execution_time = 30
max_input_time = 60
```

### PHP-FPM (www.conf)

```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
```

---

## 6. Checklist de Performance

### Antes do Deploy

- [ ] Redis instalado e configurado
- [ ] OPcache habilitado
- [ ] Assets comprimidos (`php scripts/compress_assets.php`)
- [ ] Gzip habilitado no servidor
- [ ] Índices de banco criados
- [ ] `QUERY_LOGGING=false` em produção

### Monitoramento Contínuo

- [ ] Verificar hit ratio do cache (> 90% ideal)
- [ ] Monitorar queries lentas diariamente
- [ ] Verificar erros de API do ML
- [ ] Limpar logs antigos semanalmente

### Métricas Alvo

| Métrica | Bom | Excelente |
|---------|-----|-----------|
| Cache hit ratio | > 80% | > 95% |
| Query média | < 50ms | < 20ms |
| API ML média | < 500ms | < 200ms |
| TTFB (Time to First Byte) | < 500ms | < 200ms |
| Memory usage | < 200MB | < 100MB |

### Ferramentas de Diagnóstico

```bash
# Status do Redis
redis-cli info stats

# Conexões MySQL
mysql -e "SHOW PROCESSLIST"

# Status OPcache
php -r "var_dump(opcache_get_status());"

# Uso de memória PHP
php -r "echo memory_get_usage(true) / 1024 / 1024 . ' MB';"
```

---

## Troubleshooting

### Cache não está funcionando

1. Verificar driver: `GET /api/performance/cache`
2. Se Redis, verificar conexão: `redis-cli ping`
3. Verificar extensão PHP: `php -m | grep redis`

### Queries lentas

1. Verificar logs: `GET /api/performance/slow-queries`
2. Analisar com EXPLAIN no MySQL Workbench
3. Adicionar índices conforme sugestões

### OPcache não acelera

1. Verificar se está habilitado: `php -i | grep opcache`
2. Verificar configurações: `php -r "print_r(opcache_get_configuration());"`
3. Reiniciar PHP-FPM após mudanças

---

*Última atualização: <?= date('Y-m-d') ?>*
