# 🚀 Guia de Instalação Completo - Sistema SEO Mercado Livre

## ✅ Checklist de Pré-requisitos

Antes de começar, certifique-se de ter:

- [ ] PHP 8.0 ou superior instalado
- [ ] Composer instalado
- [ ] MySQL ou MariaDB rodando
- [ ] Conta no Mercado Livre Developers
- [ ] Conta na OpenAI (para IA)
- [ ] Acesso SSH ao servidor (para produção)

## 📦 Passo 1: Clonar e Instalar Dependências

```bash
# Clone o repositório
git clone <repository-url>
cd eskill.com.br

# Instale as dependências PHP
composer install

# Dê permissões aos diretórios
chmod +x setup-seo-database.sh
chmod -R 775 storage/
chmod -R 775 logs/
```

## 🔐 Passo 2: Configurar Variáveis de Ambiente

```bash
# Copie o arquivo de exemplo
cp .env.example .env

# Edite o arquivo .env
nano .env
```

### Configurações Obrigatórias:

```env
# Banco de Dados
DB_HOST=localhost
DB_PORT=3306
DB_NAME=seo_optimizer_db
DB_USER=seu_usuario
DB_PASS=sua_senha_segura

# Mercado Livre API
ML_APP_ID=seu_app_id_aqui
ML_CLIENT_SECRET=seu_client_secret_aqui
ML_REDIRECT_URI=https://seu-dominio.com/dashboard

# OpenAI (para IA)
AI_API_KEY=sk-sua-chave-openai-aqui
AI_PROVIDER=openai

# Segurança
APP_KEY=gere_uma_chave_aleatoria_de_32_caracteres_minimo
APP_ENV=production
APP_DEBUG=false
```

## 🗄️ Passo 3: Criar Banco de Dados

```bash
# Conecte ao MySQL
mysql -u root -p

# Crie o banco de dados
CREATE DATABASE seo_optimizer_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Crie um usuário dedicado
CREATE USER 'seo_user'@'localhost' IDENTIFIED BY 'senha_segura_aqui';
GRANT ALL PRIVILEGES ON seo_optimizer_db.* TO 'seo_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 📊 Passo 4: Executar Migrações

```bash
# Execute o script de setup
./setup-seo-database.sh

# OU execute manualmente cada migração:
mysql -u seo_user -p seo_optimizer_db < database/migrations/2026_01_22_create_seo_synonyms_tables.sql
mysql -u seo_user -p seo_optimizer_db < database/migrations/2026_01_22_create_seo_monitoring_schedule.sql
mysql -u seo_user -p seo_optimizer_db < database/migrations/2026_01_23_create_seo_strategies_tables.sql
mysql -u seo_user -p seo_optimizer_db < database/migrations/2026_01_24_create_seo_hidden_attributes_table.sql
mysql -u seo_user -p seo_optimizer_db < database/migrations/2026_01_01_000002_create_seo_optimizations_table.sql
```

## 🔑 Passo 5: Obter Credenciais do Mercado Livre

### 5.1 Criar Aplicativo

1. Acesse: https://developers.mercadolivre.com.br/
2. Faça login com sua conta do Mercado Livre
3. Vá em "Meus Aplicativos" → "Criar novo aplicativo"
4. Preencha:
   - Nome: "SEO Optimizer"
   - Descrição: "Sistema de otimização SEO"
   - Redirect URI: `https://seu-dominio.com/dashboard`
   - Tópicos: Selecione "items" e "categories"

### 5.2 Obter Credenciais

Após criar o app, você receberá:
- **App ID** (Client ID)
- **Client Secret**

Adicione no `.env`:
```env
ML_APP_ID=seu_app_id
ML_CLIENT_SECRET=seu_client_secret
```

### 5.3 Gerar Token de Acesso

```bash
# Execute o script de autenticação
php bin/mcp-ml-auth.php

# Siga as instruções para autorizar o app
# O token será salvo automaticamente
```

## 🤖 Passo 6: Configurar OpenAI

1. Acesse: https://platform.openai.com/
2. Crie uma conta ou faça login
3. Vá em "API Keys" → "Create new secret key"
4. Copie a chave e adicione no `.env`:

```env
AI_API_KEY=sk-sua-chave-aqui
AI_PROVIDER=openai
AI_DEFAULT_MODEL=gpt-4o-mini
```

## 🧪 Passo 7: Testar Instalação

```bash
# Teste a conexão com o banco
php scripts/test_db_connection.php

# Teste a API do Mercado Livre
php scripts/test_ml_auth_flow.php

# Teste a integração com OpenAI
php scripts/test_openai.php

# Execute os testes unitários
php vendor/bin/phpunit
```

## 🌐 Passo 8: Configurar Servidor Web

### Apache (.htaccess já configurado)

```apache
<VirtualHost *:80>
    ServerName seu-dominio.com
    DocumentRoot /var/www/eskill.com.br/public
    
    <Directory /var/www/eskill.com.br/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/seo-error.log
    CustomLog ${APACHE_LOG_DIR}/seo-access.log combined
</VirtualHost>
```

### Nginx

```nginx
server {
    listen 80;
    server_name seu-dominio.com;
    root /var/www/eskill.com.br/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## ⏰ Passo 9: Configurar Cron Jobs (Opcional)

```bash
# Edite o crontab
crontab -e

# Adicione as tarefas agendadas
# Monitoramento SEO (a cada 6 horas)
0 */6 * * * cd /var/www/eskill.com.br && php bin/seo-performance-worker.php >> storage/logs/cron-seo.log 2>&1

# Atualização de tokens (diariamente)
0 2 * * * cd /var/www/eskill.com.br && php bin/auto-token-refresh-worker.php >> storage/logs/cron-tokens.log 2>&1
```

## 🎯 Passo 10: Primeiro Uso

### Via API:

```bash
# Otimizar um item
curl -X POST http://seu-dominio.com/api/seo/strategies/optimize/full/MLB123456789 \
  -H "Content-Type: application/json"

# Ver score SEO
curl http://seu-dominio.com/api/seo/strategies/score/MLB123456789
```

### Via PHP:

```php
<?php
require 'vendor/autoload.php';

use App\Services\SEO\SEOStrategiesEngine;

$engine = new SEOStrategiesEngine();
$result = $engine->optimizeFull('MLB123456789');

print_r($result);
```

## 🔍 Verificação Final

Execute este checklist:

```bash
# 1. Banco de dados conectando?
php -r "require 'vendor/autoload.php'; \$db = App\Database::getInstance(); echo 'DB OK';"

# 2. Tabelas criadas?
mysql -u seo_user -p -e "USE seo_optimizer_db; SHOW TABLES;"

# 3. API do ML funcionando?
php scripts/test_ml_auth_flow.php

# 4. OpenAI respondendo?
php scripts/test_openai.php

# 5. Testes passando?
php vendor/bin/phpunit --testdox
```

## 🆘 Problemas Comuns

### Erro: "Access denied for user"
```bash
# Verifique as credenciais do banco no .env
# Recrie o usuário do banco se necessário
```

### Erro: "Class not found"
```bash
# Regenere o autoload
composer dump-autoload
```

### Erro: "Permission denied"
```bash
# Ajuste permissões
chmod -R 775 storage/ logs/
chown -R www-data:www-data storage/ logs/
```

## 📞 Suporte

- **Documentação**: `/docs`
- **Issues**: GitHub Issues
- **Email**: seo-support@eskill.com.br

---

**Instalação concluída!** 🎉

Acesse: `http://seu-dominio.com/seo-dashboard.html`
