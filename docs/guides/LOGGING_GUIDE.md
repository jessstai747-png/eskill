# 📋 Sistema de Logs - Guia Completo

## Visão Geral

Sistema de logs estruturados baseado em **Monolog 3.0** com suporte a JSON, rotação automática, processadores avançados e dashboard web.

## 🚀 Início Rápido

### Uso Básico (Helpers Globais)

```php
<?php
// Em qualquer lugar do código

// Logs simples
log_debug('Informação de debug', ['user_id' => 123]);
log_info('Usuário logou com sucesso');
log_warning('Tentativa de acesso negada');
log_error('Falha ao processar item', ['item_id' => 456]);
log_critical('Banco de dados inacessível!');

// Log de exceções
try {
    // código perigoso
} catch (Exception $e) {
    log_exception($e, ['context' => 'ao processar item']);
}

// Log de performance
log_performance('ProcessarItens', 2.5, ['items' => 100]);

// Log de auditoria
log_audit('Usuário alterou configurações', [
    'user_id' => 789,
    'changes' => ['notification' => true]
]);

// Medição automática de tempo
measure_time(function() {
    // código a ser medido
}, 'ProcessarItens');
```

### Instância do Logger

```php
<?php

use App\Helpers\LogHelper;

// Obter instância
$logger = logger();

// Usar métodos do Monolog
$logger->debug('Mensagem debug');
$logger->info('Mensagem info');
$logger->warning('Mensagem aviso');
$logger->error('Mensagem erro');
$logger->critical('Mensagem crítica');
```

## 📊 Níveis de Log

| Nível | Quando Usar | Helper |
|-------|-------------|--------|
| **DEBUG** | Informações detalhadas para debugging | `log_debug()` |
| **INFO** | Eventos informativos importantes | `log_info()` |
| **WARNING** | Situações anormais mas recuperáveis | `log_warning()` |
| **ERROR** | Erros que requerem atenção | `log_error()` |
| **CRITICAL** | Falhas graves do sistema | `log_critical()` |

## 🎯 Casos de Uso

### 1. Rastrear Operações de Usuário

```php
<?php
// No controller
public function updateAccount(int $accountId): void 
{
    log_info('Atualizando conta ML', [
        'account_id' => $accountId,
        'user_id' => $_SESSION['user_id']
    ]);
    
    try {
        $this->accountService->update($accountId);
        log_audit('Conta atualizada com sucesso', [
            'account_id' => $accountId
        ]);
    } catch (Exception $e) {
        log_exception($e, [
            'operation' => 'update_account',
            'account_id' => $accountId
        ]);
        throw $e;
    }
}
```

### 2. Monitorar Performance

```php
<?php
// Medição manual
$start = microtime(true);
$items = $this->itemService->processAll();
$duration = microtime(true) - $start;

log_performance('ProcessAllItems', $duration, [
    'item_count' => count($items),
    'threshold' => 5.0
]);

// OU medição automática
measure_time(function() use ($itemService) {
    return $itemService->processAll();
}, 'ProcessAllItems');
```

### 3. Auditoria de Segurança

```php
<?php
// Login bem sucedido
log_audit('Login realizado', [
    'user_id' => $userId,
    'ip' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT']
]);

// Tentativa de acesso negado
log_warning('Acesso negado a recurso protegido', [
    'user_id' => $userId,
    'resource' => '/admin/config',
    'reason' => 'insufficient_permissions'
]);

// Alteração de dados sensíveis
log_audit('Configurações alteradas', [
    'user_id' => $userId,
    'old_value' => $oldConfig,
    'new_value' => $newConfig
]);
```

### 4. Debugging de APIs

```php
<?php
log_debug('Enviando request para ML API', [
    'endpoint' => '/items',
    'method' => 'POST',
    'payload' => $data
]);

$response = $client->post('/items', $data);

log_debug('Resposta recebida da ML API', [
    'status' => $response->status,
    'body' => $response->body,
    'duration_ms' => $response->duration
]);
```

### 5. Tratamento de Erros

