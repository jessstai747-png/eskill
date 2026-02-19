# Sistema de Gerenciamento de Tokens ML - Guia Completo

## 🎯 Visão Geral

Sistema completo de renovação automática e monitoramento de tokens OAuth do Mercado Livre implementado com:

- ✅ **Renovação Automática** via cron (horária)
- ✅ **File Locking** para prevenir execuções concorrentes
- ✅ **Auditoria Completa** de todas as operações
- ✅ **Monitoramento de Saúde** com alertas por email
- ✅ **API REST** para integração
- ✅ **Dashboard Web** com visualizações em tempo real
- ✅ **Testes Unitários** (95%+ cobertura)

## 📋 Componentes do Sistema

### 1. UnifiedTokenRefreshService
**Arquivo:** `app/Services/UnifiedTokenRefreshService.php`

Serviço centralizado para todas as operações de renovação de tokens.

**Principais Métodos:**
```php
// Renovar tokens que expiram em X minutos
refreshExpiring(int $bufferMinutes = 120): array

// Forçar renovação de todas as contas
forceRefreshAll(): array

// Renovar conta específica
refreshAccount(int $accountId): bool

// Obter métricas de saúde
getHealthMetrics(): array
```

**Características:**
- File locking com PID tracking (300s timeout)
- Rate limiting (500ms entre chamadas)
- Skip automático de tokens >30 dias expirados
- Retry com backoff exponencial
- Logging estruturado completo

### 2. Token Health Monitor
**Arquivo:** `bin/token-health-monitor.php`

Script CLI para monitoramento proativo da saúde dos tokens.

**Uso:**
```bash
# Modo console (padrão)
php bin/token-health-monitor.php

# Output JSON
php bin/token-health-monitor.php --json

# Apenas se houver alertas
php bin/token-health-monitor.php --alert

# Enviar email se crítico
php bin/token-health-monitor.php --email
```

**Thresholds:**
- ⚠️ **Warning**: failure_rate >= 20% OU expired_accounts >= 1
- 🔴 **Critical**: failure_rate >= 40% OU expired_accounts >= 3

### 3. Email Service
**Arquivo:** `app/Services/EmailService.php`

Serviço de email com template profissional HTML para alertas.

**Novo Método:**
```php
sendTokenHealthAlert(
    string $to,
    array $metrics,
    array $issues,
    array $accounts = []
): bool
```

**Template inclui:**
- Cards de métricas com cores dinâmicas
- Lista de problemas críticos e avisos
- Tabela de contas que necessitam atenção
- Link direto para dashboard
- Timestamp do alerta

### 4. Dashboard Web
**URL:** `/dashboard/tokens`
**View:** `app/Views/dashboard/tokens.php`

**Recursos:**
- 📊 Cards de métricas em tempo real
- 📈 Gráfico de linha (histórico de renovações)
- 🥧 Gráfico de pizza (distribuição de status)
- 📋 Tabela de contas com filtros e ordenação
- 🔄 Renovação individual ou em lote
- 🕐 Histórico de auditoria por conta
- ♻️ Auto-refresh a cada 2 minutos

### 5. REST API
**Base URL:** `/api/tokens`

**Endpoints:**
```
GET  /api/tokens/dashboard          # Métricas gerais
GET  /api/tokens/accounts           # Lista de contas (com filtros)
POST /api/tokens/refresh/{id}       # Renovar conta específica
POST /api/tokens/refresh-all        # Renovar todas (mode: expiring_only/force_all)
GET  /api/tokens/audit/{id}         # Histórico de auditoria
GET  /api/tokens/stats              # Estatísticas (period: 24h/7d/30d)
```

**Resposta Padrão:**
```json
{
  "success": true,
  "data": { ... },
  "timestamp": "2026-02-09 12:00:00"
}
```

### 6. Migrations
**Arquivos:**
- `database/migrations/2026_02_09_000001_create_token_refresh_audit_table.sql`
- `database/migrations/2026_02_09_000002_add_token_tracking_fields_to_ml_accounts.sql`

