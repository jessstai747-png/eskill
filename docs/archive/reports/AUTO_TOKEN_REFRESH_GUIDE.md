# 🔄 Sistema de Auto Refresh de Tokens & Sincronização de Dados

## 📋 Visão Geral

Sistema automatizado que:
1. **Renova tokens** do Mercado Livre e Mercado Pago automaticamente
2. **Sincroniza dados reais** via API (itens, pedidos, perguntas, pagamentos)
3. **Executa em background** via cron job
4. **Monitora saúde** dos tokens e notifica problemas

## 🚀 Instalação Rápida

### 1. Configurar Cron Job

```bash
# Copiar configuração exemplo
cp crontab.auto-token-refresh.example /tmp/crontab-temp

# Editar paths (se necessário)
nano /tmp/crontab-temp

# Instalar crontab
crontab /tmp/crontab-temp

# Verificar instalação
crontab -l
```

### 2. Configurar Variáveis de Ambiente (.env)

```bash
# Token refresh settings
TOKEN_REFRESH_MARGIN_MINUTES=120  # Renovar 2h antes de expirar

# Mercado Pago (opcional)
MERCADO_PAGO_ENABLED=true
```

### 3. Testar Manualmente

```bash
# Executar worker manualmente
php bin/auto-token-refresh-worker.php

# Ver logs em tempo real
tail -f storage/logs/auto-token-refresh.log
```

## 📊 O que é Sincronizado

### Mercado Livre API
- ✅ **Itens ativos** (título, preço, estoque, vendidos)
- ✅ **Pedidos** (últimos 7 dias)
- ✅ **Perguntas não respondidas**
- ✅ **Status de tokens** (renovação automática)

### Mercado Pago API (opcional)
- ✅ **Pagamentos** (últimos 7 dias)
- ✅ **Status de transações**
- ✅ **Saldo disponível**

## ⏱️ Frequência de Execução

| Tarefa | Frequência | Descrição |
|--------|-----------|-----------|
| Token Refresh | **30min** | Renova tokens que expiram em < 2h |
| Data Sync | **30min** | Sincroniza dados das APIs |
| Health Monitor | **6h** | Verifica saúde dos tokens |
| Full Sync | **Diária 3AM** | Sincronização completa (opcional) |

## 🔧 Configuração Avançada

### Ajustar Margem de Renovação

```php
// No .env
TOKEN_REFRESH_MARGIN_MINUTES=240  // 4 horas
```

### Habilitar Mercado Pago

```bash
# .env
MERCADO_PAGO_ENABLED=true
MP_ACCESS_TOKEN=seu_token_aqui
```

### Configurar Rate Limiting

```php
// No worker (personalizar se necessário)
usleep(500000);  // 0.5s entre contas
usleep(100000);  // 0.1s entre itens
```

## 📈 Monitoramento

### Verificar Status dos Tokens

```bash
# Via worker de health
php bin/token-health-monitor.php

# Via API
curl https://eskill.com.br/api/tokens/health
```

### Visualizar Logs

```bash
# Worker principal
tail -f storage/logs/auto-token-refresh.log

# Health monitor
tail -f storage/logs/token-health.log

# Erros do cron
tail -f /var/log/syslog | grep CRON
```

### Dashboard Web

Acesse: `https://eskill.com.br/dashboard/tokens`

Visualize:
- Status de cada token
- Próxima renovação
- Histórico de sincronizações
- Estatísticas de API calls

## 🔒 Segurança

### File Locking
Worker usa file locking para evitar execuções concorrentes:
```php
$lockFile = '/tmp/token-refresh.lock';
```

### Rate Limiting Automático
- 0.5s delay entre contas
- 0.1s delay entre itens
- Respeita limites da API ML/MP

### Retry com Backoff
- Máximo 3 tentativas
- Backoff exponencial
- Log de falhas

## 📊 Estrutura de Dados

### Tabelas Sincronizadas

