# 📝 Auth Failure Monitor - Cheat Sheet

Referência rápida de comandos e queries mais usados.

## 🚀 Comandos CLI

```bash
# Dashboard visual de status (NOVO!)
php bin/auth-status.php

# Ajuda
php bin/monitor-auth-failures.php --help

# Teste (não faz alterações)
php bin/monitor-auth-failures.php --dry-run --verbose

# Produção (silencioso)
php bin/monitor-auth-failures.php

# Produção (detalhado)
php bin/monitor-auth-failures.php --verbose

# Teste completo com logs simulados
php bin/test-auth-monitor.php

# Validar sintaxe
php -l bin/monitor-auth-failures.php

# Verificar versão PHP
php -v
```

## ⚙️ Configuração Rápida

```bash
# Copiar configuração
cp .env.auth-monitor.example .env

# Editar configuração
vim .env

# Testar conexão banco
mysql -h localhost -u root -p meli -e "SELECT 1"

# Adicionar ao cron
crontab -e
# Adicionar: */15 * * * * cd /home/eskill/htdocs/eskill.com.br && php bin/monitor-auth-failures.php >> /var/log/auth-monitor.log 2>&1
```

## 💾 Queries SQL

### Consultas

```sql
-- IPs atualmente bloqueados
SELECT ip_address, failure_count, blocked_at, expires_at
FROM auth_blocked_ips 
WHERE expires_at > NOW()
ORDER BY blocked_at DESC;

-- Top 10 IPs atacantes (última hora)
SELECT ip_address, COUNT(*) as tentativas
FROM auth_failure_log
WHERE detected_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY ip_address
ORDER BY tentativas DESC
LIMIT 10;

-- Estatísticas diárias (última semana)
SELECT 
    DATE(detected_at) as dia,
    COUNT(*) as total_falhas,
    COUNT(DISTINCT ip_address) as ips_unicos
FROM auth_failure_log
WHERE detected_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY dia
ORDER BY dia DESC;

-- IPs que serão desbloqueados em breve
SELECT 
    ip_address,
    TIMESTAMPDIFF(MINUTE, NOW(), expires_at) as minutos_restantes
FROM auth_blocked_ips
WHERE expires_at > NOW()
ORDER BY expires_at ASC;
```

### Manutenção

```sql
-- Desbloquear IP específico
DELETE FROM auth_blocked_ips 
WHERE ip_address = '192.168.1.100';

-- Limpar bloqueios expirados
DELETE FROM auth_blocked_ips 
WHERE expires_at < NOW();

-- Limpar log antigo (> 30 dias)
DELETE FROM auth_failure_log 
WHERE detected_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Bloquear IP manualmente
INSERT INTO auth_blocked_ips 
(ip_address, reason, failure_count, expires_at)
VALUES 
('192.168.1.100', 'Bloqueio manual', 999, DATE_ADD(NOW(), INTERVAL 24 HOUR));

-- Ver total de registros
SELECT 
    (SELECT COUNT(*) FROM auth_blocked_ips) as ips_bloqueados,
    (SELECT COUNT(*) FROM auth_failure_log) as total_falhas;
```

## 📊 Monitoramento

### Logs

```bash
# Ver log do monitor
tail -f /var/log/auth-monitor.log

# Últimas 50 linhas
tail -n 50 /var/log/auth-monitor.log

# Buscar por IP específico
grep "192.168.1.100" /var/log/auth-monitor.log

# Contar execuções hoje
grep "EXECUTION REPORT" /var/log/auth-monitor.log | grep $(date +%Y-%m-%d) | wc -l

# Ver apenas erros
grep "ERROR" /var/log/auth-monitor.log

# Ver IPs bloqueados hoje
grep "BLOCKED" /var/log/auth-monitor.log | grep $(date +%Y-%m-%d)
```

### Cron

```bash
# Ver cron jobs
crontab -l

# Logs do cron
grep CRON /var/log/syslog

# Última execução
grep "monitor-auth-failures" /var/log/syslog | tail -n 1

# Verificar se está rodando
ps aux | grep monitor-auth-failures
```

## 🔧 Troubleshooting

### Erro de Banco

```bash
# Testar conexão
mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME -e "SELECT 1"

# Ver usuários MySQL
mysql -u root -p -e "SELECT user, host FROM mysql.user"

# Criar banco se não existir
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS meli"

# Dar permissões
mysql -u root -p -e "GRANT ALL ON meli.* TO 'root'@'localhost'"
```

### Erro de Email

```bash
# Testar SMTP (Gmail)
telnet smtp.gmail.com 587

# Gerar App Password (Gmail):
# https://myaccount.google.com/apppasswords

# Testar envio com PHP
php -r "var_dump(mail('test@example.com', 'Test', 'Body'));"
```

### Limpar Tudo

```sql
-- ⚠️ CUIDADO: Remove todos os dados
TRUNCATE TABLE auth_blocked_ips;
TRUNCATE TABLE auth_failure_log;
```

## 📈 Estatísticas Rápidas

```sql
-- Dashboard em uma query
SELECT 
    (SELECT COUNT(*) FROM auth_blocked_ips WHERE expires_at > NOW()) as bloqueados_ativos,
    (SELECT COUNT(*) FROM auth_failure_log WHERE detected_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)) as falhas_ultima_hora,
    (SELECT COUNT(*) FROM auth_failure_log WHERE detected_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as falhas_ultimo_dia,
    (SELECT COUNT(DISTINCT ip_address) FROM auth_failure_log WHERE detected_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as ips_unicos_24h;
```

## ⚙️ Variáveis de Ambiente

```bash
# Ver configuração atual
grep ^AUTH_ .env
grep ^DB_ .env
grep ^SMTP_ .env

# Configuração padrão
AUTH_BLOCK_THRESHOLD=10          # Falhas para bloquear
AUTH_FAILURE_ALERT_THRESHOLD=50  # Total para alerta
AUTH_BLOCK_DURATION=3600         # Segundos (1h)
AUTH_TIME_WINDOW=3600            # Janela de análise (1h)
AUTH_IP_WHITELIST=127.0.0.1,::1  # IPs protegidos
```

## 🎯 Casos de Uso

### Bloquear Agressivo
```bash
AUTH_BLOCK_THRESHOLD=3
AUTH_BLOCK_DURATION=86400  # 24 horas
```

### Apenas Alertar (não bloquear)
```bash
AUTH_BLOCK_THRESHOLD=9999
AUTH_FAILURE_ALERT_THRESHOLD=10
```

### Análise de 12 horas
```bash
AUTH_TIME_WINDOW=43200
```

## 📋 Checklist Diário

```bash
# 1. Verificar execuções
grep "EXECUTION REPORT" /var/log/auth-monitor.log | tail -n 3

# 2. Ver IPs bloqueados
mysql meli -e "SELECT COUNT(*) FROM auth_blocked_ips WHERE expires_at > NOW()"

# 3. Ver atividade recente
mysql meli -e "SELECT COUNT(*) FROM auth_failure_log WHERE detected_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"

# 4. Verificar erros
grep ERROR /var/log/auth-monitor.log | tail -n 10
```

---

**Dica**: Salve este arquivo em `/usr/local/share/auth-monitor-cheatsheet.txt` para acesso rápido!
