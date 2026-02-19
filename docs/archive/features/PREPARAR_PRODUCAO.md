# 🚀 Preparar Sistema para Produção

## 🎯 Scripts Automatizados Disponíveis

### 1. Setup Completo de Produção
```bash
# Configuração completa automática
./scripts/setup_production.sh
```
**O que faz:** Configura .env, valida dependências, executa migrações, testa aplicação

### 2. Configuração PHP Específica
```bash
# Otimizar PHP para produção
sudo ./scripts/configure_php_production.sh
```
**O que faz:** Ajusta php.ini, instala extensões, configura logs, aplica configurações de segurança

### 3. Configuração Banco Dedicado
```bash
# Criar usuário e banco seguros
sudo ./scripts/setup_database_production.sh
```
**O que faz:** Cria usuário dedicado, configura privilégios mínimos, executa migrações, configura backup

---

## ⚠️ Checklist Crítico (Automático nos Scripts)

### 1. Configurar .env de Produção

```bash
# Copiar exemplo
cp .env.production.example .env

# Editar e configurar
nano .env  # ou use seu editor preferido
```

**Configurações OBRIGATÓRIAS:**
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seudominio.com.br
APP_KEY=gerar_com_openssl_rand_-hex_32
```

### 2. Gerar Chave de Segurança

```bash
openssl rand -hex 32
```

Cole o resultado no `.env` em `APP_KEY`.

### 3. Configurar Banco de Dados

**NUNCA use root em produção!**

```sql
CREATE USER 'ml_user'@'localhost' IDENTIFIED BY 'senha_forte_aqui';
GRANT ALL PRIVILEGES ON mercadolivre_db.* TO 'ml_user'@'localhost';
FLUSH PRIVILEGES;
```

### 4. Configurar SSL/HTTPS

**Let's Encrypt (Recomendado):**
```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d seudominio.com.br
```

### 5. Configurar PHP para Produção

Edite `php.ini`:
```ini
display_errors = Off
log_errors = On
error_log = /var/log/php/errors.log
expose_php = Off
```

### 6. Configurar Apache/Nginx

**Apache (.htaccess):**
```apache
# Forçar HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Headers de segurança
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

### 7. Configurar Backup Automatizado

**Linux (CRON):**
```bash
# Editar crontab
crontab -e

# Adicionar linha (backup diário às 2h)
0 2 * * * /caminho/para/scripts/backup_production.sh
```

**Windows (Agendador de Tarefas):**
- Criar tarefa agendada
- Executar: `scripts\backup_production.bat`
- Frequência: Diária

### 8. Configurar Monitoramento

**Health Check (CRON):**
```bash
# Verificar saúde a cada 5 minutos
*/5 * * * * php /caminho/para/scripts/health_check.php
```

### 9. Executar Migrations

```bash
php scripts/migrate.php
```

### 10. Limpar Cache

```bash
php -r "require 'vendor/autoload.php'; (new App\Services\CacheService())->clear();"
```

### 11. Verificar Permissões

```bash
chmod -R 755 storage
chmod 600 .env
chmod 644 public/.htaccess
```

### 12. Testar Sistema

1. Acesse: `https://seudominio.com.br/check.php`
2. Verifique se não há erros
3. Teste login/registro
4. Teste funcionalidades principais

---

## 🔒 Segurança Adicional

### Firewall
```bash
# Ubuntu/Debian
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

### Fail2ban (Proteção contra brute force)
```bash
sudo apt install fail2ban
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

### Desabilitar Listagem de Diretórios
Já configurado no `.htaccess`:
```apache
Options -Indexes
```

---

## 📊 Monitoramento Recomendado

### 1. Logs
- Monitorar `storage/logs/app.log`
- Monitorar `storage/logs/php_errors.log`
- Configurar rotação de logs

### 2. Métricas
- Uptime monitoring (UptimeRobot, Pingdom)
- Performance monitoring (New Relic, Datadog)
- Error tracking (Sentry)

### 3. Alertas
- Configurar Telegram para alertas críticos
- E-mail para notificações importantes

---

## ✅ Verificação Final

Antes de considerar em produção:

- [ ] `.env` configurado com `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` forte gerado
- [ ] HTTPS configurado e funcionando
- [ ] Banco de dados com usuário dedicado
- [ ] Backup automatizado configurado
- [ ] Monitoramento ativo
- [ ] Logs configurados
- [ ] Permissões corretas
- [ ] Testes realizados
- [ ] Documentação atualizada

---

## 🚨 Após Deploy

1. **Monitorar logs** nas primeiras 24h
2. **Verificar métricas** regularmente
3. **Testar funcionalidades** críticas
4. **Verificar backups** estão sendo criados
5. **Revisar segurança** periodicamente

---

**Pronto para produção após completar este checklist!** ✅
