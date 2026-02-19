# 🚀 Auth Monitor - New Features Documentation

**Version**: 2.0.0  
**Release Date**: 2026-01-25  
**Status**: Production Ready

---

## 📋 Overview

Esta versão adiciona **4 novas funcionalidades principais** ao sistema de monitoramento de autenticação, tornando-o uma solução completa e profissional para segurança de aplicações.

---

## 🆕 New Features

### 1. 🌐 REST API para Consulta de Status

API completa para integração com outros sistemas e dashboards externos.

#### Endpoints Disponíveis

**Status Geral**
```http
GET /api/auth-monitor/status
```
Retorna estatísticas gerais do sistema (bloqueios, falhas, IPs únicos, etc.)

**IPs Bloqueados**
```http
GET /api/auth-monitor/blocked-ips?active_only=true&limit=100&offset=0
```
Lista IPs bloqueados com paginação

**Falhas de Autenticação**
```http
GET /api/auth-monitor/failures?ip=192.168.1.1&since=2026-01-01&limit=100
```
Lista tentativas de falhas com filtros

**Estatísticas Detalhadas**
```http
GET /api/auth-monitor/stats
```
Retorna top IPs, estatísticas diárias, por tipo de falha e por hora

**Detalhes de um IP**
```http
GET /api/auth-monitor/ip/{ip_address}
```
Informações completas sobre um IP específico

**Bloquear IP Manualmente**
```http
POST /api/auth-monitor/block-ip
Content-Type: application/json

{
    "ip_address": "192.168.1.100",
    "reason": "Suspicious activity",
    "duration": 3600,
    "is_permanent": false
}
```

**Desbloquear IP**
```http
DELETE /api/auth-monitor/unblock-ip/{ip_address}
```

#### Exemplos de Uso

**curl**
```bash
# Ver status geral
curl http://localhost/api/auth-monitor/status

# Listar IPs bloqueados ativos
curl http://localhost/api/auth-monitor/blocked-ips?active_only=true

# Bloquear IP
curl -X POST http://localhost/api/auth-monitor/block-ip \
  -H "Content-Type: application/json" \
  -d '{"ip_address":"1.2.3.4","reason":"Manual block","is_permanent":true}'

# Desbloquear IP
curl -X DELETE http://localhost/api/auth-monitor/unblock-ip/1.2.3.4
```

**JavaScript/Fetch**
```javascript
// Buscar estatísticas
const stats = await fetch('/api/auth-monitor/stats')
  .then(r => r.json());

console.log('Top atacantes:', stats.data.top_ips);
console.log('Atividade diária:', stats.data.daily_stats);
```

**Python**
```python
import requests

# Ver detalhes de um IP
response = requests.get('http://localhost/api/auth-monitor/ip/201.47.36.86')
data = response.json()

print(f"IP bloqueado: {data['data']['is_blocked']}")
print(f"Total de falhas: {data['data']['failure_count']}")
```

---

### 2. 📊 Relatórios Semanais Automáticos

Sistema de relatórios profissionais enviados por email toda segunda-feira às 9h.

#### Características

- ✅ HTML responsivo e visualmente atraente
- ✅ Estatísticas comparativas (tendências semana vs semana)
- ✅ Top 20 IPs atacantes
- ✅ Gráficos de atividade diária
- ✅ Análise de horários de pico
- ✅ Tipos de falha mais comuns
- ✅ Lista de IPs bloqueados na semana
- ✅ Cópia local salva em `storage/reports/`

#### Execução

**Manual**
```bash
php bin/weekly-report.php
```

**Automático (Cron)**
```
0 9 * * 1 cd /home/eskill/htdocs/eskill.com.br && php bin/weekly-report.php >> storage/logs/weekly_report.log 2>&1
```

#### Configuração

Edite `.env` para configurar o destinatário:
```env
ADMIN_EMAIL=seu-email@exemplo.com
SMTP_HOST=seu-smtp.com
SMTP_USERNAME=usuario@smtp.com
SMTP_PASSWORD=sua-senha
```

#### Preview

O relatório inclui:
- Header com período analisado
- Cards com métricas principais (tentativas, IPs únicos, bloqueios)
- Indicador de tendência (📈 aumento ou 📉 redução)
- Tabela com top 20 atacantes
- Gráfico de barras da atividade diária
- Análise de horários com mais ataques
- Lista completa de IPs bloqueados

---

### 3. 🛠️ Sistema de Gerenciamento de IPs (CLI)

Ferramenta de linha de comando para gerenciar whitelist e bloqueios permanentes.

#### Comandos Disponíveis

**Bloquear IP Permanentemente**
```bash
php bin/manage-ips.php block 192.168.1.100 "IP malicioso confirmado"
```

**Desbloquear IP**
```bash
php bin/manage-ips.php unblock 192.168.1.100
```

**Adicionar à Whitelist**
```bash
php bin/manage-ips.php whitelist 187.111.220.84
```

**Remover da Whitelist**
```bash
php bin/manage-ips.php remove-whitelist 187.111.220.84
```

**Listar IPs Bloqueados Permanentemente**
```bash
php bin/manage-ips.php list-blocked
```

**Listar Whitelist**
```bash
php bin/manage-ips.php list-whitelist
```

**Informações Detalhadas de um IP**
```bash
php bin/manage-ips.php info 201.47.36.86
```

#### Exemplo de Output

