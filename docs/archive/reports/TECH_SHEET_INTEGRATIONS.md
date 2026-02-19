# Tech Sheet - Integration & Alerts System

**Status**: ✅ COMPLETO  
**Data**: 2026-01-01  
**Versão**: 4.0.0

## 🚀 Funcionalidades Implementadas

### 1. **Webhook System** 🔗

Sistema completo de webhooks para integrações em tempo real.

**Service**: `TechSheetWebhookService`

#### Tipos de Webhooks Suportados:

##### 1.1 **Slack Integration**
```json
{
  "type": "slack",
  "config": {
    "url": "https://hooks.slack.com/services/YOUR/WEBHOOK/URL",
    "channel": "#tech-sheet-alerts",
    "username": "Tech Sheet Bot",
    "events": ["suggestions.generated", "alert.critical"]
  }
}
```

**Formato de Mensagem**:
- Attachments coloridos por tipo
- Fields organizados
- Emojis para identificação visual
- Links diretos para itens

##### 1.2 **Telegram Integration**
```json
{
  "type": "telegram",
  "config": {
    "bot_token": "YOUR_BOT_TOKEN",
    "chat_id": "YOUR_CHAT_ID",
    "events": ["*"]
  }
}
```

**Formato de Mensagem**:
- HTML formatting
- Emojis por evento
- Dados estruturados

##### 1.3 **HTTP Webhook Genérico**
```json
{
  "type": "http",
  "config": {
    "url": "https://your-service.com/webhook",
    "headers": ["Authorization: Bearer TOKEN"],
    "events": ["suggestions.applied", "analysis.completed"]
  }
}
```

**Payload Enviado**:
```json
{
  "event": "suggestions.generated",
  "timestamp": "2026-01-01T10:30:00+00:00",
  "account_id": 123,
  "payload": {
    "item_id": "MLB123",
    "suggestions_count": 5,
    "completeness": 75.5
  }
}
```

#### Eventos Disponíveis:
- `suggestions.generated` - Novas sugestões criadas
- `suggestions.applied` - Sugestões aplicadas com sucesso
- `analysis.completed` - Análise de item finalizada
- `alert.critical` - Alerta crítico disparado
- `optimization.completed` - Otimização concluída
- `*` - Todos os eventos

#### Funcionalidades:
- ✅ Retry automático com exponential backoff (3 tentativas)
- ✅ Timeout configurável (10s padrão)
- ✅ Log de sucesso/falha
- ✅ Filtro de eventos por webhook
- ✅ Pause/resume de webhooks
- ✅ Teste de webhook on-demand

---

### 2. **Advanced Alert System** 🚨

Sistema de alertas personalizados com thresholds customizados.

**Service**: `TechSheetAlertService`

#### Tipos de Alertas:

##### 2.1 **Completeness Alerts**
```json
{
  "name": "Low Completeness Warning",
  "type": "completeness",
  "conditions": [
    {"field": "completeness", "operator": "<", "value": 50}
  ],
  "channels": ["email", "slack"],
  "cooldown_minutes": 60
}
```

##### 2.2 **Performance Alerts**
```json
{
  "name": "High Failure Rate",
  "type": "performance",
  "conditions": [
    {"field": "failure_rate", "operator": ">", "value": 10}
  ],
  "channels": ["telegram", "webhook"]
}
```

##### 2.3 **Volume Alerts**
```json
{
  "name": "Many Pending Suggestions",
  "type": "volume",
  "conditions": [
    {"field": "pending_count", "operator": ">", "value": 100}
  ],
  "channels": ["email"]
}
```

#### Operadores Suportados:
- `<` - Menor que
- `<=` - Menor ou igual
- `>` - Maior que
- `>=` - Maior ou igual
- `==` - Igual
- `!=` - Diferente
- `contains` - Contém string
- `not_contains` - Não contém string

#### Canais de Notificação:
- **email** - Envio via TechSheetEmailService
- **webhook** - Disparo de webhooks configurados
- **slack** - Notificação Slack direta
- **telegram** - Mensagem Telegram

