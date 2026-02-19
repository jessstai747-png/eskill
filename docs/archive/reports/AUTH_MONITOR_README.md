# Auth Failure Monitor

Script PHP de linha de comando para monitoramento de falhas de autenticação, bloqueio automático de IPs suspeitos e envio de alertas por e-mail.

## 🎯 Funcionalidades

- **Análise de Logs**: Processa arquivos de log em busca de tentativas de autenticação falhas
- **Detecção Inteligente**: Identifica padrões de falha em formatos texto e JSON
- **Bloqueio Automático**: Bloqueia IPs que excedem limite configurável de falhas
- **Whitelist**: IPs confiáveis nunca são bloqueados
- **Alertas por E-mail**: Notificações HTML quando volume de falhas é alto
- **Modo Dry-Run**: Simula ações sem realizar bloqueios ou envios
- **Relatório JSON**: Estatísticas detalhadas da execução

## 📋 Requisitos

- PHP 8.0+
- Extensões: PDO, JSON, cURL
- Composer packages:
  - `vlucas/phpdotenv` - Carregamento de variáveis de ambiente
  - `phpmailer/phpmailer` - Envio de e-mails

## 🚀 Instalação

1. O script já está disponível em `bin/monitor-auth-failures.php`
2. Configure as variáveis de ambiente no `.env`:

```bash
# Copie o exemplo
cp .env.auth-monitor.example .env

# Edite com suas configurações
vim .env
```

3. Certifique-se de que o banco de dados está acessível (as tabelas serão criadas automaticamente)

## ⚙️ Configuração

### Variáveis de Ambiente Obrigatórias

```env
# Banco de Dados
DB_HOST=localhost
DB_PORT=3306
DB_NAME=meli
DB_USER=root
DB_PASS=sua_senha

# E-mail (para alertas)
ADMIN_EMAIL=admin@exemplo.com.br
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=seu-email@gmail.com
SMTP_PASS=sua-senha-app
SMTP_SECURE=tls
```

### Variáveis Opcionais (com valores padrão)

```env
AUTH_BLOCK_THRESHOLD=10              # Falhas para bloquear IP
AUTH_FAILURE_ALERT_THRESHOLD=50      # Falhas totais para alerta
AUTH_BLOCK_DURATION=3600             # Duração do bloqueio (segundos)
AUTH_TIME_WINDOW=3600                # Janela de análise (segundos)
AUTH_IP_WHITELIST=127.0.0.1,::1     # IPs protegidos
```

## 📖 Uso

### Comandos Básicos

```bash
# Executar em produção
php bin/monitor-auth-failures.php

# Modo de teste (não bloqueia nem envia e-mails)
php bin/monitor-auth-failures.php --dry-run --verbose

# Produção com saída detalhada
php bin/monitor-auth-failures.php --verbose

# Mostrar ajuda
php bin/monitor-auth-failures.php --help
```

### Opções da Linha de Comando

| Opção       | Descrição                                          |
|-------------|---------------------------------------------------|
| `--dry-run` | Simula ações sem bloquear IPs ou enviar e-mails   |
| `--verbose` | Mostra saída detalhada no console                 |
| `--help`    | Exibe mensagem de ajuda                           |

### Restrição de Ambiente

Por padrão, o script **só executa em produção** (`APP_ENV=production`). Para executar em desenvolvimento, use a flag `--verbose`:

```bash
# Em desenvolvimento
APP_ENV=development php bin/monitor-auth-failures.php --verbose
```

## 📊 Estrutura do Banco de Dados

O script cria automaticamente duas tabelas:

### auth_blocked_ips

Armazena IPs bloqueados:

```sql
CREATE TABLE auth_blocked_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    reason TEXT,
    failure_count INT NOT NULL DEFAULT 0,
    blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    INDEX idx_ip (ip_address),
    INDEX idx_expires (expires_at)
);
```

### auth_failure_log

Registra cada falha individual:

```sql
CREATE TABLE auth_failure_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    username VARCHAR(255),
    failure_type VARCHAR(100),
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip (ip_address),
    INDEX idx_detected (detected_at)
);
```

## 🔍 Padrões de Detecção

O script identifica falhas de autenticação pelos seguintes padrões (case-insensitive):

- `login failed`
- `authentication failed`
- `invalid credentials`
- `brute force`
- `failed login`
- `unauthorized`
- `access denied`
- `401` (código HTTP)
- `403` (código HTTP)

## 📁 Análise de Logs

- **Diretório**: `storage/logs/`
- **Arquivos**: `*.log` e `*.log*` (incluindo rotacionados)
- **Formatos suportados**:
  - Logs em texto plano
  - Logs em formato JSON
- **Extração de dados**:
  - Endereços IP (IPv4 e IPv6)
  - User-Agent
  - Username (quando disponível)
  - Timestamp

## 📧 Alertas por E-mail

Quando o número total de falhas excede `AUTH_FAILURE_ALERT_THRESHOLD`, um e-mail HTML é enviado contendo:

- Total de falhas detectadas
- Número de IPs bloqueados
- Tabela com top 20 IPs ofensores
- Lista de IPs bloqueados
- Timestamp da detecção

### Exemplo de E-mail

