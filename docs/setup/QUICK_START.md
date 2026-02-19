# 🚀 Guia Rápido de Início

## ✅ Sistema Pronto para Acesso Web

O sistema está configurado e pronto para uso. Siga os passos abaixo:

### 1. Verificar Pré-requisitos

Certifique-se de que:
- ✅ XAMPP/WAMP está rodando
- ✅ MySQL está ativo
- ✅ Apache está ativo
- ✅ PHP 8.0+ instalado
- ✅ Composer instalado

### 2. Configuração Inicial

1. **Copiar arquivo de configuração:**
   ```bash
   copy .env.example .env
   ```

2. **Configurar banco de dados no `.env`:**
   ```env
   DB_HOST=localhost
   DB_NAME=mercadolivre_db
   DB_USER=root
   DB_PASS=
   ```

3. **Criar banco de dados:**
   - Acesse phpMyAdmin: `http://localhost/phpmyadmin`
   - Crie o banco `mercadolivre_db`
   - Execute o script: `database/migrations/000_install_all.sql`

4. **Configurar Mercado Livre:**
   ```env
   ML_APP_ID=seu_app_id
   ML_CLIENT_SECRET=seu_secret
   ML_REDIRECT_URI=http://localhost/eskill/public/auth/callback
   ```

### 3. Acessar o Sistema

**🔍 Primeiro: Verificar Sistema**
```
http://localhost/eskill/public/check.php
```

**🔐 Criar Conta (Primeira Vez)**
```
http://localhost/eskill/public/auth/register
```

**🔑 Login**
```
http://localhost/eskill/public/auth/login
```

**🏠 Dashboard Principal**
```
http://localhost/eskill/public/dashboard
```

**📊 Outras Páginas:**
- Categorias: `http://localhost/eskill/public/dashboard/categories`
- Análise: `http://localhost/eskill/public/dashboard/analysis`
- Pedidos: `http://localhost/eskill/public/dashboard/orders`
- Perfil: `http://localhost/eskill/public/dashboard/profile`
- Configurações: `http://localhost/eskill/public/dashboard/settings`
- Ajuda: `http://localhost/eskill/public/dashboard/help`

**🧪 Páginas de Teste:**
- `http://localhost/eskill/public/check.php` - Verificação rápida
- `http://localhost/eskill/public/diagnostic.php` - Diagnóstico completo
- `http://localhost/eskill/public/test.php` - Teste original

### 4. Rotas Principais

- **Dashboard:** `/eskill/public/dashboard`
- **Categorias:** `/eskill/public/dashboard/categories`
- **Análise:** `/eskill/public/dashboard/analysis`
- **Pedidos:** `/eskill/public/dashboard/orders`
- **API:** `/eskill/public/api/*`

### 5. Verificações

Execute o arquivo de teste para verificar se tudo está funcionando:
```
http://localhost/eskill/public/test.php
```

### 6. Problemas Comuns

**Erro 404:**
- Verifique se o módulo `mod_rewrite` está habilitado no Apache
- Verifique se o arquivo `.htaccess` existe em `public/.htaccess`

**Erro de conexão com banco:**
- Verifique as credenciais no `.env`
- Certifique-se de que o MySQL está rodando
- Verifique se o banco foi criado

**Erro de autoload:**
- Execute: `composer dump-autoload`

### 7. Próximos Passos

1. Vincular conta do Mercado Livre
2. Explorar funcionalidades
3. Configurar alertas e notificações
4. Gerar relatórios

---

**Sistema pronto para uso em produção!** 🎉