#### Funcionalidades:
- ✅ Cooldown configurável por regra (evita spam)
- ✅ Múltiplos destinatários por regra
- ✅ Histórico de alertas disparados
- ✅ Condições múltiplas (AND logic)
- ✅ Ativação/desativação de regras
- ✅ Estatísticas de triggers

---

## 📡 API Endpoints (13 novos)

### Webhooks (5 endpoints)
```
GET    /api/seo/technical-sheet/webhooks?type=slack&status=active
POST   /api/seo/technical-sheet/webhooks
Body: {
  "type": "slack",
  "config": {
    "url": "https://hooks.slack.com/...",
    "events": ["suggestions.generated"]
  }
}

PUT    /api/seo/technical-sheet/webhooks/{webhookId}
DELETE /api/seo/technical-sheet/webhooks/{webhookId}
POST   /api/seo/technical-sheet/webhooks/{webhookId}/test
```

### Alert Rules (8 endpoints)
```
GET    /api/seo/technical-sheet/alerts/rules?type=completeness
POST   /api/seo/technical-sheet/alerts/rules
Body: {
  "name": "Low Completeness",
  "type": "completeness",
  "conditions": [
    {"field": "completeness", "operator": "<", "value": 50}
  ],
  "channels": ["email", "slack"],
  "cooldown_minutes": 60
}

PUT    /api/seo/technical-sheet/alerts/rules/{ruleId}
DELETE /api/seo/technical-sheet/alerts/rules/{ruleId}
POST   /api/seo/technical-sheet/alerts/rules/{ruleId}/recipients
Body: {"email": "admin@example.com"}

DELETE /api/seo/technical-sheet/alerts/rules/{ruleId}/recipients/{email}
GET    /api/seo/technical-sheet/alerts/history?days=7&limit=100
```

---

## 🗄️ Database Schema

### tech_sheet_webhooks
```sql
- id (INT)
- account_id (INT)
- type (VARCHAR) - slack, telegram, http
- url (VARCHAR 500)
- config (JSON) - bot tokens, channels, headers
- events (JSON) - array de eventos
- status (VARCHAR) - active, paused, failed
- last_triggered_at (DATETIME)
- last_error (TEXT)
- success_count (INT)
- failure_count (INT)
- created_at, updated_at
```

### tech_sheet_alert_rules
```sql
- id (INT)
- account_id (INT)
- name (VARCHAR 200)
- type (VARCHAR 50) - completeness, performance, volume
- conditions (JSON) - array de condições
- channels (JSON) - array de canais
- cooldown_minutes (INT) - padrão 60
- status (VARCHAR) - active, paused
- trigger_count (INT)
- last_triggered_at (DATETIME)
- created_at, updated_at
```

### tech_sheet_alert_recipients
```sql
- id (INT)
- rule_id (INT) FK
- email (VARCHAR 200)
- status (VARCHAR) - active, inactive
- created_at
```

### tech_sheet_alerts (histórico)
```sql
- id (BIGINT)
- account_id (INT)
- rule_id (INT) FK
- data (JSON) - dados que dispararam o alerta
- created_at
```

---

## 🧪 Testes

**Novos Arquivos**:
- `tests/Unit/Services/TechSheetWebhookServiceTest.php` (3 testes)
- `tests/Unit/Services/TechSheetAlertServiceTest.php` (4 testes)

**Resultado**: ✅ OK (7 tests, 9 assertions)

---

## 💡 Use Cases

### 1. Slack Notification Setup

```javascript
// Registrar webhook Slack
fetch('/api/seo/technical-sheet/webhooks', {
    method: 'POST',
    body: JSON.stringify({
        type: 'slack',
        config: {
            url: 'https://hooks.slack.com/services/T00/B00/XXX',
            channel: '#marketplace-alerts',
            username: 'Tech Sheet Bot',
            events: [
                'suggestions.generated',
                'alert.critical',
                'optimization.completed'
            ]
        }
    })
})
.then(r => r.json())
.then(result => {
    console.log(`Webhook criado: #${result.webhook_id}`);
    
    // Testar webhook
    return fetch(`/api/seo/technical-sheet/webhooks/${result.webhook_id}/test`, {
        method: 'POST'
    });
})
.then(r => r.json())
.then(testResult => {
    console.log('Teste:', testResult.success ? 'OK' : 'FALHOU');
});
```

### 2. Telegram Bot Setup

```javascript
// 1. Criar bot no Telegram (@BotFather)
// 2. Obter bot_token
// 3. Obter chat_id (enviar mensagem e ver updates)

