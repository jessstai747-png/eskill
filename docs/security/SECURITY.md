# 🔐 Guia de Segurança - Mercado Livre Manager

Este documento descreve as medidas de segurança implementadas no sistema.

---

## 🔒 Criptografia

### Tokens OAuth2

Todos os tokens do Mercado Livre são **criptografados** antes de serem armazenados no banco de dados.

- **Algoritmo:** AES-256-CBC
- **Chave:** Configurada via `APP_KEY` no `.env`
- **IV:** Gerado aleatoriamente para cada criptografia

**Importante:** Sempre gere uma chave forte:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Cole o resultado no `.env`:
```env
APP_KEY=sua_chave_gerada_aqui
```

### Senhas de Usuários

- Hash usando **bcrypt** com cost 12
- Nunca armazenadas em texto plano
- Verificação segura com `password_verify()`

---

## 🛡️ Proteção CSRF

### Como Funciona

1. Token CSRF é gerado por sessão
2. Token expira em 1 hora
3. Validação automática em POST/PUT/DELETE
4. Token pode ser enviado via:
   - Header `X-CSRF-Token`
   - Campo `_token` no formulário

### Uso em Formulários

```php
<?php
use App\Helpers\SecurityHelper;
?>

<form method="POST">
    <?= SecurityHelper::csrfField() ?>
    <!-- outros campos -->
</form>
```

### Uso em AJAX

```javascript
fetch('/api/endpoint', {
    method: 'POST',
    headers: {
        'X-CSRF-Token': '<?= SecurityHelper::csrfToken() ?>',
        'Content-Type': 'application/json'
    },
    body: JSON.stringify(data)
});
```

---

## 🚦 Rate Limiting

### Configuração

- **Limite:** 100 requisições por minuto por IP
- **Janela:** 60 segundos
- **Resposta:** HTTP 429 quando excedido
- **Header:** `Retry-After` com segundos restantes

### Exceções

Rate limiting **não** é aplicado em:
- Webhooks (`/webhook/ml`)
- APIs públicas (se configurado)

### Personalização

Para alterar limites, edite `public/index.php`:

```php
$rateLimit = new App\Middleware\RateLimitMiddleware(
    100,  // max requests
    60    // window in seconds
);
```

---

## 🧹 Proteção XSS

### Sanitização Automática

Todos os dados de entrada são sanitizados:

```php
use App\Services\SecurityService;

$security = new SecurityService();
$safe = $security->sanitize($userInput);
```

### Em Views

Use o helper:

```php
<?= SecurityHelper::e($userData) ?>
```

Ou diretamente:

```php
<?= htmlspecialchars($data, ENT_QUOTES, 'UTF-8') ?>
```

---

## 📋 Logs de Auditoria

### O que é Registrado

- Ações de usuários
- Acesso a contas ML
- Modificações de dados
- IP e User-Agent
- Timestamp preciso

### Consultar Logs

```sql
SELECT * FROM audit_logs 
WHERE user_id = 1 
ORDER BY created_at DESC;
```

Via API:
```
GET /api/audit?user_id=1&action=login
```

### Tipos de Ações

- `login` - Login de usuário
- `logout` - Logout
- `account_linked` - Conta ML vinculada
- `account_unlinked` - Conta ML desvinculada
- `order_synced` - Pedido sincronizado
- `token_refreshed` - Token renovado

---

## 🔐 Boas Práticas

### 1. Chave de Criptografia

- ✅ Use chave de 64 caracteres (32 bytes em hex)
- ✅ Nunca commite a chave no Git
- ✅ Use `.env` para configuração
- ✅ Gere nova chave para cada ambiente

### 2. Tokens

- ✅ Sempre criptografados no banco
- ✅ Descriptografados apenas quando necessário
- ✅ Nunca logados em texto plano
- ✅ Renovados automaticamente

### 3. Senhas

- ✅ Hash com bcrypt
- ✅ Nunca armazenadas em texto plano
- ✅ Verificação segura
- ✅ Política de senha forte (implementar)

### 4. Rate Limiting

- ✅ Aplicado por IP
- ✅ Configurável por endpoint
- ✅ Logs de tentativas bloqueadas

### 5. CSRF

- ✅ Tokens por sessão
- ✅ Expiração de 1 hora
- ✅ Validação em todas as modificações
- ✅ Regeneração após uso (opcional)

---

## 🚨 Incidentes de Segurança

### Se Tokens Forem Comprometidos

1. Revogue tokens no Mercado Livre
2. Desvincule contas comprometidas
3. Gere nova `APP_KEY`
4. Re-criptografe todos os tokens
5. Revise logs de auditoria

### Se Banco de Dados For Comprometido

1. Tokens estão criptografados (proteção parcial)
2. Senhas estão com hash (proteção total)
3. Revogue todos os tokens OAuth2
4. Force re-autenticação de todos os usuários
5. Gere nova `APP_KEY`

---

## 📊 Monitoramento

### Verificar Segurança

```sql
-- Tokens próximos de expirar
SELECT * FROM ml_accounts 
WHERE token_expires_at < DATE_ADD(NOW(), INTERVAL 7 DAY);

-- Tentativas de rate limit
SELECT ip_address, COUNT(*) as attempts 
FROM rate_limits 
WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY ip_address
HAVING attempts > 50;

-- Logs de auditoria suspeitos
SELECT * FROM audit_logs 
WHERE action LIKE '%failed%' 
ORDER BY created_at DESC;
```

---

## 🔧 Configuração Recomendada

### .env (Produção)

```env
# Segurança
APP_KEY=chave_gerada_com_64_caracteres
APP_ENV=production
APP_DEBUG=false

# Rate Limiting
RATE_LIMIT_MAX=100
RATE_LIMIT_WINDOW=60

# Cache
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=sua_senha_redis
```

### Servidor

- ✅ HTTPS obrigatório
- ✅ Headers de segurança (HSTS, CSP)
- ✅ Firewall configurado
- ✅ Backups regulares
- ✅ Logs monitorados

---

**Última atualização:** 15 de Dezembro de 2024

