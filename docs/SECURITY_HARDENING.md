# 🔐 Guia de Hardening de Segurança

## Visão Geral

Este guia documenta todas as medidas de segurança implementadas no Mercado Livre Manager e como configurá-las corretamente em produção.

## Índice

1. [Criptografia de Dados](#criptografia-de-dados)
2. [Proteção de Tokens OAuth](#proteção-de-tokens-oauth)
3. [Middleware de Segurança](#middleware-de-segurança)
4. [Firewall e Fail2ban](#firewall-e-fail2ban)
5. [Headers de Segurança](#headers-de-segurança)
6. [Rate Limiting](#rate-limiting)
7. [Auditoria de Segurança](#auditoria-de-segurança)
8. [Checklist de Produção](#checklist-de-produção)

---

## 1. Criptografia de Dados

### EncryptionService

O sistema utiliza AES-256-GCM para criptografia de dados sensíveis.

#### Configuração

Adicione no `.env`:
```env
APP_KEY=sua-chave-de-32-caracteres-aqui
```

**Gerar chave segura:**
```bash
php -r "echo bin2hex(random_bytes(16));" 
```

#### Uso no Código

```php
use App\Services\EncryptionService;

$encryption = new EncryptionService();

// Criptografar dado único
$encrypted = $encryption->encrypt('dado sensível');
$decrypted = $encryption->decrypt($encrypted);

// Criptografar array (selectivo)
$data = [
    'name' => 'Público',
    'token' => 'SECRETO',
    'password' => 'SECRETO'
];
$encrypted = $encryption->encryptArray($data, ['token', 'password']);

// Hash de senha
$hash = $encryption->hashPassword('senha123');
$valid = $encryption->verifyPassword('senha123', $hash);
```

---

## 2. Proteção de Tokens OAuth

### SecureTokenService

Gerencia tokens do Mercado Livre de forma segura.

#### Migração de Tokens Existentes

```bash
# Via API
curl -X POST https://seu-dominio.com/api/security/migrate-tokens

# Via código
$tokenService = new SecureTokenService();
$result = $tokenService->migrateUnencryptedTokens();
// Retorna: ['migrated' => 5, 'errors' => []]
```

#### Verificar Status de Criptografia

```bash
curl https://seu-dominio.com/api/security/tokens-status
```

Resposta:
```json
{
  "success": true,
  "data": {
    "total_accounts": 10,
    "encrypted": 8,
    "unencrypted": 2,
    "encryption_percentage": 80
  }
}
```

#### Uso no Código

```php
use App\Services\SecureTokenService;

$tokenService = new SecureTokenService();

// Armazenar tokens (já criptografados automaticamente)
$tokenService->storeTokens($accountId, $accessToken, $refreshToken, $expiresAt);

// Recuperar tokens (descriptografados automaticamente)
$tokens = $tokenService->getTokens($accountId);
// ['access_token' => '...', 'refresh_token' => '...', 'expires_at' => '...']

// Obter token válido (renova automaticamente se expirado)
$accessToken = $tokenService->getValidAccessToken($accountId);
```

---

## 3. Middleware de Segurança

### SecurityMiddleware

Proteção automática em todas as requisições.

#### Funcionalidades

- ✅ Bloqueio de IPs maliciosos
- ✅ Detecção de padrões de ataque (SQLi, XSS, LFI)
- ✅ Rate limiting por IP
- ✅ Bloqueio de user agents suspeitos
- ✅ Headers de segurança automáticos
- ✅ Força HTTPS em produção

#### Configuração no .env

```env
# Rate Limiting
RATE_LIMIT_ENABLED=true
RATE_LIMIT_MAX=100          # Requisições por janela
RATE_LIMIT_WINDOW=60        # Janela em segundos

# HTTPS
FORCE_HTTPS=true            # Redireciona HTTP para HTTPS
```

#### Integração no index.php

```php
// Já integrado automaticamente
use App\Middleware\SecurityMiddleware;

$security = new SecurityMiddleware();
if (!$security->handle()) {
    exit; // Requisição bloqueada
}
```

---

## 4. Firewall e Fail2ban

### Configuração UFW

```bash
# Executar script de configuração
sudo bash scripts/setup_security.sh

# Ou manualmente:
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp      # SSH
sudo ufw allow 80/tcp      # HTTP
sudo ufw allow 443/tcp     # HTTPS
sudo ufw enable
```

### Configuração Fail2ban

O script `setup_security.sh` cria automaticamente:

**Jail personalizado** (`/etc/fail2ban/jail.local`):
```ini
[ml-manager-auth]
enabled = true
port = http,https
filter = ml-manager-auth
logpath = /home/eskill/htdocs/eskill.com.br/storage/logs/security.log
maxretry = 5
bantime = 3600
findtime = 600
```

**Filtro** (`/etc/fail2ban/filter.d/ml-manager-auth.conf`):
```ini
[Definition]
failregex = ^.*"ip_address":"<HOST>".*"event_type":"(failed_login|attack_pattern|rate_limit_exceeded)".*$
ignoreregex =
```

### Verificar Status

```bash
sudo fail2ban-client status ml-manager-auth
sudo fail2ban-client status sshd
```

---

## 5. Headers de Segurança

Os seguintes headers são adicionados automaticamente:

| Header | Valor | Função |
|--------|-------|--------|
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | Força HTTPS |
| `X-Frame-Options` | `SAMEORIGIN` | Previne clickjacking |
| `X-XSS-Protection` | `1; mode=block` | Proteção XSS (legacy) |
| `X-Content-Type-Options` | `nosniff` | Previne MIME sniffing |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Controle de referência |
| `Content-Security-Policy` | Ver abaixo | Previne injeção de conteúdo |
| `Permissions-Policy` | `geolocation=(), microphone=(), camera=()` | Bloqueia APIs sensíveis |

### CSP Configurado

```
default-src 'self';
script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;
style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;
img-src 'self' data: https:;
font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;
connect-src 'self' https://api.mercadolibre.com https://api.mercadolivre.com.br
```

---

## 6. Rate Limiting

### Limites Padrão

| Endpoint | Limite | Janela |
|----------|--------|--------|
| Geral | 100 req | 60s |
| Login | 5 tentativas | 15min |
| API | 1000 req | 60s |

### Personalizar por Rota

```php
// Em config/app.php
'rate_limits' => [
    'default' => ['max' => 100, 'window' => 60],
    'login' => ['max' => 5, 'window' => 900],
    'api' => ['max' => 1000, 'window' => 60],
]
```

### Resposta de Rate Limit

```json
{
  "error": true,
  "message": "Muitas requisições. Tente novamente em alguns minutos.",
  "code": 429
}
```

---

## 7. Auditoria de Segurança

### Dashboard de Segurança

Acesse: `https://seu-dominio.com/security`

Funcionalidades:
- Visualização de eventos em tempo real
- Gerenciamento de IPs bloqueados
- Estatísticas de segurança
- Migração de tokens
- Exportação de relatórios

### API de Segurança

```bash
# Listar eventos
GET /api/security/events?hours=24&severity=critical

# Estatísticas
GET /api/security/stats?hours=24

# IPs bloqueados
GET /api/security/blocked-ips

# Bloquear IP
POST /api/security/block-ip
{
  "ip": "192.168.1.100",
  "reason": "Tentativa de ataque",
  "duration": 3600
}

# Desbloquear IP
POST /api/security/unblock-ip
{ "ip": "192.168.1.100" }

# Exportar relatório
GET /api/security/export?format=json&hours=24
```

### Tabelas de Auditoria

```sql
-- Log de eventos
security_audit_log (
    id, event_type, ip_address, user_agent, 
    details, severity, created_at
)

-- IPs bloqueados
blocked_ips (
    id, ip_address, reason, blocked_until,
    attempts, created_at, updated_at
)

-- Sessões de usuário
user_sessions (
    id, user_id, session_token, ip_address,
    user_agent, expires_at, created_at, last_activity
)
```

---

## 8. Checklist de Produção

### Antes do Deploy

- [ ] Gerar `APP_KEY` único e forte
- [ ] Configurar `FORCE_HTTPS=true`
- [ ] Revisar `APP_DEBUG=false`
- [ ] Configurar backup de chave de criptografia

### Após o Deploy

- [ ] Executar `scripts/setup_security.sh`
- [ ] Verificar firewall: `sudo ufw status`
- [ ] Verificar fail2ban: `sudo fail2ban-client status`
- [ ] Migrar tokens: `POST /api/security/migrate-tokens`
- [ ] Verificar headers: [SecurityHeaders.com](https://securityheaders.com)

### Monitoramento Contínuo

- [ ] Revisar logs de segurança diariamente
- [ ] Monitorar IPs bloqueados
- [ ] Verificar rate limit excedido
- [ ] Backup das chaves de criptografia
- [ ] Atualizar dependências regularmente

### Rotação de Chaves

Para rotacionar a `APP_KEY` sem perda de dados:

1. Descriptografar todos os tokens com chave antiga
2. Atualizar `APP_KEY` no `.env`
3. Re-criptografar tokens com nova chave

```php
// Script de rotação
$oldKey = 'chave-antiga';
$newKey = 'chave-nova';

$tokenService = new SecureTokenService($oldKey);
$accounts = getAllAccounts();

foreach ($accounts as $account) {
    $tokens = $tokenService->getTokens($account['id']);
    // Atualizar com nova chave...
}
```

---

## Contato de Emergência

Em caso de incidente de segurança:

1. Bloquear IPs suspeitos imediatamente
2. Verificar logs de segurança
3. Revogar tokens comprometidos no ML
4. Notificar administrador do sistema

```bash
# Bloquear IP de emergência
sudo fail2ban-client set ml-manager-auth banip IP_SUSPEITO

# Ver últimos eventos críticos
tail -f storage/logs/security.log | grep critical
```

---

*Última atualização: <?= date('Y-m-d') ?>*
