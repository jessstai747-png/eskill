# 🧪 Teste o Sistema Agora

## Passos para Testar

### 1. Verificação Rápida
Acesse primeiro:
```
http://localhost/eskill/public/check.php
```

Este arquivo verifica rapidamente se tudo está OK.

---

### 2. Diagnóstico Completo
Para diagnóstico detalhado:
```
http://localhost/eskill/public/diagnostic.php
```

Este arquivo verifica:
- ✅ Versão PHP
- ✅ Extensões necessárias
- ✅ Arquivos essenciais
- ✅ Configuração .env
- ✅ Conexão com banco
- ✅ Permissões
- ✅ Rotas

---

### 3. Teste Rápido
```
http://localhost/eskill/public/quick_test.php
```

---

### 4. Acessar o Sistema

#### Se tudo estiver OK:

**Login:**
```
http://localhost/eskill/public/auth/login
```

**Registro:**
```
http://localhost/eskill/public/auth/register
```

**Dashboard (requer login):**
```
http://localhost/eskill/public/dashboard
```

---

## Problemas Comuns

### Se aparecer "Arquivo .env não encontrado"
```bash
cd C:\xampp\htdocs\eskill
copy .env.example .env
```
Depois edite o `.env` com suas configurações.

### Se aparecer erro de banco de dados
1. Verifique se MySQL está rodando no XAMPP
2. Crie o banco `mercadolivre_db` no phpMyAdmin
3. Execute as migrations

### Se aparecer erro 404
1. Verifique se `mod_rewrite` está habilitado no Apache
2. Verifique se existe `public/.htaccess`

### Se aparecer "Não autenticado"
- Isso é normal! Acesse primeiro `/auth/login` ou `/auth/register`
- Crie uma conta ou faça login
- Depois acesse o dashboard

---

## Checklist Rápido

Antes de testar, verifique:

- [ ] XAMPP está rodando (Apache e MySQL)
- [ ] Arquivo `.env` existe e está configurado
- [ ] Banco de dados `mercadolivre_db` existe
- [ ] Composer instalado (`composer install` executado)

---

## Próximos Passos Após Teste

1. ✅ Se tudo estiver OK → Acesse `/auth/register` e crie uma conta
2. ✅ Faça login em `/auth/login`
3. ✅ Acesse o dashboard
4. ✅ Vincule uma conta do Mercado Livre

---

## Arquivos de Teste Criados

1. **check.php** - Verificação rápida
2. **diagnostic.php** - Diagnóstico completo
3. **quick_test.php** - Teste rápido
4. **test.php** - Teste original

Use qualquer um deles para verificar o sistema!