```php
<?php
try {
    $item = $this->mlClient->getItem($itemId);
} catch (ApiException $e) {
    if ($e->getCode() === 404) {
        log_warning('Item não encontrado', [
            'item_id' => $itemId
        ]);
    } else {
        log_exception($e, [
            'operation' => 'get_item',
            'item_id' => $itemId
        ]);
    }
}
```

## 🔍 Dashboard Web

### Acesso

```
http://seu-dominio.com/dashboard/logs
```

### Funcionalidades

1. **Filtros**
   - Por nível (DEBUG, INFO, WARNING, ERROR, CRITICAL)
   - Por período (hoje, última semana, último mês)
   - Por contexto (busca em mensagem e contexto)

2. **Busca**
   - Texto completo em mensagens
   - Busca em dados de contexto JSON
   - Case-insensitive

3. **Exportação**
   - CSV para análise em Excel/Sheets
   - Inclui todos os campos e contexto

4. **Estatísticas**
   - Total por nível
   - Média de logs por dia
   - Distribuição de níveis

### Exemplos de Busca

```
# Buscar por user_id
context: user_id:123

# Buscar erros de API
level:error context:api

# Buscar performance lenta
level:warning duration

# Buscar exceções específicas
level:error exception_class:PDOException
```

## 🛠️ API REST

### Endpoints Disponíveis

#### 1. Buscar Logs
```http
GET /api/logs/search?level=error&limit=50&offset=0
```

**Parâmetros:**
- `level` (opcional): DEBUG, INFO, WARNING, ERROR, CRITICAL
- `search` (opcional): Termo de busca
- `start_date` (opcional): Data inicial (YYYY-MM-DD)
- `end_date` (opcional): Data final (YYYY-MM-DD)
- `limit` (opcional, default: 100): Resultados por página
- `offset` (opcional, default: 0): Offset para paginação

**Resposta:**
```json
{
    "total": 245,
    "limit": 50,
    "offset": 0,
    "logs": [
        {
            "datetime": "2025-12-25 10:30:45",
            "level": "ERROR",
            "message": "Falha ao processar item",
            "context": {
                "item_id": 456,
                "error": "Connection timeout"
            }
        }
    ]
}
```

#### 2. Estatísticas
```http
GET /api/logs/statistics
```

**Resposta:**
```json
{
    "total_logs": 1523,
    "by_level": {
        "DEBUG": 856,
        "INFO": 421,
        "WARNING": 189,
        "ERROR": 52,
        "CRITICAL": 5
    },
    "last_24h": 234,
    "oldest_log": "2025-01-15 08:00:00",
    "newest_log": "2025-12-25 14:30:00"
}
```

#### 3. Limpeza
```http
DELETE /api/logs/cleanup?days=30
```

**Parâmetros:**
- `days` (opcional, default: 30): Manter logs dos últimos N dias

**Resposta:**
```json
{
    "deleted": 1245,
    "kept": 278,
    "freed_space": "15.3 MB"
}
```

#### 4. Exportar
```http
GET /api/logs/export?format=csv&level=error
```

**Parâmetros:**
- `format` (opcional, default: csv): Formato de exportação
- Mesmos filtros do endpoint de busca

**Resposta:** Arquivo CSV para download

## ⚙️ Configuração Avançada

### Rotação de Logs

Por padrão, logs com mais de 30 dias são automaticamente deletados. Configure em `StructuredLogService.php`:

```php
<?php
// Definir retenção de logs
private const LOG_RETENTION_DAYS = 30; // Ajuste conforme necessário
```

### Níveis de Log por Ambiente

```php
<?php
// config/app.php
return [
    'log_level' => $_ENV['APP_ENV'] === 'production' 
        ? 'WARNING'  // Produção: WARNING e acima
        : 'DEBUG'    // Desenvolvimento: todos os níveis
];
```

### Processadores Customizados

```php
<?php
use App\Services\StructuredLogService;

$logger = new StructuredLogService();

// Adicionar processador customizado
$logger->pushProcessor(function($record) {
    $record['extra']['app_version'] = '1.0.0';
    return $record;
});
```

