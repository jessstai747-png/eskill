# 📦 Guia de Instalação - Mercado Livre Manager

## Pré-requisitos

- ✅ PHP 8.0 ou superior
- ✅ MySQL 8.0 ou superior  
- ✅ Composer instalado
- ✅ XAMPP/WAMP rodando
- ✅ Conta no Mercado Livre Developers

## Passo a Passo

### 1️⃣ Instalar Dependências

Abra o terminal na pasta do projeto:

```bash
cd C:\xampp\htdocs\eskill
composer install
```

### 2️⃣ Configurar Banco de Dados

#### Opção A: Via phpMyAdmin (Recomendado)

1. Acesse `http://localhost/phpmyadmin`
2. Clique em "Novo" para criar um banco de dados
3. Nome: `mercadolivre_db`
4. Collation: `utf8mb4_unicode_ci`
5. Clique em "Criar"
6. Selecione o banco `mercadolivre_db`
7. Vá na aba "SQL"
8. Copie e cole o conteúdo do arquivo `database/migrations/000_install_all.sql`
9. Clique em "Executar"

#### Opção B: Via Linha de Comando

```bash
mysql -u root -p
```

```sql
CREATE DATABASE mercadolivre_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mercadolivre_db;
SOURCE C:/xampp/htdocs/eskill/database/migrations/000_install_all.sql;
EXIT;
```

### 3️⃣ Configurar Variáveis de Ambiente

1. Copie o arquivo `.env.example` para `.env`:

```bash
copy .env.example .env
```

2. Edite o arquivo `.env` com suas configurações:

```env
# Banco de Dados
DB_HOST=localhost
DB_PORT=3306
DB_NAME=mercadolivre_db
DB_USER=root
DB_PASS=          # Deixe vazio se não tiver senha

# Mercado Livre (obter em developers.mercadolivre.com.br)
ML_APP_ID=1234567890123456
ML_CLIENT_SECRET=abc123def456...
ML_REDIRECT_URI=http://localhost/eskill/public/auth/callback

# Aplicação
APP_URL=http://localhost/eskill/public
APP_ENV=development
APP_DEBUG=true
```

3. Gere uma chave de segurança:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Cole o resultado no `.env` na variável `APP_KEY`.

### 4️⃣ Criar Aplicação no Mercado Livre

1. Acesse [developers.mercadolivre.com.br](https://developers.mercadolivre.com.br)
2. Faça login com sua conta ML
3. Clique em "Criar aplicação"
4. Preencha os dados:
   - Nome: Mercado Livre Manager
   - URL de redirecionamento: `http://localhost/eskill/public/auth/callback`
5. Copie o **App ID** e **Secret Key** para o arquivo `.env`

### 5️⃣ Configurar Apache (se necessário)

Se estiver usando Apache, certifique-se de que o módulo `mod_rewrite` está habilitado:

```apache
# No arquivo httpd.conf do Apache, descomente:
LoadModule rewrite_module modules/mod_rewrite.so
```

O arquivo `.htaccess` já está configurado em `public/.htaccess`.

### 6️⃣ Acessar o Sistema

Abra no navegador:
```
http://localhost/eskill/public/dashboard
```

## ✅ Verificação

Se tudo estiver correto, você verá o dashboard do Mercado Livre Manager.

## 🐛 Problemas Comuns

### Erro: "Arquivo .env não encontrado"
- Certifique-se de que o arquivo `.env` existe na raiz do projeto
- Verifique se você copiou o `.env.example` corretamente

### Erro: "Controller não encontrado"
- Execute `composer dump-autoload` para regenerar o autoloader
- Verifique se todos os arquivos estão no lugar correto

### Erro: "Rota não encontrada"
- Verifique se o módulo `mod_rewrite` está habilitado no Apache
- Verifique se o arquivo `.htaccess` está presente em `public/.htaccess`
- Verifique se o caminho base está correto em `public/index.php` (linha 171)

### Erro de conexão com banco de dados
- Verifique as credenciais no arquivo `.env`
- Certifique-se de que o MySQL está rodando
- Verifique se o banco de dados foi criado corretamente

## 📚 Próximos Passos

Após a instalação:
1. Vincule sua conta do Mercado Livre através do dashboard
2. Configure os alertas e notificações
3. Explore as funcionalidades de análise e relatórios