```
╔═══════════════════════════════════════════════════════════════╗
║         INFORMAÇÕES DO IP: 201.47.36.86                       ║
╚═══════════════════════════════════════════════════════════════╝

Status: ⏳ Bloqueado temporariamente (expira em 2026-01-25 20:59:29)
Motivo: Exceeded threshold of 10 failed authentication attempts
Falhas registradas: 21
Bloqueado em: 2026-01-25 18:59:29
Bloqueado por: monitor-auth-failures

📊 Estatísticas de Falhas:
   Total de tentativas: 21
   Primeira detecção: 2026-01-25 18:59:29
   Última detecção: 2026-01-25 18:59:29

⚠️  Últimas 5 Tentativas:
   - 2026-01-25 18:59:29 | Tipo: 403
   - 2026-01-25 18:59:29 | Tipo: 403
```

---

### 4. 🔧 Exportador de Regras para Nginx/Apache

Gera automaticamente configurações de bloqueio para servidores web.

#### Formatos Suportados

**Nginx**
```bash
php bin/export-blocked-ips.php nginx
```

Gera arquivo `storage/nginx-blocked-ips.conf` com:
- Diretivas `geo` para bloqueio de IPs
- Configurações `limit_req_zone` para rate limiting
- Instruções de uso

**Apache**
```bash
php bin/export-blocked-ips.php apache
```

Gera arquivo `storage/apache-blocked-ips.conf` com:
- Diretivas `Require not ip` (Apache 2.4+)
- Fallback para Apache 2.2
- Instruções de uso

**JSON**
```bash
php bin/export-blocked-ips.php json
```

Gera arquivo `storage/blocked-ips.json` para integração com outros sistemas.

#### Exemplo de Configuração Nginx Gerada

```nginx
# Auth Monitor - Blocked IPs Configuration
# Generated: 2026-01-25 20:15:39
# Total IPs: 3

geo $blocked_ip {
    default 0;

    201.47.36.86 1; # TEMP - Exceeded threshold
    177.112.59.162 1; # TEMP - Exceeded threshold
}

# No bloco server {}:
if ($blocked_ip) {
    return 403 "Access Denied - IP Blocked";
}

# Rate limiting:
limit_req_zone $binary_remote_addr zone=auth_limit:10m rate=5r/m;

# No location /login:
limit_req zone=auth_limit burst=3 nodelay;
```

#### Automação com Cron

Adicione ao crontab para atualizar automaticamente a cada 30 minutos:
```bash
*/30 * * * * cd /home/eskill/htdocs/eskill.com.br && php bin/export-blocked-ips.php nginx
```

---

## 📦 Arquivos Criados

### Controllers
- `app/Controllers/AuthMonitorApiController.php` - API REST (430 linhas)

### Scripts CLI
- `bin/weekly-report.php` - Gerador de relatórios semanais (400 linhas)
- `bin/manage-ips.php` - Gerenciador de IPs (450 linhas)
- `bin/export-blocked-ips.php` - Exportador de regras (220 linhas)

### Rotas
- `app/Routes/api.php` - 7 novos endpoints

### Arquivos Gerados
- `storage/reports/weekly-YYYY-MM-DD.html` - Relatórios semanais
- `storage/nginx-blocked-ips.conf` - Configuração Nginx
- `storage/apache-blocked-ips.conf` - Configuração Apache
- `storage/blocked-ips.json` - Export JSON

---

## 🎯 Use Cases

### 1. Dashboard Externo
Use a API REST para criar um dashboard personalizado:
```javascript
async function updateDashboard() {
    const status = await fetch('/api/auth-monitor/status').then(r => r.json());
    const stats = await fetch('/api/auth-monitor/stats').then(r => r.json());
    
    document.getElementById('total-blocks').textContent = status.data.active_blocks;
    document.getElementById('failures-today').textContent = status.data.failures_today;
    
    // Renderizar gráfico com stats.data.daily_stats
    renderChart(stats.data.daily_stats);
}
```

### 2. Bloqueio Preventivo
Bloquear IPs conhecidos proativamente:
```bash
# Bloquear lista de IPs de fontes conhecidas
php bin/manage-ips.php block 1.2.3.4 "Known botnet IP"
php bin/manage-ips.php block 5.6.7.8 "Tor exit node"
```

### 3. Proteção em Camadas
Combinar bloqueio da aplicação com servidor web:
```bash
# Exportar para Nginx
php bin/export-blocked-ips.php nginx

# Copiar para configuração
sudo cp storage/nginx-blocked-ips.conf /etc/nginx/conf.d/
sudo nginx -s reload
```

### 4. Auditoria e Compliance
Salvar relatórios para auditoria:
```bash
# Gerar relatório manualmente
php bin/weekly-report.php

# Relatórios ficam salvos em storage/reports/
ls -la storage/reports/
```

---

## 🔐 Segurança

- ✅ Todos os endpoints validados
- ✅ Prepared statements (SQL injection protection)
- ✅ Input sanitization
- ✅ Rate limiting recomendado
- ✅ Codacy: 0 problemas detectados

---

## 📈 Melhorias Futuras Sugeridas

1. **Dashboard Web Visual** - Interface gráfica completa
2. **Geo-IP Integration** - Identificar países de origem
3. **Alertas Telegram/Slack** - Notificações em tempo real
4. **Machine Learning** - Detecção de padrões avançados
5. **Multi-tenant Support** - Gerenciar múltiplos domínios

---

## 🚀 Quick Start

### Testar API
```bash
curl http://localhost/api/auth-monitor/status | jq
```

### Gerar Relatório
```bash
php bin/weekly-report.php
```

### Gerenciar IPs
```bash
php bin/manage-ips.php list-blocked
php bin/manage-ips.php info 201.47.36.86
```

### Exportar Regras
```bash
php bin/export-blocked-ips.php nginx
```

---

## 📞 Support

Para dúvidas ou problemas:
- Logs: `storage/logs/`
- Documentação: `AUTH_MONITOR*.md`
- Status: `php bin/auth-status.php`

---

**Versão**: 2.0.0  
**Última Atualização**: 2026-01-25  
**Status**: ✅ Production Ready - All Features Tested
