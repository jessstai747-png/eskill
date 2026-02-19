# 🚀 Quick Start - Auth Failure Monitor

## ⚡ Setup Rápido (5 minutos)

### 1️⃣ Adicione as variáveis ao seu .env

```bash
# Adicione ao final do seu arquivo .env
cat >> .env << 'EOF'

# Auth Failure Monitor
AUTH_BLOCK_THRESHOLD=10
AUTH_FAILURE_ALERT_THRESHOLD=50
AUTH_BLOCK_DURATION=3600
AUTH_TIME_WINDOW=3600
AUTH_IP_WHITELIST=127.0.0.1,::1

# Configuração de Email (ajuste conforme necessário)
ADMIN_EMAIL=seu-email@exemplo.com
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=seu-email@gmail.com
SMTP_PASS=sua-senha-app
SMTP_SECURE=tls
EOF
```

### 2️⃣ Teste a Ajuda

```bash
php bin/monitor-auth-failures.php --help
```

### 3️⃣ Execute um Teste Simulado

```bash
# Cria logs de teste e executa em modo dry-run
php bin/test-auth-monitor.php
```

### 4️⃣ Execute Manualmente (primeira vez)

```bash
# Modo dry-run (não bloqueia nem envia emails)
php bin/monitor-auth-failures.php --dry-run --verbose

# Modo produção
php bin/monitor-auth-failures.php --verbose
```

### 5️⃣ Automatize com Cron

```bash
# Editar crontab
crontab -e

# Adicionar esta linha (executa a cada 15 minutos)
*/15 * * * * cd /home/eskill/htdocs/eskill.com.br && php bin/monitor-auth-failures.php >> /var/log/auth-monitor.log 2>&1
```

## 📋 Verificação Rápida

### Verificar IPs Bloqueados

```sql
-- Conectar ao banco
mysql -u root -p meli

-- Ver IPs atualmente bloqueados
SELECT ip_address, failure_count, blocked_at, expires_at 
FROM auth_blocked_ips 
WHERE expires_at > NOW()
ORDER BY blocked_at DESC;

-- Ver últimas falhas de autenticação
SELECT ip_address, COUNT(*) as total, MAX(detected_at) as last_attempt
FROM auth_failure_log
WHERE detected_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY ip_address
ORDER BY total DESC
LIMIT 10;
```

### Verificar Logs do Monitor

```bash
# Ver últimas execuções
tail -f /var/log/auth-monitor.log

# Contar quantas vezes executou hoje
grep "EXECUTION REPORT" /var/log/auth-monitor.log | grep $(date +%Y-%m-%d) | wc -l
```

## 🎯 Casos de Uso Comuns

### Ajustar Sensibilidade

```bash
# Mais rigoroso (bloqueia após 5 falhas)
# No .env:
AUTH_BLOCK_THRESHOLD=5

# Mais permissivo (bloqueia após 20 falhas)
AUTH_BLOCK_THRESHOLD=20
```

### Adicionar IP à Whitelist

```bash
# No .env, adicione IPs separados por vírgula
AUTH_IP_WHITELIST=127.0.0.1,::1,192.168.1.1,seu.ip.fixo
```

### Aumentar Tempo de Bloqueio

```bash
# Bloquear por 24 horas (86400 segundos)
AUTH_BLOCK_DURATION=86400

# Bloquear por 1 semana (604800 segundos)
AUTH_BLOCK_DURATION=604800
```

### Desbloquear IP Manualmente

```sql
-- Conectar ao banco
mysql -u root -p meli

-- Desbloquear IP específico
DELETE FROM auth_blocked_ips WHERE ip_address = '192.168.1.100';

-- Limpar todos os bloqueios expirados
DELETE FROM auth_blocked_ips WHERE expires_at < NOW();
```

## 🔍 Troubleshooting Rápido

### ❌ "Database connection failed"
```bash
# Verificar credenciais no .env
grep ^DB_ .env

# Testar conexão
mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME -e "SELECT 1"
```

### ❌ "Failed to load .env file"
```bash
# Verificar se .env existe
ls -la .env

# Copiar do exemplo se necessário
cp .env.example .env
```

### ❌ "Email sending failed"
```bash
# Para Gmail, use App Passwords (não a senha normal)
# https://support.google.com/accounts/answer/185833

# Testar SMTP manualmente
telnet smtp.gmail.com 587
```

### ❌ Nenhuma falha detectada
```bash
# Verificar se há logs
ls -lh storage/logs/

# Criar log de teste
php bin/test-auth-monitor.php
```

## 📊 Monitoramento

### Dashboard Simples (SQL)

```sql
-- Estatísticas gerais
SELECT 
    COUNT(DISTINCT ip_address) as ips_unicos,
    COUNT(*) as total_falhas,
    DATE(detected_at) as dia
FROM auth_failure_log
WHERE detected_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY dia
ORDER BY dia DESC;

-- Top 10 IPs atacantes
SELECT 
    ip_address,
    COUNT(*) as tentativas,
    MIN(detected_at) as primeira_tentativa,
    MAX(detected_at) as ultima_tentativa
FROM auth_failure_log
GROUP BY ip_address
ORDER BY tentativas DESC
LIMIT 10;

-- IPs atualmente bloqueados
SELECT 
    ip_address,
    failure_count,
    blocked_at,
    TIMESTAMPDIFF(MINUTE, NOW(), expires_at) as minutos_restantes
FROM auth_blocked_ips
WHERE expires_at > NOW()
ORDER BY blocked_at DESC;
```

## 📚 Mais Informações

- **Documentação Completa**: [docs/AUTH_MONITOR_README.md](docs/AUTH_MONITOR_README.md)
- **Resumo da Implementação**: [AUTH_MONITOR_IMPLEMENTATION.md](AUTH_MONITOR_IMPLEMENTATION.md)
- **Exemplos de Configuração**: [.env.auth-monitor.example](.env.auth-monitor.example)
- **Exemplos de Cron**: [crontab.auth-monitor.example](crontab.auth-monitor.example)

## ✅ Checklist de Produção

- [ ] Configurado .env com credenciais corretas
- [ ] Testado em modo --dry-run
- [ ] Verificado conexão com banco de dados
- [ ] Testado envio de email (se configurado)
- [ ] Adicionado cron job
- [ ] Configurado rotação de logs
- [ ] Adicionado IPs confiáveis à whitelist
- [ ] Documentado procedimento de emergência
- [ ] Testado desbloquear IP manualmente

## 🆘 Suporte

Em caso de problemas, consulte:
1. Logs do sistema: `tail -f /var/log/auth-monitor.log`
2. Logs do cron: `grep CRON /var/log/syslog`
3. Documentação completa: `docs/AUTH_MONITOR_README.md`

---
**Status**: ✅ Sistema pronto para produção  
**Versão**: 1.0.0  
**Data**: 25/01/2026