```sql
-- Itens
items (account_id, item_id, title, price, status, ...)

-- Pedidos
orders (account_id, order_id, status, total_amount, ...)

-- Perguntas
questions (account_id, question_id, item_id, text, status, ...)

-- Pagamentos MP
mp_payments (account_id, payment_id, status, transaction_amount, ...)
```

### Campos de Auditoria

Todas as tabelas incluem:
- `synced_at` - Última sincronização
- `updated_at` - Última atualização
- `created_at` - Primeira inserção

## 🐛 Troubleshooting

### Worker não está executando

```bash
# 1. Verificar se cron está rodando
systemctl status cron

# 2. Verificar logs do cron
grep CRON /var/log/syslog | tail -20

# 3. Testar worker manualmente
php bin/auto-token-refresh-worker.php
```

### Tokens não estão sendo renovados

```bash
# 1. Verificar tokens no banco
mysql -u root -p
USE seu_banco;
SELECT user_id, token_expires_at, status FROM ml_accounts;

# 2. Verificar logs de erro
tail -50 storage/logs/auto-token-refresh.log | grep ERROR

# 3. Testar renovação manual
php bin/token-health-monitor.php --force-refresh
```

### Dados não estão sendo sincronizados

```bash
# 1. Verificar última sincronização
SELECT MAX(synced_at) FROM items;
SELECT MAX(synced_at) FROM orders;

# 2. Verificar rate limiting
# Se muitos erros 429, aumentar delays no worker

# 3. Verificar token válido
curl -H "Authorization: Bearer TOKEN" https://api.mercadolibre.com/users/me
```

## 📞 Notificações

### Email de Alerta (opcional)

Adicione ao crontab:
```bash
MAILTO=seu-email@dominio.com
```

### Webhook de Alerta (opcional)

Configure no `.env`:
```bash
ALERT_WEBHOOK_URL=https://hooks.slack.com/...
```

## 🔄 Workflow Completo

```
┌─────────────────────────────────────────────────────────────┐
│                    CRON JOB (a cada 30min)                  │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│              Auto Token Refresh Worker                       │
├─────────────────────────────────────────────────────────────┤
│  1. Buscar tokens que expiram < 2h                          │
│  2. Renovar tokens via ML API                               │
│  3. Atualizar tokens no banco                               │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│              Data Sync Process                               │
├─────────────────────────────────────────────────────────────┤
│  Para cada conta ativa:                                     │
│    1. Sincronizar itens ativos (50 primeiros)              │
│    2. Sincronizar pedidos (últimos 7 dias)                 │
│    3. Sincronizar perguntas não respondidas                 │
│    4. [Opcional] Sincronizar pagamentos MP                  │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│              Database Update                                 │
├─────────────────────────────────────────────────────────────┤
│  • INSERT ... ON DUPLICATE KEY UPDATE                       │
│  • Atualizar timestamps (synced_at)                         │
│  • Log de operações                                         │
└─────────────────────────────────────────────────────────────┘
```

## 📚 Referências

- [Mercado Livre API Docs](https://developers.mercadolivre.com.br/)
- [Mercado Pago API Docs](https://www.mercadopago.com.br/developers/)
- [UnifiedTokenRefreshService](app/Services/UnifiedTokenRefreshService.php)
- [Auto Token Refresh Worker](bin/auto-token-refresh-worker.php)

## 🎯 Checklist de Produção

- [ ] Cron job instalado e verificado
- [ ] Variáveis de ambiente configuradas
- [ ] Worker testado manualmente
- [ ] Logs sendo gerados corretamente
- [ ] Tokens sendo renovados automaticamente
- [ ] Dados sendo sincronizados
- [ ] Monitoramento configurado
- [ ] Alertas funcionando
- [ ] Backup de tokens configurado
- [ ] Rate limiting adequado

## 🆘 Suporte

Para problemas ou dúvidas:
1. Verificar logs em `storage/logs/`
2. Testar worker manualmente
3. Consultar documentação da API ML/MP
4. Abrir issue no repositório
