# 🔒 Configuração SSL/HTTPS para Produção

## 1. Certificado Let's Encrypt (Recomendado - Gratuito)

### Instalação Certbot (Ubuntu/Debian)
```bash
# Instalar certbot
sudo apt update
sudo apt install certbot python3-certbot-apache

# Para Apache
sudo certbot --apache -d seudominio.com

# Para Nginx
sudo apt install python3-certbot-nginx
sudo certbot --nginx -d seudominio.com
```

### Renovação Automática
```bash
# Testar renovação
sudo certbot renew --dry-run

# Adicionar ao crontab para renovação automática
sudo crontab -e
# Adicionar linha:
0 12 * * * /usr/bin/certbot renew --quiet
```

## 2. Configuração Apache

### Arquivo Virtual Host (Apache)
```apache
# /etc/apache2/sites-available/mercadolivre-ssl.conf
<VirtualHost *:443>
    ServerName seudominio.com
    DocumentRoot /var/www/eskill.com.br/public
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/seudominio.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/seudominio.com/privkey.pem
    
    # Security Headers
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # PHP Configuration
    <Directory /var/www/eskill.com.br/public>
        AllowOverride All
        Require all granted
        
        # Hide sensitive files
        <Files ".env">
            Require all denied
        </Files>
        
        <Files "*.log">
            Require all denied
        </Files>
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/mercadolivre_error.log
    CustomLog ${APACHE_LOG_DIR}/mercadolivre_access.log combined
</VirtualHost>

# Redirect HTTP to HTTPS
<VirtualHost *:80>
    ServerName seudominio.com
    Redirect permanent / https://seudominio.com/
</VirtualHost>
```

### Ativar configuração
```bash
# Ativar módulos necessários
sudo a2enmod ssl
sudo a2enmod headers
sudo a2enmod rewrite

# Ativar site SSL
sudo a2ensite mercadolivre-ssl.conf

# Desativar site HTTP padrão
sudo a2dissite 000-default.conf

# Reiniciar Apache
sudo systemctl restart apache2
```

## 3. Configuração Nginx

### Arquivo de configuração Nginx
```nginx
# /etc/nginx/sites-available/mercadolivre
server {
    listen 80;
    server_name seudominio.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name seudominio.com;
    
    root /var/www/eskill.com.br/public;
    index index.php index.html;
    
    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/seudominio.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/seudominio.com/privkey.pem;
    
    # Security
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;
    
    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options DENY always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Hide sensitive files
    location ~ /\.(env|git) {
        deny all;
        return 404;
    }
    
    location ~ \.log$ {
        deny all;
        return 404;
    }
    
    # PHP Processing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Assets caching
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### Ativar configuração
```bash
# Ativar site
sudo ln -s /etc/nginx/sites-available/mercadolivre /etc/nginx/sites-enabled/

# Testar configuração
sudo nginx -t

# Reiniciar Nginx
sudo systemctl restart nginx
```

## 4. Configuração PHP para Produção

### php.ini ajustes
```ini
; /etc/php/8.1/apache2/php.ini (ou fpm/php.ini para Nginx)

; Segurança
expose_php = Off
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

; Performance
memory_limit = 256M
max_execution_time = 60
max_input_time = 60
upload_max_filesize = 10M
post_max_size = 10M

; Sessão
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
```

## 5. Firewall Básico

### UFW (Ubuntu)
```bash
# Ativar UFW
sudo ufw enable

# Permitir SSH, HTTP e HTTPS
sudo ufw allow ssh
sudo ufw allow 80
sudo ufw allow 443

# Verificar status
sudo ufw status
```

## 6. Teste de SSL

### Ferramentas de teste
```bash
# Testar SSL localmente
openssl s_client -connect seudominio.com:443 -servername seudominio.com

# Verificar certificado
curl -I https://seudominio.com

# Teste online
# https://www.ssllabs.com/ssltest/
```

## 7. Headers de Segurança

### .htaccess (Apache)
```apache
# public/.htaccess
<IfModule mod_headers.c>
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Force HTTPS
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>
```

## 8. Checklist Final

- [ ] Certificado SSL instalado
- [ ] Redirect HTTP → HTTPS funcionando
- [ ] Headers de segurança configurados
- [ ] Arquivos sensíveis protegidos (.env, .log)
- [ ] PHP configurado para produção
- [ ] Firewall básico ativo
- [ ] Teste SSL com nota A/A+
- [ ] ML_REDIRECT_URI atualizado para HTTPS
- [ ] APP_URL no .env usando HTTPS

## 9. Comandos de Verificação

```bash
# Verificar certificado expira em
echo | openssl s_client -connect seudominio.com:443 2>/dev/null | openssl x509 -dates -noout

# Verificar redirects
curl -I http://seudominio.com

# Verificar headers de segurança
curl -I https://seudominio.com

# Verificar se .env está acessível (deve dar 404/403)
curl -I https://seudominio.com/.env
```