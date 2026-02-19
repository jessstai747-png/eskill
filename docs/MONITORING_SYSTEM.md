# 📊 Sistema de Monitoramento e Alertas

## 🎯 Visão Geral

Sistema completo de monitoramento para o Mercado Livre Manager, incluindo health checks, monitoramento de uptime, alertas inteligentes e métricas detalhadas.

## 📦 Componentes

### Scripts de Monitoramento

| Script | Função | Frequência Recomendada |
|--------|--------|----------------------|
| `health_check_advanced.php` | Health check completo do sistema | A cada 5-15 minutos |
| `uptime_monitor.sh` | Monitoramento de disponibilidade | A cada 2-5 minutos |
| `setup_monitoring.sh` | Configuração automática via CRON | Execução única |

### O Que é Monitorado

✅ **Banco de Dados**
- Conectividade e tempo de resposta
- Tabelas essenciais
- Tamanho do banco
- Performance de queries

✅ **Sistema Operacional**
- Espaço em disco (múltiplos pontos de montagem)
- Uso de memória RAM
- Load average (carga da CPU)
- Serviços críticos (Apache/Nginx, MySQL, PHP-FPM)

✅ **Aplicação Web**
- Endpoints HTTP principais
- Tempo de resposta
- Códigos de status HTTP
- Disponibilidade geral

✅ **Logs e Erros**
- Contagem de erros recentes (24h)
- Tamanho dos arquivos de log
- Análise de criticidade

✅ **Tokens e Autenticação**
- Tokens ML expirando
- Tokens expirados
- Contas ativas

✅ **Backups**
- Status do último backup
- Idade dos backups
- Integridade dos arquivos

## 🚀 Configuração Rápida

### Setup Automático Completo
```bash
# Configurar monitoramento com assistente interativo
./scripts/setup_monitoring.sh

# Selecionar frequência (recomendado: a cada 5 minutos)
# Configurar alertas (Email e/ou Telegram)
# Aplicar automaticamente ao CRON
```

### Configuração Manual

#### 1. Health Check Básico
```bash
# Adicionar ao crontab (a cada 5 minutos)
*/5 * * * * /usr/bin/php /home/user/scripts/health_check_advanced.php --json >> /var/log/ml_health.log 2>&1
```

#### 2. Monitoramento de Uptime
```bash
# Adicionar ao crontab (a cada 2 minutos)
*/2 * * * * /home/user/scripts/uptime_monitor.sh >> /var/log/ml_uptime.log 2>&1
```

## ⚙️ Configuração de Alertas

### Variáveis de Ambiente (.env)

```env
# Alertas por Email
EMAIL_ENABLED=true
EMAIL_FROM=noreply@seudominio.com
ALERT_EMAIL=admin@seudominio.com

# Alertas Telegram
TELEGRAM_ENABLED=true
TELEGRAM_BOT_TOKEN=123456789:ABCdefGHIjklMNOpqrsTUVwxyz
TELEGRAM_CHAT_ID=123456789

# Configurações de Monitoramento
HEALTH_CHECK_INTERVAL=300
UPTIME_CHECK_INTERVAL=120
ALERT_COOLDOWN=900
```

### Configuração Telegram

1. **Criar Bot**:
   - Converse com @BotFather no Telegram
   - Execute `/newbot` e siga as instruções
   - Copie o token fornecido

2. **Obter Chat ID**:
   ```bash
   # Envie uma mensagem para seu bot, depois execute:
   curl "https://api.telegram.org/bot<SEU_TOKEN>/getUpdates"
   ```

3. **Testar**:
   ```bash
   # Teste manual de alerta
   curl -X POST "https://api.telegram.org/bot<TOKEN>/sendMessage" \
        -d chat_id="<CHAT_ID>" \
        -d text="Teste de alerta ML Manager"
   ```

## 📊 Uso e Monitoramento

### Execução Manual

```bash
# Health check detalhado
php scripts/health_check_advanced.php --detailed

# Health check formato JSON
php scripts/health_check_advanced.php --json

# Monitoramento de uptime
./scripts/uptime_monitor.sh

# Ver status atual
tail -f /var/log/ml_health.log
tail -f /var/log/ml_uptime.log
```

### Visualização de Logs

```bash
# Logs em tempo real
tail -f /var/log/ml_health.log /var/log/ml_uptime.log

# Filtrar apenas problemas
grep -E "(ERROR|WARNING|DOWN|DEGRADED)" /var/log/ml_health.log

# Estatísticas dos últimos 7 dias
grep "$(date -d '7 days ago' '+%Y-%m-%d')" /var/log/ml_health.log | wc -l
```

### Métricas e Relatórios

```bash
# Ver métricas de uptime em JSON
tail -100 /var/log/ml_uptime_metrics.json | jq '.'

# Relatório de disponibilidade (últimas 24h)
grep "$(date '+%Y-%m-%d')" /var/log/ml_uptime.log | \
  grep -E "(UP|DOWN|DEGRADED)" | sort | uniq -c
```

## 🔔 Tipos de Alerta

### Níveis de Criticidade

| Nível | Descrição | Ação |
|-------|-----------|------|
| **OK** | Sistema funcionando normalmente | Nenhuma |
| **WARNING** | Problemas menores detectados | Log + Alerta após múltiplas ocorrências |
| **CRITICAL** | Problemas graves | Alerta imediato |
| **DOWN** | Sistema indisponível | Alerta urgente + Escalação |

### Condições de Alerta

