# 🔧 Guia de Troubleshooting

## Problemas Comuns e Soluções

### 1. Erro: "Arquivo .env não encontrado"

**Solução:**
```bash
cd C:\xampp\htdocs\eskill
copy .env.example .env
```

Depois edite o `.env` com suas configurações.

---

### 2. Erro: "Class not found" ou "Controller não encontrado"

**Solução:**
```bash
composer dump-autoload
```

Se ainda não funcionar:
```bash
composer install
```

---

### 3. Erro 404 - Página não encontrada

**Possíveis causas:**
- Módulo `mod_rewrite` não habilitado no Apache
- Arquivo `.htaccess` não existe ou está incorreto
- Caminho base incorreto

**Solução:**

1. **Habilitar mod_rewrite no Apache:**
   - Abra `C:\xampp\apache\conf\httpd.conf`
   - Procure por `#LoadModule rewrite_module modules/mod_rewrite.so`
   - Remova o `#` para descomentar
   - Reinicie o Apache

2. **Verificar .htaccess:**
   - Certifique-se de que existe `public/.htaccess`
   - Verifique se o conteúdo está correto

3. **Verificar caminho base:**
   - Abra `public/index.php`
   - Verifique a linha 26: `$basePath = '/eskill/public';`
   - Ajuste se necessário para seu ambiente

---

### 4. Erro de Conexão com Banco de Dados

**Solução:**

1. **Verificar MySQL está rodando:**
   - Abra o XAMPP Control Panel
   - Certifique-se de que MySQL está "Running"

2. **Verificar credenciais no .env:**
   ```env
   DB_HOST=localhost
   DB_NAME=mercadolivre_db
   DB_USER=root
   DB_PASS=
   ```

3. **Criar banco de dados:**
   - Acesse phpMyAdmin: `http://localhost/phpmyadmin`
   - Crie o banco `mercadolivre_db`
   - Execute as migrations

---

### 5. Erro: "Token CSRF inválido"

**Solução:**
- Limpe o cache do navegador
- Certifique-se de que as sessões estão funcionando
- Verifique se `APP_KEY` está configurado no `.env`

---

### 6. Erro: "Não autenticado" ao acessar dashboard

**Solução:**
- Acesse primeiro `/auth/login` ou `/auth/register`
- Faça login ou crie uma conta
- Depois acesse o dashboard

---

### 7. Erro: "Call to undefined function" ou extensão não encontrada

**Solução:**

Instale as extensões PHP necessárias:
- `pdo`
- `pdo_mysql`
- `curl`
- `json`
- `mbstring`
- `openssl`
- `session`

No XAMPP, edite `php.ini` e descomente as extensões necessárias.

---

### 8. Erro 500 - Internal Server Error

**Solução:**

1. **Verificar logs:**
   - Verifique `storage/logs/php_errors.log`
   - Verifique logs do Apache

2. **Habilitar display_errors temporariamente:**
   - No início do `public/index.php`, adicione:
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```

3. **Verificar permissões:**
   - Certifique-se de que `storage/` é gravável
   - Windows: geralmente não há problema
   - Linux: `chmod -R 775 storage`

---

### 9. Problemas com Rotas

**Solução:**

1. **Teste a rota diretamente:**
   ```
   http://localhost/eskill/public/auth/login
   ```

2. **Verifique se o Router está funcionando:**
   - Acesse `/diagnostic.php` para verificar

3. **Teste rotas de API:**
   ```
   http://localhost/eskill/public/api/categories
   ```

---

### 10. Erro ao Vincular Conta do Mercado Livre

**Solução:**

1. **Verificar configuração no .env:**
   ```env
   ML_APP_ID=seu_app_id
   ML_CLIENT_SECRET=seu_secret
   ML_REDIRECT_URI=http://localhost/eskill/public/auth/callback
   ```

2. **Verificar URL de callback no Mercado Livre:**
   - Deve ser exatamente: `http://localhost/eskill/public/auth/callback`

3. **Verificar se está logado:**
   - Você precisa estar autenticado no sistema para vincular contas ML

---

## Ferramentas de Diagnóstico

### Arquivo de Diagnóstico Completo
Acesse: `http://localhost/eskill/public/diagnostic.php`

Este arquivo verifica:
- ✅ Versão PHP
- ✅ Extensões necessárias
- ✅ Arquivos essenciais
- ✅ Configuração .env
- ✅ Conexão com banco
- ✅ Permissões
- ✅ Rotas

### Arquivo de Teste Simples
Acesse: `http://localhost/eskill/public/test.php`

---

## Checklist Rápido

Antes de reportar um problema, verifique:

- [ ] PHP 8.0+ instalado
- [ ] Composer instalado e dependências instaladas (`composer install`)
- [ ] Arquivo `.env` existe e está configurado
- [ ] MySQL está rodando
- [ ] Banco de dados `mercadolivre_db` existe
- [ ] Migrations executadas
- [ ] Módulo `mod_rewrite` habilitado (Apache)
- [ ] Arquivo `.htaccess` existe em `public/`
- [ ] Diretório `storage/` é gravável
- [ ] `APP_KEY` configurado no `.env`

---

## Logs

### Onde encontrar logs:

1. **Erros PHP:**
   - `storage/logs/php_errors.log`

2. **Logs da aplicação:**
   - `storage/logs/app.log` (se configurado)

3. **Logs do Apache:**
   - `C:\xampp\apache\logs\error.log`

---

## Suporte Adicional

Se o problema persistir:

1. Execute o diagnóstico: `/diagnostic.php`
2. Verifique os logs de erro
3. Capture mensagens de erro completas
4. Verifique a versão do PHP: `php -v`
5. Verifique se todas as dependências estão instaladas: `composer show`