fetch('/api/seo/technical-sheet/webhooks', {
    method: 'POST',
    body: JSON.stringify({
        type: 'telegram',
        config: {
            bot_token: '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            chat_id: '-1001234567890',
            events: ['*']  // Todos eventos
        }
    })
});
```

### 3. Custom Alert Rules

```javascript
// Alerta: Completude abaixo de 50%
fetch('/api/seo/technical-sheet/alerts/rules', {
    method: 'POST',
    body: JSON.stringify({
        name: 'Completude Crítica',
        type: 'completeness',
        conditions: [
            {field: 'completeness', operator: '<', value: 50},
            {field: 'missing_required', operator: '>', value: 5}
        ],
        channels: ['email', 'slack', 'telegram'],
        cooldown_minutes: 30
    })
})
.then(r => r.json())
.then(result => {
    const ruleId = result.rule_id;
    
    // Adicionar destinatários
    fetch(`/api/seo/technical-sheet/alerts/rules/${ruleId}/recipients`, {
        method: 'POST',
        body: JSON.stringify({
            email: 'admin@example.com'
        })
    });
    
    fetch(`/api/seo/technical-sheet/alerts/rules/${ruleId}/recipients`, {
        method: 'POST',
        body: JSON.stringify({
            email: 'manager@example.com'
        })
    });
});
```

### 4. Disparar Alertas Programaticamente

```php
// No código PHP, após análise
$alertService = new TechSheetAlertService($accountId);

$alertService->checkAndTriggerAlerts('completeness', [
    'item_id' => $itemId,
    'completeness' => $completeness,
    'missing_required' => $missingCount,
]);
```

### 5. Webhook para Sistema Externo

```javascript
// Registrar webhook HTTP genérico
fetch('/api/seo/technical-sheet/webhooks', {
    method: 'POST',
    body: JSON.stringify({
        type: 'http',
        config: {
            url: 'https://your-system.com/api/tech-sheet-events',
            headers: [
                'Authorization: Bearer YOUR_API_TOKEN',
                'X-Custom-Header: value'
            ],
            events: ['suggestions.applied']
        }
    })
});

// Seu sistema receberá:
// POST https://your-system.com/api/tech-sheet-events
// {
//   "event": "suggestions.applied",
//   "timestamp": "2026-01-01T10:30:00+00:00",
//   "account_id": 123,
//   "payload": {
//     "item_id": "MLB123",
//     "applied_count": 8
//   }
// }
```

---

## 🔔 Slack Message Examples

### Suggestions Generated
```
💡 Novas Sugestões Geradas

Item: MLB123456
Sugestões: 8
Completude: 75%
```

### Critical Alert
```
🚨 Alerta Crítico

Tipo: Completude Baixa
Prioridade: HIGH
Mensagem: 15 itens com menos de 40% de completude
```

### Optimization Completed
```
⚡ Otimização Concluída

