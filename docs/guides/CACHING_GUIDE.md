# 💾 Sistema de Cache - Guia Completo

## Visão Geral

Sistema avançado de cache com suporte a **TTL**, **tags**, **compressão gzip** e drivers de memória/arquivo para otimização de performance.

## 🚀 Início Rápido

### Uso Básico (Helpers Globais)

```php
<?php
// Cache simples
cache('user:123', ['name' => 'John', 'email' => 'john@example.com']);

// Recuperar
$user = cache('user:123');

// Com TTL (segundos)
cache('session:abc', $data, 3600); // Expira em 1 hora

// Remember pattern (busca ou cria)
$items = cache_remember('all_items', 3600, function() {
    return Item::getAll(); // Só executa se não estiver em cache
});

// Invalidar
cache_forget('user:123');

// Limpar tudo
cache_flush();

// Tags
cache_tags(['users', 'premium'], 'user:123', $userData);
cache_forget_tag('users'); // Remove todos os caches com tag 'users'
```

## 📊 Drivers Disponíveis

### 1. File Driver (Padrão)

Armazena cache em arquivos com compressão gzip.

```php
<?php
use App\Services\AdvancedCacheService;

$cache = new AdvancedCacheService('file');
$cache->set('key', $value, 3600);
```

**Vantagens:**
- Persiste entre requisições
- Suporta grandes volumes
- Compressão automática (gzip)
- Não usa memória RAM

**Desvantagens:**
- Mais lento que memória
- I/O de disco

### 2. Memory Driver

Armazena cache em array PHP (RAM).

```php
<?php
$cache = new AdvancedCacheService('memory');
$cache->set('key', $value, 3600);
```

**Vantagens:**
- Extremamente rápido
- Zero I/O
- Ideal para testes

**Desvantagens:**
- Não persiste (limpa a cada requisição)
- Limitado pela memória disponível

## 🎯 Casos de Uso

### 1. Cache de Dados de API Externa

```php
<?php
use function App\Helpers\cache_remember;

// Cache de itens do ML por 1 hora
$items = cache_remember('ml_items_account_' . $accountId, 3600, function() use ($mlClient, $accountId) {
    return $mlClient->getItems($accountId);
});

// Com tags para invalidação seletiva
cache_tags(['ml_api', "account_$accountId"], 
    "ml_items_account_$accountId", 
    $items,
    3600
);

// Quando atualizar conta, invalida cache
cache_forget_tag("account_$accountId");
```

### 2. Cache de Queries de Banco

```php
<?php
// Cache de categorias (raramente mudam)
$categories = cache_remember('all_categories', 86400, function() use ($db) {
    $stmt = $db->query("SELECT * FROM categories ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
});

// Cache de estatísticas (atualiza a cada 15 min)
$stats = cache_remember('dashboard_stats', 900, function() use ($db) {
    return [
        'total_items' => $db->query("SELECT COUNT(*) FROM items")->fetchColumn(),
        'total_orders' => $db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
        'revenue' => $db->query("SELECT SUM(price) FROM orders")->fetchColumn()
    ];
});
```

### 3. Cache de Processamento Pesado

```php
<?php
// Análise SEO de item (processamento complexo)
$seoScore = cache_remember("seo_score_$itemId", 3600, function() use ($itemId, $seoAnalyzer) {
    return $seoAnalyzer->analyze($itemId); // Demora ~5 segundos
});

// Otimização de título (usa IA)
$optimizedTitle = cache_remember("optimized_title_$itemId", 7200, function() use ($item, $titleOptimizer) {
    return $titleOptimizer->optimize($item['title']);
});
```

### 4. Rate Limiting

```php
<?php
// Limitar requests por IP
$key = "rate_limit_ip_{$_SERVER['REMOTE_ADDR']}";
$attempts = (int) cache($key, 0);

if ($attempts >= 100) {
    http_response_code(429);
    die('Too Many Requests');
}

cache($key, $attempts + 1, 60); // Expira em 1 min
```