```
⚠️ Authentication Failure Alert

Total Failures: 127
IPs Blocked: 5
Detection Time: 2026-01-25 14:30:00

Top Offending IPs:
IP Address          Failure Count    Status
192.168.1.100       45               BLOCKED
10.0.0.50           23               BLOCKED
...
```

## 📈 Relatório de Execução

Ao final, o script exibe um relatório JSON com estatísticas:

```json
{
    "logs_analyzed": 15,
    "failures_detected": 127,
    "unique_ips": 23,
    "ips_blocked": 5,
    "ips_expired": 2,
    "alerts_sent": 1,
    "errors": [],
    "execution_time": 2.45,
    "timestamp": "2026-01-25 14:30:00"
}
```

## 🔄 Automação com Cron

Para executar periodicamente (recomendado):

```bash
# Editar crontab
crontab -e

# Executar a cada 15 minutos
*/15 * * * * cd /caminho/do/projeto && php bin/monitor-auth-failures.php >> /var/log/auth-monitor.log 2>&1

# Ou executar a cada hora
0 * * * * cd /caminho/do/projeto && php bin/monitor-auth-failures.php
```

## 🛡️ Boas Práticas

### Whitelist

Sempre inclua IPs confiáveis na whitelist:

```env
AUTH_IP_WHITELIST=127.0.0.1,::1,192.168.1.1,seu.ip.fixo
```

### Thresholds

Ajuste os limites conforme seu volume de tráfego:

- **Tráfego baixo**: `AUTH_BLOCK_THRESHOLD=5`
- **Tráfego médio**: `AUTH_BLOCK_THRESHOLD=10` (padrão)
- **Tráfego alto**: `AUTH_BLOCK_THRESHOLD=20`

### Duração do Bloqueio

- **Temporário**: `AUTH_BLOCK_DURATION=1800` (30 min)
- **Padrão**: `AUTH_BLOCK_DURATION=3600` (1 hora)
- **Longo**: `AUTH_BLOCK_DURATION=86400` (24 horas)

### Janela de Tempo

- **Análise rápida**: `AUTH_TIME_WINDOW=1800` (30 min)
- **Padrão**: `AUTH_TIME_WINDOW=3600` (1 hora)
- **Análise ampla**: `AUTH_TIME_WINDOW=7200` (2 horas)

## 🧪 Testes

### Teste em Dry-Run

Recomendado antes de colocar em produção:

```bash
php bin/monitor-auth-failures.php --dry-run --verbose
```

Este modo:
- ✅ Analisa logs
- ✅ Detecta falhas
- ✅ Identifica IPs a serem bloqueados
- ✅ Simula envio de e-mail
- ❌ **NÃO** bloqueia IPs
- ❌ **NÃO** envia e-mails reais
- ❌ **NÃO** grava no banco (exceto dry-run dos bloqueios)

### Verificação do Banco

```bash
# Conectar ao MySQL
mysql -u root -p meli

# Verificar IPs bloqueados
SELECT * FROM auth_blocked_ips WHERE expires_at > NOW();

# Verificar log de falhas
SELECT ip_address, COUNT(*) as total 
FROM auth_failure_log 
WHERE detected_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY ip_address 
ORDER BY total DESC 
LIMIT 10;

# Limpar bloqueios expirados manualmente
DELETE FROM auth_blocked_ips WHERE expires_at < NOW();
```

## 🔧 Troubleshooting

### Erro: "Database connection failed"

```bash
# Verificar credenciais no .env
cat .env | grep DB_

# Testar conexão
mysql -h $DB_HOST -P $DB_PORT -u $DB_USER -p$DB_PASS $DB_NAME
```

### Erro: "Failed to load .env file"

```bash
# Verificar se arquivo existe
ls -la .env

# Verificar permissões
chmod 644 .env
```

### Erro: "Email sending failed"

```bash
# Para Gmail, use App Passwords:
# https://support.google.com/accounts/answer/185833

# Verificar configuração SMTP no .env
cat .env | grep SMTP_
```

### Nenhuma falha detectada

```bash
# Verificar arquivos de log
ls -lh storage/logs/

# Ver últimas linhas dos logs
tail -n 50 storage/logs/*.log | grep -i "failed\|unauthorized\|401\|403"
```

## 📝 Exemplos de Logs Detectados

### Formato Texto

```
[2026-01-25 14:30:00] ERROR: Login failed for user admin from 192.168.1.100
[2026-01-25 14:30:05] WARNING: Authentication failed from 10.0.0.50
```

### Formato JSON

```json
{"timestamp":"2026-01-25T14:30:00Z","level":"error","message":"Invalid credentials","ip":"192.168.1.100","user_agent":"Mozilla/5.0...","username":"admin"}
```

## 🤝 Integração com Firewall

Para integração real com firewall (iptables, fail2ban, etc.), adicione ao método `blockIp()`:

```php
// Exemplo: adicionar regra iptables
exec("iptables -A INPUT -s {$ip} -j DROP");

// Ou usar fail2ban
exec("fail2ban-client set sshd banip {$ip}");
```

## 📚 Referências

- [PHPMailer Documentation](https://github.com/PHPMailer/PHPMailer)
- [PHP PDO Documentation](https://www.php.net/manual/en/book.pdo.php)
- [Dotenv Documentation](https://github.com/vlucas/phpdotenv)

## 📄 Licença

MIT License - Eskill Team 2026