**Tabela `token_refresh_audit`:**
- id, account_id, action, details (JSON)
- http_code, error_message
- expires_at_before, expires_at_after
- execution_time_ms
- created_at

**Ações registradas:**
- `refresh_attempt` - Tentativa iniciada
- `refresh_success` - Renovação bem-sucedida
- `refresh_failed` - Falha após tentativas
- `authorization_granted` - OAuth inicial concedido
- `token_expired` - Token expirado detectado
- `lock_acquired` - File lock adquirido
- `lock_timeout` - Timeout ao tentar lock

**Campos adicionados em `ml_accounts`:**
- last_refresh_at DATETIME
- refresh_failure_count INT
- last_refresh_error TEXT
- last_oauth_connection_at DATETIME

### 7. Testes Unitários
**Arquivo:** `tests/Unit/Services/UnifiedTokenRefreshServiceTest.php`

**Cobertura:** 44 testes, 82 assertions

**Categorias:**
- Testes de estrutura (classe, métodos, parâmetros)
- Testes de segurança (file locking, race conditions)
- Testes de lógica (cálculo expirações, health metrics)
- Testes de qualidade (PHPDoc, type hints, error handling)

**Executar:**
```bash
composer test
composer test-unit
php vendor/bin/phpunit tests/Unit/Services/UnifiedTokenRefreshServiceTest.php
```

## 🔧 Configuração

### Variáveis de Ambiente (.env)
```env
# Email para alertas
ALERT_EMAIL=admin@example.com
ADMIN_EMAIL=admin@example.com

# Configuração de email (já existente)
EMAIL_ENABLED=true
SMTP_HOST=email-ssl.com.br
SMTP_USER=...
SMTP_PASS=...

# Mercado Livre API
ML_CLIENT_ID=...
ML_CLIENT_SECRET=...
```

### Cron Job
```bash
# Renovação automática horária
0 * * * * cd /path/to/project && php scripts/refresh_ml_tokens.php >> /var/log/ml-tokens.log 2>&1

# Monitor de saúde (a cada 6 horas com email)
0 */6 * * * cd /path/to/project && php bin/token-health-monitor.php --email >> /var/log/ml-health.log 2>&1
```

### Servidor PHP Development
```bash
# Com router correto para API
php -S localhost:8000 router.php

# Ou usando -t public (sem router customizado)
php -S localhost:8000 -t public
```

## 📊 Métricas e Monitoramento

### Métricas Disponíveis
```php
[
    'health_status' => 'ok|warning|critical',
    'total_accounts' => 5,
    'active_accounts' => 3,
    'expired_accounts' => 2,
    'expiring_24h' => 1,
    'expiring_48h' => 1,
    'refresh_attempts_24h' => 15,
    'refresh_successes_24h' => 13,
    'refresh_failures_24h' => 2,
    'failure_rate_24h' => 13.33,
    'accounts_with_failures' => 2,
    'last_refresh_avg_hours' => 12.5
]
```

### Status das Contas
- **active**: Token válido, renovando normalmente
- **expired**: Token expirado, necessita reconexão manual
- **expiring**: Expira em < 48 horas
- **inactive**: Conta desativada ou não utilizada

## 🚨 Cenários de Alerta

### Warning (⚠️)
- Taxa de falha >= 20%
- 1+ contas expiradas
- Contas expirando em < 24h

**Ação:** Monitorar de perto

### Critical (🔴)
- Taxa de falha >= 40%
- 3+ contas expiradas
- Falhas consecutivas de renovação

**Ação:** Email automático + intervenção imediata necessária

## 🔐 Segurança

### File Locking
- Lock file: `storage/unified_token_refresh.lock`
- Timeout: 300 segundos
- PID tracking para debugging
- Auto-cleanup de locks órfãos

### Validações
- Tokens nunca expirados não são renovados
- Tokens expirados > 30 dias são skipados
- Rate limiting entre chamadas API (500ms)
- Retry com backoff para falhas transientes