### 5. Session Alternative (leve)

```php
<?php
// Armazenar dados temporários de usuário
$sessionKey = "user_session_" . session_id();

cache($sessionKey, [
    'user_id' => 123,
    'preferences' => ['theme' => 'dark'],
    'last_activity' => time()
], 1800); // 30 minutos

// Recuperar
$session = cache($sessionKey);
```

## 🏷️ Sistema de Tags

Tags permitem invalidar múltiplos caches de uma vez.

### Uso Básico

```php
<?php
use function App\Helpers\cache_tags;

// Adicionar cache com tags
cache_tags(['users', 'premium'], 'user:123:profile', $userData);
cache_tags(['users', 'premium'], 'user:123:settings', $settings);
cache_tags(['users', 'free'], 'user:456:profile', $userData2);

// Invalidar TODOS os caches com tag 'premium'
cache_forget_tag('premium');
// Remove: user:123:profile, user:123:settings

// 'users' tag ainda existe
$profile = cache('user:456:profile'); // ✅ Ainda em cache
```

### Cenários Práticos

#### Invalidação por Entidade

```php
<?php
// Ao criar item
cache_tags(['items', "account_$accountId"], "item_$itemId", $item);

// Ao atualizar conta, invalida TODOS os itens dela
cache_forget_tag("account_$accountId");
```

#### Invalidação por Tipo

```php
<?php
// Cache de diferentes recursos
cache_tags(['api', 'ml'], 'ml_categories', $categories);
cache_tags(['api', 'ml'], 'ml_shipping', $shipping);
cache_tags(['api', 'correios'], 'cep_data', $cepData);

// Limpar TODOS os caches de API ML
cache_forget_tag('ml');

// Limpar TODAS as APIs
cache_forget_tag('api');
```

#### Invalidação por Usuário

```php
<?php
$userId = $_SESSION['user_id'];

cache_tags(['user', "user_$userId"], "cart_$userId", $cart);
cache_tags(['user', "user_$userId"], "wishlist_$userId", $wishlist);

// Ao fazer logout, limpa todos os dados do usuário
cache_forget_tag("user_$userId");
```

## ⚙️ API Completa

### Métodos Principais

#### set()
```php
<?php
$cache->set(string $key, mixed $value, ?int $ttl = null): bool
```

Armazena valor no cache.

```php
<?php
$cache->set('user:123', $userData);
$cache->set('session:abc', $sessionData, 3600);
```

#### get()
```php
<?php
$cache->get(string $key, mixed $default = null): mixed
```

Recupera valor do cache. Retorna `$default` se não existir.

```php
<?php
$user = $cache->get('user:123');
$user = $cache->get('user:999', ['name' => 'Guest']); // Com default
```

#### has()
```php
<?php
$cache->has(string $key): bool
```

Verifica se chave existe e não expirou.

```php
<?php
if ($cache->has('user:123')) {
    $user = $cache->get('user:123');
}
```

#### delete()
```php
<?php
$cache->delete(string $key): bool
```

Remove item do cache.

```php
<?php
$cache->delete('user:123');
```

#### clear()
```php
<?php
$cache->clear(): bool
```

Remove TUDO do cache.

```php
<?php
$cache->clear(); // ⚠️ Use com cuidado!
```

#### remember()
```php
<?php
$cache->remember(string $key, int $ttl, callable $callback, array $tags = []): mixed
```

Busca no cache ou executa callback e armazena resultado.

```php
<?php
$items = $cache->remember('all_items', 3600, function() {
    return Item::getAll();
});

// Com tags
$items = $cache->remember('all_items', 3600, function() {
    return Item::getAll();
}, ['items', 'database']);
```

#### invalidateTags()
```php
<?php
$cache->invalidateTags(array $tags): int
```

Remove todos os caches com as tags especificadas.

```php
<?php
$count = $cache->invalidateTags(['users', 'premium']);
echo "Removidos $count caches";
```

