# 🚀 Guia de Deploy - Mercado Livre Manager

Guia completo para colocar o sistema em produção.

---

## 📋 Pré-requisitos

- Servidor com PHP 8.0+
- MySQL 8.0+
- Apache/Nginx
- SSL/HTTPS (obrigatório para produção)
- Composer instalado
- Git (opcional)

---

## 🔧 Passo 1: Preparar Servidor

### Instalar Dependências do Sistema

**Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install php8.0 php8.0-cli php8.0-mysql php8.0-curl php8.0-mbstring php8.0-xml php8.0-zip
sudo apt install mysql-server nginx
sudo apt install composer
```

**CentOS/RHEL:**
```bash
sudo yum install php php-cli php-mysql php-curl php-mbstring php-xml php-zip
sudo yum install mysql-server nginx
```

### Instalar Redis (Opcional mas Recomendado)

```bash
sudo apt install redis-server  # Ubuntu/Debian
sudo yum install redis         # CentOS/RHEL

sudo systemctl start redis
sudo systemctl enable redis
```

---

## 📥 Passo 2: Fazer Upload do Código

### Opção A: Git Clone

```bash
cd /var/www
git clone https://seu-repositorio.git mercadolivre-manager
cd mercadolivre-manager
composer install --no-dev --optimize-autoloader
```

### Opção B: Upload Manual

1. Compacte o projeto (exceto `vendor/` e `.env`)
2. Faça upload via FTP/SFTP
3. Descompacte no servidor
4. Execute: `composer install --no-dev`

---

## 🗄️ Passo 3: Configurar Banco de Dados

### Criar Banco

```bash
mysql -u root -p
```

```sql
CREATE DATABASE mercadolivre_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ml_user'@'localhost' IDENTIFIED BY 'senha_forte_aqui';
GRANT ALL PRIVILEGES ON mercadolivre_db.* TO 'ml_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Executar Migrations

```bash
mysql -u ml_user -p mercadolivre_db < database/migrations/000_install_all.sql
mysql -u ml_user -p mercadolivre_db < database/migrations/004_create_ml_orders_table.sql
mysql -u ml_user -p mercadolivre_db < database/migrations/005_create_notifications_and_alerts_tables.sql
mysql -u ml_user -p mercadolivre_db < database/migrations/006_create_price_history_table.sql
mysql -u ml_user -p mercadolivre_db < database/migrations/007_create_security_tables.sql
```

---

## ⚙️ Passo 4: Configurar Ambiente

### Criar .env

```bash
cp .env.example .env
nano .env
```

### Configurações Essenciais

```env
# Ambiente
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seusite.com.br

# Banco de Dados
DB_HOST=localhost
DB_PORT=3306
DB_NAME=mercadolivre_db
DB_USER=ml_user
DB_PASS=senha_forte_aqui

# Mercado Livre
ML_APP_ID=seu_app_id_producao
ML_CLIENT_SECRET=seu_secret_producao
ML_REDIRECT_URI=https://seusite.com.br/public/auth/callback

# Segurança (GERAR NOVA CHAVE!)
APP_KEY=chave_gerada_com_64_caracteres

# Cache
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

# E-mail
EMAIL_ENABLED=true
EMAIL_FROM=noreply@seusite.com.br
EMAIL_REPLY_TO=suporte@seusite.com.br
```

### Gerar Chave de Segurança

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Cole o resultado em `APP_KEY` no `.env`.

---

## 🌐 Passo 5: Configurar Web Server

### Nginx

Crie arquivo `/etc/nginx/sites-available/mercadolivre`:

```nginx
server {
    listen 80;
    server_name seusite.com.br;
    
    # Redirecionar para HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name seusite.com.br;
    
    root /var/www/mercadolivre-manager/public;
    index index.php;
    
    # SSL
    ssl_certificate /etc/letsencrypt/live/seusite.com.br/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/seusite.com.br/privkey.pem;
    
    # Segurança
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\. {
        deny all;
    }
}
```

Ativar:
```bash
sudo ln -s /etc/nginx/sites-available/mercadolivre /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Apache

Crie arquivo `/etc/apache2/sites-available/mercadolivre.conf`:

```apache
<VirtualHost *:80>
    ServerName seusite.com.br
    Redirect permanent / https://seusite.com.br/
</VirtualHost>

<VirtualHost *:443>
    ServerName seusite.com.br
    DocumentRoot /var/www/mercadolivre-manager/public
    
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/seusite.com.br/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/seusite.com.br/privkey.pem
    
    <Directory /var/www/mercadolivre-manager/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/mercadolivre_error.log
    CustomLog ${APACHE_LOG_DIR}/mercadolivre_access.log combined
</VirtualHost>
```

Ativar:
```bash
sudo a2enmod ssl rewrite
sudo a2ensite mercadolivre
sudo systemctl reload apache2
```

---

## 🔒 Passo 6: Configurar SSL

### Let's Encrypt (Gratuito)

```bash
sudo apt install certbot python3-certbot-nginx  # Nginx
sudo apt install certbot python3-certbot-apache  # Apache

# Nginx
sudo certbot --nginx -d seusite.com.br