### Canais de Log Separados

```php
<?php
// Criar logger para um contexto específico
$logger = logger(); // canal padrão
$logger->withName('api'); // canal 'api'
$logger->info('Request received'); // vai para api.log
```

## 📁 Estrutura de Arquivos

```
storage/
└── logs/
    ├── app-2025-12-25.log          # Logs principais
    ├── app-2025-12-24.log          # Dia anterior
    ├── app-2025-12-23.log
    └── ...
```

### Formato JSON

```json
{
    "message": "Usuário logou com sucesso",
    "context": {
        "user_id": 123,
        "ip": "192.168.1.1"
    },
    "level": 200,
    "level_name": "INFO",
    "channel": "app",
    "datetime": "2025-12-25T10:30:45.123456+00:00",
    "extra": {
        "file": "/path/to/Controller.php",
        "line": 45,
        "class": "App\\Controllers\\AuthController",
        "function": "login",
        "memory_usage": "2 MB",
        "url": "/auth/login",
        "method": "POST",
        "ip": "192.168.1.1"
    }
}
```

## 🔧 Troubleshooting

### Logs não aparecem no dashboard

1. Verifique permissões do diretório:
   ```bash
   chmod -R 775 storage/logs/
   chown -R www-data:www-data storage/logs/
   ```

2. Confirme que os logs estão sendo escritos:
   ```bash
   ls -lh storage/logs/
   tail -f storage/logs/app-*.log
   ```

3. Verifique o formato JSON:
   ```bash
   cat storage/logs/app-*.log | jq .
   ```

### Performance lenta ao buscar

1. Use filtros específicos (data, nível)
2. Limite os resultados (max 100-200)
3. Considere rotação mais frequente para grandes volumes

### Espaço em disco

```bash
# Ver tamanho dos logs
du -sh storage/logs/

# Limpar logs antigos manualmente
find storage/logs/ -name "*.log" -mtime +30 -delete

# OU via API
curl -X DELETE "http://seu-dominio.com/api/logs/cleanup?days=30"
```

## 🎓 Boas Práticas

### 1. Contexto Rico

```php
<?php
// ❌ Ruim
log_error('Erro ao processar');

// ✅ Bom
log_error('Erro ao processar item ML', [
    'item_id' => $itemId,
    'account_id' => $accountId,
    'error_message' => $e->getMessage(),
    'retry_count' => 3
]);
```

### 2. Níveis Apropriados

```php
<?php
// DEBUG - Apenas em desenvolvimento
log_debug('Valor da variável: ' . $value);

// INFO - Eventos normais importantes
log_info('100 itens sincronizados com sucesso');

// WARNING - Algo inesperado mas recuperável
log_warning('Retry após timeout', ['attempt' => 2]);

// ERROR - Erro que precisa atenção
log_error('Falha ao publicar item', ['item_id' => 123]);

// CRITICAL - Sistema instável
log_critical('Banco de dados offline!');
```

### 3. Evite Dados Sensíveis

```php
<?php
// ❌ Nunca faça isso
log_info('Login', ['password' => $password]);
log_debug('Cartão', ['card_number' => $card]);

// ✅ Faça isso
log_info('Login', ['user_id' => $userId]);
log_debug('Pagamento', ['card_last4' => substr($card, -4)]);
```

### 4. Performance

```php
<?php
// Use measure_time para operações importantes
measure_time(function() {
    $this->heavyOperation();
}, 'HeavyOperation');

// Mas evite para operações triviais
// ❌ Desnecessário
measure_time(function() {
    return 1 + 1;
}, 'SimpleSum');
```

## 📚 Recursos

- [Monolog Documentation](https://github.com/Seldaek/monolog)
- [PSR-3 Logger Interface](https://www.php-fig.org/psr/psr-3/)
- [Best Practices for Logging](https://betterstack.com/community/guides/logging/php-logging-best-practices/)

---

**Sistema:** Mercado Livre Manager  
**Versão:** 1.0.0  
**Última Atualização:** 25/12/2025