Itens Processados: 50
Sucesso: 47
Falhas: 3
Duração: 45s
```

---

## 📊 Monitoring

### Verificar Status de Webhooks
```bash
GET /api/seo/technical-sheet/webhooks
```

**Response**:
```json
[
  {
    "id": 1,
    "type": "slack",
    "url": "https://hooks.slack.com/...",
    "events": ["suggestions.generated", "alert.critical"],
    "status": "active",
    "success_count": 150,
    "failure_count": 2,
    "last_triggered_at": "2026-01-01 10:25:30"
  }
]
```

### Histórico de Alertas
```bash
GET /api/seo/technical-sheet/alerts/history?days=7
```

**Response**:
```json
[
  {
    "id": 42,
    "rule_name": "Low Completeness Warning",
    "rule_type": "completeness",
    "data": {
      "item_id": "MLB123",
      "completeness": 45.5
    },
    "created_at": "2026-01-01 09:15:20"
  }
]
```

---

## 🎯 Integration Patterns

### Pattern 1: Real-time Dashboard Updates
```javascript
// Registrar webhook para seu backend
// Seu backend envia via WebSocket para frontend
// Dashboard atualiza em tempo real sem polling
```

### Pattern 2: Multi-channel Alerts
```javascript
// Alertas críticos: email + Slack + Telegram
// Alertas médios: email + Slack
// Alertas baixos: apenas log
```

### Pattern 3: Custom Workflows
```javascript
// Webhook HTTP → Zapier/Make.com → Google Sheets
// Webhook HTTP → Lambda → SNS → SMS
// Webhook HTTP → Seu CRM → Criar ticket
```

---

## ⚙️ Configuration

### Slack Webhook URL
1. Acesse https://api.slack.com/apps
2. Crie novo app ou use existente
3. Ative "Incoming Webhooks"
4. Adicione webhook ao workspace
5. Copie Webhook URL

### Telegram Bot
1. Converse com @BotFather no Telegram
2. Envie `/newbot` e siga instruções
3. Copie o `bot_token`
4. Para obter `chat_id`:
   ```bash
   # Envie mensagem para o bot
   # Depois acesse:
   curl https://api.telegram.org/bot<TOKEN>/getUpdates
   # Procure por "chat":{"id":-1234567890}
   ```

---

## 🚀 Performance

### Webhook Delivery
- Timeout: 10 segundos
- Retry: 3 tentativas
- Delay entre retries: exponential (2s, 4s, 8s)

### Alert Cooldown
- Padrão: 60 minutos
- Configurável por regra
- Previne spam de notificações

### Throughput
- Webhooks assíncronos (não bloqueantes)
- Processamento em background recomendado
- Rate limiting no destino (ex: Slack 1 req/sec)

---

## ✅ Checklist de Implementação

- [x] TechSheetWebhookService (560 linhas)
- [x] TechSheetAlertService (590 linhas)
- [x] 13 novos endpoints API
- [x] 4 tabelas de banco (webhooks, alert_rules, alert_recipients, alerts)
- [x] Migration SQL
- [x] 7 testes unitários (100% passando)
- [x] Integração Slack completa
- [x] Integração Telegram completa
- [x] Webhook HTTP genérico
- [x] Sistema de retry com backoff
- [x] Cooldown de alertas
- [x] Histórico de alertas

---

## 🎓 Exemplos Práticos

### Exemplo Completo: Sistema de Alertas Avançado

```javascript
// 1. Criar webhook Slack
const slackWebhook = await fetch('/api/seo/technical-sheet/webhooks', {
    method: 'POST',
    body: JSON.stringify({
        type: 'slack',
        config: {
            url: 'https://hooks.slack.com/services/XXX',
            events: ['alert.triggered']
        }
    })
}).then(r => r.json());

// 2. Criar regra de alerta complexa
const alertRule = await fetch('/api/seo/technical-sheet/alerts/rules', {
    method: 'POST',
    body: JSON.stringify({
        name: 'Sistema Crítico',
        type: 'performance',
        conditions: [
            {field: 'completeness', operator: '<', value: 40},
            {field: 'pending_count', operator: '>', value: 50}
        ],
        channels: ['email', 'slack'],
        cooldown_minutes: 30
    })
}).then(r => r.json());

// 3. Adicionar múltiplos destinatários
const emails = ['admin@example.com', 'manager@example.com', 'ops@example.com'];
for (const email of emails) {
    await fetch(`/api/seo/technical-sheet/alerts/rules/${alertRule.rule_id}/recipients`, {
        method: 'POST',
        body: JSON.stringify({email})
    });
}

// 4. Monitorar histórico
setInterval(async () => {
    const history = await fetch('/api/seo/technical-sheet/alerts/history?days=1')
        .then(r => r.json());
    
    console.log(`Alertas últimas 24h: ${history.length}`);
}, 60000); // A cada 1 minuto
```

---

**Documentação criada em**: 2026-01-01 22:45:00  
**Total de arquivos**: 6 novos  
**Linhas de código**: ~1,150 linhas  
**Endpoints totais**: 41 API routes para Tech Sheet  
**Integrações**: Slack, Telegram, HTTP Webhooks