# Apache
sudo certbot --apache -d seusite.com.br
```

Renovação automática:
```bash
sudo certbot renew --dry-run
```

---

## 🔐 Passo 7: Permissões

```bash
cd /var/www/mercadolivre-manager
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
sudo chmod -R 775 storage
sudo chmod -R 775 storage/cache
sudo chmod -R 775 storage/logs
```

---

## 🔄 Passo 8: Configurar CRON (Opcional)

Para tarefas agendadas (limpeza de cache, sincronização, etc.):

```bash
sudo crontab -e
```

Adicione:
```cron
# Limpar cache expirado (diariamente às 2h)
0 2 * * * cd /var/www/mercadolivre-manager && php -r "require 'vendor/autoload.php'; (new App\Services\CacheService())->cleanExpired();"

# Verificar tokens expirando (diariamente às 9h)
0 9 * * * cd /var/www/mercadolivre-manager && php -r "require 'vendor/autoload.php'; (new App\Services\AlertService())->checkExpiringTokens();"
```

---

## 📦 Passo 9: Configurar Webhooks

1. Acesse [developers.mercadolivre.com.br](https://developers.mercadolivre.com.br)
2. Vá em sua aplicação de **produção**
3. Configure webhook:
   - **URL:** `https://seusite.com.br/public/webhook/ml`
   - **Tópicos:** `orders`, `items`, `questions`
4. Salve configurações

---

## ✅ Passo 10: Verificação Final

### Checklist

- [ ] Banco de dados criado e migrations executadas
- [ ] Arquivo `.env` configurado
- [ ] Chave `APP_KEY` gerada
- [ ] SSL/HTTPS configurado
- [ ] Web server configurado
- [ ] Permissões corretas
- [ ] Webhooks configurados no ML
- [ ] Redis rodando (se usando)
- [ ] E-mail configurado (se usando)

### Testar

1. Acesse: `https://seusite.com.br/public/dashboard`
2. Vincule uma conta ML
3. Teste sincronização de pedidos
4. Verifique logs em `storage/logs/app.log`

---

## 🔄 Backup

### Script de Backup Automatizado

Crie `/usr/local/bin/backup-mercadolivre.sh`:

```bash
#!/bin/bash
BACKUP_DIR="/backups/mercadolivre"
DATE=$(date +%Y%m%d_%H%M%S)

# Criar diretório
mkdir -p $BACKUP_DIR

# Backup do banco
mysqldump -u ml_user -p'senha' mercadolivre_db > $BACKUP_DIR/db_$DATE.sql

# Backup dos arquivos
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/mercadolivre-manager

# Manter apenas últimos 7 dias
find $BACKUP_DIR -type f -mtime +7 -delete

echo "Backup concluído: $DATE"
```

Tornar executável:
```bash
sudo chmod +x /usr/local/bin/backup-mercadolivre.sh
```

Agendar (diariamente às 3h):
```bash
sudo crontab -e
0 3 * * * /usr/local/bin/backup-mercadolivre.sh
```

---

## 📊 Monitoramento

### Logs

- **Aplicação:** `storage/logs/app.log`
- **Nginx:** `/var/log/nginx/mercadolivre_error.log`
- **Apache:** `/var/log/apache2/mercadolivre_error.log`
- **PHP:** `/var/log/php8.0-fpm.log`

### Métricas

Monitore:
- Uso de CPU e memória
- Espaço em disco
- Conexões MySQL
- Requisições por segundo
- Taxa de erro HTTP

### Alertas

Configure alertas para:
- Espaço em disco < 20%
- CPU > 80%
- Memória > 90%
- Erros 500 frequentes
- Banco de dados offline

---

## 🔧 Manutenção

### Limpar Cache

```bash
cd /var/www/mercadolivre-manager
php -r "require 'vendor/autoload.php'; (new App\Services\CacheService())->flush();"
```

### Limpar Logs Antigos

```bash
find storage/logs -name "*.log" -mtime +30 -delete
```

### Otimizar Banco

```bash
mysqlcheck -u ml_user -p --optimize mercadolivre_db
```

---

## 🚨 Troubleshooting

### Erro 500

1. Verifique logs: `tail -f storage/logs/app.log`
2. Verifique permissões: `ls -la storage/`
3. Verifique `.env`: `cat .env | grep APP_`

### Erro de Conexão com Banco

1. Verifique se MySQL está rodando: `sudo systemctl status mysql`
2. Teste conexão: `mysql -u ml_user -p mercadolivre_db`
3. Verifique credenciais no `.env`

### Webhooks Não Funcionam

1. Verifique se URL está acessível: `curl https://seusite.com.br/public/webhook/ml`
2. Verifique logs: `tail -f storage/logs/app.log`
3. Verifique tabela: `SELECT * FROM webhook_logs ORDER BY created_at DESC LIMIT 10;`

---

## 📚 Recursos Adicionais

- [Documentação API](API_DOCUMENTATION.md)
- [Manual do Usuário](USER_MANUAL.md)
- [Guia de Segurança](SECURITY.md)
- [Configuração de Webhooks](WEBHOOK_SETUP.md)

---

**Última atualização:** 15 de Dezembro de 2024