#### clearExpired()
```php
<?php
$cache->clearExpired(): int
```

Remove apenas caches expirados (file driver).

```php
<?php
$count = $cache->clearExpired();
echo "Removidos $count caches expirados";
```

#### getStats()
```php
<?php
$cache->getStats(): array
```

Estatísticas de uso do cache.

```php
<?php
$stats = $cache->getStats();
/*
[
    'hits' => 1523,
    'misses' => 345,
    'hit_rate' => 81.5,
    'total_items' => 156 (file driver only)
]
*/
```

## 🔧 Configuração Avançada

### Customizar Path de Armazenamento

```php
<?php
use App\Services\AdvancedCacheService;

$cache = new AdvancedCacheService('file', [
    'path' => '/var/cache/myapp/'
]);
```

### Desabilitar Compressão

```php
<?php
// Por padrão gzip está ativado
// Para desabilitar, modifique AdvancedCacheService.php:

private function saveToFile(string $key, array $data): bool
{
    $content = json_encode($data);
    // Remova esta linha: $content = gzencode($content, 9);
    return file_put_contents($filePath, $content) !== false;
}
```

### TTL Padrão Global

```php
<?php
// Defina TTL padrão no construtor
class AdvancedCacheService 
{
    private int $defaultTtl = 3600; // 1 hora
    
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        // ...
    }
}
```

### Namespace/Prefix

```php
<?php
// Adicione prefix para evitar colisões
class AdvancedCacheService 
{
    private string $prefix = 'myapp:';
    
    private function getCacheKey(string $key): string
    {
        return $this->prefix . $key;
    }
}
```

## 📈 Monitoramento

### Dashboard de Cache ⭐ NOVO!

Acesse o dashboard web completo em:

```
http://seu-dominio.com/dashboard/cache
```

#### Funcionalidades do Dashboard

1. **Estatísticas em Tempo Real**
   - Total de itens em cache
   - Cache hits (sucessos)
   - Cache misses (falhas)
   - Hit rate (taxa de acerto)
   - Tamanho total do cache

2. **Visualização de Itens**
   - Lista todos os itens em cache
   - Mostra chave, tamanho, data de modificação
   - Indica se está expirado
   - Exibe tags associadas
   - Paginação automática

3. **Busca e Filtros**
   - Buscar por nome da chave
   - Filtrar por status (todos/ativos/expirados)
   - Ordenação por data

4. **Ações em Massa**
   - Limpar cache expirado
   - Limpar todo o cache
   - Ver conteúdo de item específico
   - Remover item individual

5. **Auto-refresh**
   - Estatísticas atualizam a cada 30 segundos
   - Visualização sempre atualizada

### API REST Completa

O dashboard é alimentado por uma API REST robusta:

#### Endpoints Disponíveis

##### 1. Estatísticas
```http
GET /api/cache/statistics
```

**Resposta:**
```json
{
    "total_items": 156,
    "hits": 1523,
    "misses": 345,
    "hit_rate": 81.5,
    "total_size": "2.3 MB",
    "total_size_bytes": 2411724,
    "cache_directory": "/path/to/cache"
}
```

##### 2. Listar Itens
```http
GET /api/cache/list?page=1&per_page=50
```

**Resposta:**
```json
{
    "total": 156,
    "page": 1,
    "per_page": 50,
    "items": [
        {
            "key": "user:123:profile",
            "expires_at": 1735200000,
            "tags": ["users", "premium"],
            "size": 1024,
            "size_formatted": "1.00 KB",
            "modified": "2025-12-25 14:30:00",
            "is_expired": false
        }
    ]
}
```

##### 3. Obter Valor
```http
GET /api/cache/get?key=user:123
```

**Resposta:**
```json
{
    "key": "user:123",
    "exists": true,
    "value": {"name": "John", "email": "john@example.com"}
}
```

