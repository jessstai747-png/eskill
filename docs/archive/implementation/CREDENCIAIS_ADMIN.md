# 🔐 Credenciais de Acesso - Mercado Livre Manager

## ✅ Usuário Administrador

### Credenciais de Login

```
📧 Email:    admin@eskill.com.br
🔑 Senha:    admin123
```

---

## 🌐 URLs de Acesso

### Login
```
http://localhost/eskill/public/auth/login
```
ou em produção:
```
https://eskill.com.br/auth/login
```

### Dashboard
```
http://localhost/eskill/public/dashboard
```
ou em produção:
```
https://eskill.com.br/dashboard
```

---

## ⚠️ IMPORTANTE - SEGURANÇA

### Primeira vez acessando:

1. **Faça login** com as credenciais acima
2. **Acesse o perfil**: `/dashboard/profile`
3. **Altere a senha imediatamente** para uma senha forte
4. **Ative a autenticação de dois fatores** se disponível

### Recomendações de Senha:

- ✅ Mínimo 12 caracteres
- ✅ Combine letras maiúsculas e minúsculas
- ✅ Inclua números e símbolos
- ✅ Não use informações pessoais
- ✅ Use um gerenciador de senhas

### Exemplo de senha forte:
```
M3rc@d0L1vre!2025#Adm1n
```

---

## 🗄️ Banco de Dados

### MySQL/MariaDB

```
Host:     localhost
Porta:    3306
Banco:    meli
Usuário:  root
Senha:    Tr1unf0@
```

⚠️ **Nota:** Estas credenciais estão configuradas no arquivo `.env`

---

## 🔑 API Mercado Livre

### Credenciais da Aplicação

```
App ID:         757032559637450
Client Secret:  Qq7AwTHymrP9m8L2CWAj0m2m1frhlL0m
Redirect URI:   http://localhost/eskill/public/auth/callback
```

### Portal de Desenvolvedor
```
https://developers.mercadolivre.com.br
```

---

## 📧 Configuração de Email (SMTP)

Configure no arquivo `.env`:

```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=seu-email@gmail.com
SMTP_PASSWORD=sua-senha-de-app
MAIL_FROM_ADDRESS=noreply@eskill.com.br
MAIL_FROM_NAME=Mercado Livre Manager
```

**Para Gmail:**
1. Acesse: https://myaccount.google.com/apppasswords
2. Gere uma senha de app
3. Use essa senha no `SMTP_PASSWORD`

---

## 🔐 Tokens de API

### Criar Token de API

1. Faça login no sistema
2. Acesse: `/dashboard/api-tokens`
3. Clique em "Criar Novo Token"
4. Defina nome e permissões (escopos)
5. Copie o token (exibido apenas uma vez!)

### Usar Token de API

```bash
curl -H "Authorization: Bearer SEU_TOKEN_AQUI" \
     https://eskill.com.br/api/v1/orders
```

---

## 📝 Checklist de Segurança

Após configurar o sistema:

- [ ] Alterar senha padrão do admin
- [ ] Configurar firewall (permitir apenas portas 80, 443, 22)
- [ ] Configurar SSL/HTTPS
- [ ] Habilitar autenticação de dois fatores
- [ ] Configurar backup automático
- [ ] Revisar permissões de arquivos
- [ ] Atualizar todas as dependências
- [ ] Configurar logs de auditoria
- [ ] Testar recuperação de senha
- [ ] Criar tokens de API específicos (não usar admin)

---

## 🚨 Em Caso de Problema

### Esqueci a senha

1. Acesse: `/auth/forgot-password`
2. Digite o email cadastrado
3. Verifique seu email
4. Clique no link de recuperação
5. Defina nova senha

### Resetar senha via terminal

```bash
cd /home/eskill/htdocs/eskill.com.br
php -r "
require_once 'vendor/autoload.php';
\$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
\$dotenv->load();
\$db = App\Database::getInstance();
\$newPassword = password_hash('novaSenha123', PASSWORD_BCRYPT);
\$stmt = \$db->prepare('UPDATE users SET password = ? WHERE email = ?');
\$stmt->execute([\$newPassword, 'admin@eskill.com.br']);
echo 'Senha resetada para: novaSenha123';
"
```

---

## 📞 Suporte

Para dúvidas ou problemas:

1. Consulte a documentação em `/docs`
2. Verifique os logs em `/storage/logs`
3. Execute diagnóstico: `php scripts/health_check.php`

---

**Última atualização:** {{ date() }}
**Sistema:** Mercado Livre Manager v1.2.0