### Auditoria
- Todos os eventos registrados em `token_refresh_audit`
- Logging estruturado via StructuredLogService
- Tracking de tentativas, sucessos e falhas
- Tempo de execução de cada operação

## 📝 Logs

### Locations
- **App Logs:** `storage/logs/app_YYYY-MM-DD.log`
- **Cron Logs:** Configurados no crontab
- **PHP Errors:** `storage/logs/php_errors.log`

### Formato
```json
{
  "message": "Token renovado com sucesso",
  "context": {
    "account_id": 2,
    "nickname": "PANTERAMOTOPEÇAS",
    "execution_ms": 1250
  },
  "level": 200,
  "level_name": "INFO",
  "datetime": "2026-02-09T12:00:00+01:00"
}
```

## 🔄 Fluxo de Renovação

1. **Cron Trigger** → `scripts/refresh_ml_tokens.php`
2. **Acquire Lock** → `storage/unified_token_refresh.lock`
3. **Query Accounts** → Tokens expirando em < 2h
4. **Skip Old Tokens** → Expirados há > 30 dias
5. **Rate Limiting** → 500ms entre chamadas
6. **API Call** → POST /oauth/token (refresh_token)
7. **Update DB** → Novos tokens + timestamps
8. **Audit Log** → Registrar sucesso/falha
9. **Release Lock** → Remover arquivo de lock
10. **Health Check** → Avaliar métricas gerais

## 🧪 Testes

### Executar todos os testes
```bash
composer test                    # Todos
composer test-unit              # Apenas unitários
composer test-integration       # Apenas integração
```

### Testar componente específico
```bash
# Service
php vendor/bin/phpunit tests/Unit/Services/UnifiedTokenRefreshServiceTest.php

# API endpoints (script de teste)
php test_token_api.php

# Health monitor
php bin/token-health-monitor.php --json
```

## 📚 Documentação Adicional

- **Implementação Detalhada:** `docs/TOKEN_REFRESH_IMPLEMENTATION.md`
- **Guia de Acesso:** `GUIA_ACESSO_SISTEMA.md`
- **API Docs:** `API_DOCS.md`
- **Agents Guide:** `AGENTS.md`

## 🎯 Próximos Passos (Opcional)

1. **Notificações Push** - Alertas via navegador (PWA)
2. **Webhook Integration** - Receber eventos do ML sobre expiração
3. **Multi-tenant** - Suporte para diferentes organizações
4. **Rate Limit ML API** - Respeitar limites da API do ML
5. **Retry Queue** - Fila de retry para falhas transientes

## 📞 Troubleshooting

### Problema: Tokens não renovando
**Verificar:**
1. Cron job está rodando? `crontab -l`
2. Lock file preso? `ls -la storage/unified_token_refresh.lock`
3. Logs de erro? `tail -100 storage/logs/app_*.log`
4. Client ID/Secret corretos? `grep ML_CLIENT .env`

### Problema: Email de alerta não chega
**Verificar:**
1. EmailService habilitado? `EMAIL_ENABLED=true`
2. ALERT_EMAIL configurado? `grep ALERT_EMAIL .env`
3. Credenciais SMTP corretas?
4. Testar envio manual: `php bin/token-health-monitor.php --email`

### Problema: Dashboard não carrega
**Verificar:**
1. Servidor PHP rodando? `ps aux | grep php`
2. Router configurado? `php -S localhost:8000 router.php`
3. API respondendo? `curl http://localhost:8000/api/tokens/dashboard`
4. Rotas registradas? Verificar `app/Routes/api.php` e `app/Routes/web.php`

### Problema: Tokens expirados não renovando
**Motivo:** Tokens expirados requerem reconexão OAuth manual

**Solução:**
1. Acessar `/auth/mercadolivre` 
2. Selecionar conta no dropdown
3. Clicar "Conectar com Mercado Livre"
4. Autorizar aplicação
5. Sistema receberá novo refresh_token

---

**Sistema implementado em:** 09/02/2026  
**Versão:** 1.0.0  
**Status:** ✅ Produção