##### 4. Remover Item
```http
POST /api/cache/delete
Content-Type: application/x-www-form-urlencoded

key=user:123
```

**Resposta:**
```json
{
    "success": true
}
```

##### 5. Limpar Expirados
```http
POST /api/cache/clear-expired
```

**Resposta:**
```json
{
    "success": true,
    "removed": 23,
    "message": "23 cache items removed"
}
```

##### 6. Limpar Tudo
```http
POST /api/cache/clear
```

**Resposta:**
```json
{
    "success": true,
    "message": "All cache cleared"
}
```

##### 7. Invalidar Tags
```http
POST /api/cache/invalidate-tags
Content-Type: application/x-www-form-urlencoded

tags=users,premium
```

**Resposta:**
```json
{
    "success": true,
    "removed": 45,
    "tags": ["users", "premium"]
}
```

### Dashboard de Cache (Exemplo PHP)

Se você quiser criar um dashboard customizado:

```php
<?php
// app/Views/dashboard/cache.php
$cache = new AdvancedCacheService('file');
$stats = $cache->getStats();
?>

<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5>Total de Itens</h5>
                <h2><?= $stats['total_items'] ?></h2>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5>Cache Hits</h5>
                <h2><?= number_format($stats['hits']) ?></h2>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5>Cache Misses</h5>
                <h2><?= number_format($stats['misses']) ?></h2>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5>Hit Rate</h5>
                <h2><?= number_format($stats['hit_rate'], 1) ?>%</h2>
            </div>
        </div>
    </div>
</div>

<div class="mt-3">
    <button class="btn btn-danger" onclick="clearCache()">
        🗑️ Limpar Cache
    </button>
    <button class="btn btn-warning" onclick="clearExpired()">
        ⏰ Limpar Expirados
    </button>
</div>
```

### API de Monitoramento

```php
<?php
// app/Controllers/CacheController.php
class CacheController
{
    public function stats(): void
    {
        $cache = new AdvancedCacheService('file');
        header('Content-Type: application/json');
        echo json_encode($cache->getStats());
    }
    
    public function clear(): void
    {
        $cache = new AdvancedCacheService('file');
        $cache->clear();
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }
    
    public function clearExpired(): void
    {
        $cache = new AdvancedCacheService('file');
        $removed = $cache->clearExpired();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'removed' => $removed
        ]);
    }
}
```

### Comandos CLI

```bash
# Limpar cache
php bin/cache-clear.php

# Ver estatísticas
php bin/cache-stats.php

# Limpar expirados
php bin/cache-cleanup.php
```

## 🛠️ Troubleshooting

### Cache não persiste

**File driver:**
```bash
# Verificar permissões
ls -la storage/cache/
chmod -R 775 storage/cache/
chown -R www-data:www-data storage/cache/
```

**Memory driver:**
```php
<?php
// Memory driver NÃO persiste entre requisições
// Use file driver para persistência
$cache = new AdvancedCacheService('file');
```

### Performance ruim

1. **Muitos arquivos pequenos:**
   ```bash
   # Ver quantidade
   find storage/cache/ -type f | wc -l
   
   # Se > 10.000, considere Redis/Memcached
   ```

2. **TTL muito baixo:**
   ```php
   <?php
   // ❌ Ruim (cache inútil)
   cache('key', $value, 5); // Apenas 5 segundos
   
   // ✅ Bom
   cache('key', $value, 3600); // 1 hora
   ```

3. **Compressão pesada:**
   ```php
   <?php
   // Para dados já comprimidos (imagens, vídeos)
   // desabilite gzip (veja "Desabilitar Compressão")
   ```

### Espaço em disco

```bash
# Ver tamanho do cache
du -sh storage/cache/

# Limpar tudo
rm -rf storage/cache/*

# OU via código
cache_flush();
```

### Debugging