#### Críticas (Alerta Imediato)
- Banco de dados inacessível
- Aplicação não responde (>50% dos endpoints)
- Espaço em disco >95%
- Tokens ML expirados
- Último backup >48h

#### Avisos (Alerta Acumulativo)
- Carga alta do sistema
- Espaço em disco >85%
- Erros elevados nos logs
- Tokens expirando em 3 dias
- Endpoints lentos (>5s)

## 📈 Dashboard e Relatórios

### Relatórios Automáticos

```bash
# Script de relatório diário (adicionar ao CRON)
cat << 'EOF' > scripts/daily_report.sh
#!/bin/bash
DATE=$(date '+%Y-%m-%d')
REPORT_FILE="/tmp/ml_report_$DATE.txt"

echo "RELATÓRIO DIÁRIO - $DATE" > $REPORT_FILE
echo "================================" >> $REPORT_FILE
echo "" >> $REPORT_FILE

# Uptime do dia
echo "UPTIME:" >> $REPORT_FILE
grep "$DATE" /var/log/ml_uptime.log | \
  grep -E "(UP|DOWN|DEGRADED)" | sort | uniq -c >> $REPORT_FILE

# Problemas detectados
echo -e "\nPROBLEMAS DETECTADOS:" >> $REPORT_FILE
grep "$DATE" /var/log/ml_health.log | \
  grep -E "(ERROR|WARNING)" >> $REPORT_FILE

# Métricas médias
echo -e "\nMÉTRICAS MÉDIAS:" >> $REPORT_FILE
grep "$DATE" /var/log/ml_uptime_metrics.json | \
  jq -r '.metrics | "Load: \(.load_1m) | Disk: \(.disk_usage_percent)% | Memory: \(.memory_usage_percent)%"' | \
  awk '{load+=$2; disk+=$5; mem+=$8; count++} END {print "Load Média:", load/count, "| Disk Médio:", disk/count"% | Memory Média:", mem/count"%"}' >> $REPORT_FILE

# Enviar relatório
mail -s "Relatório Diário ML Manager" admin@seudominio.com < $REPORT_FILE
EOF

chmod +x scripts/daily_report.sh

# Adicionar ao CRON (diário às 23:00)
0 23 * * * /home/user/scripts/daily_report.sh
```

## 🔧 Configuração Avançada

### Personalização de Alertas

```bash
# Criar arquivo de configuração customizada
cat << 'EOF' > config/monitoring.conf
# Limites personalizados
DISK_WARNING_THRESHOLD=85
DISK_CRITICAL_THRESHOLD=95
MEMORY_WARNING_THRESHOLD=80
MEMORY_CRITICAL_THRESHOLD=90
LOAD_WARNING_MULTIPLIER=1
LOAD_CRITICAL_MULTIPLIER=2
RESPONSE_TIME_WARNING=3000
RESPONSE_TIME_CRITICAL=5000

# Cooldown entre alertas (segundos)
ALERT_COOLDOWN=900

# Endpoints personalizados para monitorar
CUSTOM_ENDPOINTS=(
    "https://seudominio.com/api/health"
    "https://seudominio.com/dashboard"
    "https://seudominio.com/auth/status"
)
EOF
```

### Integração com Sistemas Externos

#### Webhook personalizado
```bash
# Adicionar webhook no health_check_advanced.php
private function sendWebhookAlert(array $report): void {
    $webhookUrl = getenv('WEBHOOK_URL');
    if (!$webhookUrl) return;
    
    $payload = json_encode([
        'service' => 'ML Manager',
        'status' => $report['status'],
        'level' => $report['level'],
        'timestamp' => $report['timestamp'],
        'issues' => $report['issues'],
        'warnings' => $report['warnings']
    ]);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $payload
        ]
    ]);
    
    @file_get_contents($webhookUrl, false, $context);
}
```

#### Integração com Slack
```bash
# Variáveis no .env
SLACK_ENABLED=true
SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK
```

## 🛠️ Troubleshooting

### Problemas Comuns

**1. Alertas não chegam**
```bash
# Verificar configurações
grep -E "(TELEGRAM|EMAIL)" .env

# Testar conexão Telegram
curl -X POST "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/getMe"

# Verificar logs de erro
grep "ERROR" /var/log/ml_health.log
```

**2. CRON não executa**
```bash
# Verificar se cron está rodando
sudo systemctl status cron

# Verificar configuração
crontab -l

# Verificar logs do cron
sudo tail -f /var/log/syslog | grep CRON
```

**3. Falsos positivos**
```bash
# Ajustar limites no código ou configuração
# Verificar se serviços estão realmente rodando
systemctl list-units --failed

# Testar conectividade manualmente
curl -I https://seudominio.com
```

## 📋 Checklist de Implementação

- [ ] Scripts de monitoramento executáveis
- [ ] Configuração CRON ativa
- [ ] Variáveis de alerta configuradas no .env
- [ ] Bot Telegram criado e testado
- [ ] Alertas por email funcionando
- [ ] Logs rotacionando corretamente
- [ ] Teste de todos os cenários de alerta
- [ ] Dashboard de métricas (opcional)
- [ ] Documentação da equipe atualizada
- [ ] Backup dos scripts de monitoramento

---

## 🎯 Métricas de Sucesso

- **Uptime target**: >99.5%
- **Tempo de resposta**: <2s (95% das requests)
- **Alertas falsos positivos**: <5% 
- **Tempo para detecção de problemas**: <5 minutos
- **Tempo de resolução**: <30 minutos para problemas críticos

**Status:** ✅ Sistema de monitoramento completo e pronto para produção!