```php
<?php
// Verificar se cache está funcionando
cache('test', 'value');
var_dump(cache('test')); // Deve retornar 'value'

// Ver conteúdo de arquivo de cache
$file = 'storage/cache/xx/xxxxx.cache';
$content = file_get_contents($file);
$data = json_decode(gzdecode($content), true);
print_r($data);
```

## 🎓 Boas Práticas

### 1. Keys Descritivas

```php
<?php
// ❌ Ruim
cache('u123', $user);
cache('data', $data);

// ✅ Bom
cache('user:123:profile', $user);
cache('ml_items_account_456', $items);
cache("seo_score_item_$itemId", $score);
```

### 2. TTL Apropriado

```php
<?php
// Dados que mudam frequentemente (segundos/minutos)
cache('cart', $cart, 300);           // 5 min
cache('rate_limit', $count, 60);     // 1 min

// Dados estáveis (horas)
cache('user_profile', $user, 3600);  // 1 hora
cache('ml_categories', $cats, 7200); // 2 horas

// Dados raramente mudam (dias)
cache('all_categories', $cats, 86400);   // 1 dia
cache('config_global', $config, 604800); // 1 semana
```

### 3. Remember Pattern

```php
<?php
// ❌ Verboso
if (!cache('items')) {
    $items = Item::getAll();
    cache('items', $items, 3600);
}
$items = cache('items');

// ✅ Conciso
$items = cache_remember('items', 3600, fn() => Item::getAll());
```

### 4. Invalidação Inteligente

```php
<?php
// Ao atualizar item
public function updateItem(int $itemId, array $data): void
{
    $this->db->update('items', $itemId, $data);
    
    // Invalida caches relacionados
    cache_forget("item_$itemId");
    cache_forget("item_${itemId}_seo");
    cache_forget_tag("account_{$data['account_id']}");
}
```

### 5. Cache em Camadas

```php
<?php
// 1. Busca em cache L1 (memory - muito rápido)
$cache = new AdvancedCacheService('memory');
if ($cache->has('hot_data')) {
    return $cache->get('hot_data');
}

// 2. Busca em cache L2 (file - rápido)
$fileCache = new AdvancedCacheService('file');
if ($fileCache->has('hot_data')) {
    $data = $fileCache->get('hot_data');
    $cache->set('hot_data', $data, 60); // Promove para L1
    return $data;
}

// 3. Busca no banco (lento)
$data = $db->query(...)->fetch();
$fileCache->set('hot_data', $data, 3600); // L2
$cache->set('hot_data', $data, 60);       // L1
return $data;
```

## 📊 Comparação com Alternativas

| Feature | AdvancedCacheService | Redis | Memcached | APCu |
|---------|---------------------|-------|-----------|------|
| Setup | ✅ Zero config | ❌ Servidor externo | ❌ Servidor externo | ✅ Extensão PHP |
| Persistência | ✅ Sim (file) | ✅ Sim | ❌ Não | ❌ Não |
| TTL | ✅ Sim | ✅ Sim | ✅ Sim | ✅ Sim |
| Tags | ✅ Sim | ✅ Sim (complexo) | ❌ Não | ❌ Não |
| Compressão | ✅ Automático (gzip) | ❌ Manual | ❌ Não | ❌ Não |
| Multi-server | ❌ Não | ✅ Sim | ✅ Sim | ❌ Não |
| Performance | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| Escalabilidade | ⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐ |

**Recomendação:**
- **Pequenos projetos**: AdvancedCacheService (file)
- **Médios projetos**: AdvancedCacheService (file) + APCu
- **Grandes projetos**: Redis
- **Alta disponibilidade**: Redis Cluster

## 📚 Recursos

- [PSR-16: Simple Cache](https://www.php-fig.org/psr/psr-16/)
- [Caching Best Practices](https://aws.amazon.com/caching/best-practices/)
- [Redis vs Memcached](https://redis.io/topics/lru-cache)

---

**Sistema:** Mercado Livre Manager  
**Versão:** 1.0.0  
**Última Atualização:** 25/12/2025
