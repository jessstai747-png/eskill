# 🌐 URLs de Acesso - Mercado Livre Manager

## 📍 URLs Principais

### 🏠 Dashboard Principal
```
http://localhost/eskill/public/dashboard
```
**Requer:** Login

---

### 🔐 Autenticação

#### Login
```
http://localhost/eskill/public/auth/login
```
**Acesso:** Público (não requer login)

#### Registro (Criar Conta)
```
http://localhost/eskill/public/auth/register
```
**Acesso:** Público (não requer login)

#### Logout
```
http://localhost/eskill/public/auth/logout
```
**Requer:** Login

---

### 👤 Área do Usuário

#### Meu Perfil
```
http://localhost/eskill/public/dashboard/profile
```
**Requer:** Login
- Editar dados pessoais
- Alterar senha
- Ver informações da conta

#### Configurações
```
http://localhost/eskill/public/dashboard/settings
```
**Requer:** Login
- Preferências de notificações
- Configuração Telegram
- Sincronização automática

#### Ajuda
```
http://localhost/eskill/public/dashboard/help
```
**Requer:** Login
- Central de ajuda
- Guias e tutoriais
- Solução de problemas

---

### 📊 Páginas do Dashboard

#### Categorias
```
http://localhost/eskill/public/dashboard/categories
```
**Requer:** Login
- Navegador visual de categorias
- Árvore hierárquica
- Busca de categorias

#### Análise
```
http://localhost/eskill/public/dashboard/analysis
```
**Requer:** Login
- Análise de anúncios
- Gráficos e estatísticas
- Filtros avançados

#### Pedidos
```
http://localhost/eskill/public/dashboard/orders
```
**Requer:** Login
- Lista de pedidos
- Filtros por status/data
- Detalhes dos pedidos

---

### 🔗 Vinculação de Contas

#### Vincular Conta Mercado Livre
```
http://localhost/eskill/public/auth/authorize
```
**Requer:** Login
- Inicia processo OAuth2
- Redireciona para Mercado Livre

#### Callback OAuth2
```
http://localhost/eskill/public/auth/callback
```
**Acesso:** Automático (chamado pelo Mercado Livre)

---

### 🧪 Arquivos de Teste e Diagnóstico

#### Verificação Rápida
```
http://localhost/eskill/public/check.php
```
**Acesso:** Público
- Teste rápido do sistema
- Verificação básica

#### Diagnóstico Completo
```
http://localhost/eskill/public/diagnostic.php
```
**Acesso:** Público
- Diagnóstico detalhado
- Verificação completa

#### Teste Rápido
```
http://localhost/eskill/public/quick_test.php
```
**Acesso:** Público
- Teste alternativo

#### Teste Original
```
http://localhost/eskill/public/test.php
```
**Acesso:** Público
- Teste original do sistema

---

### 🔌 APIs REST

#### Base URL
```
http://localhost/eskill/public/api/
```

#### Exemplos de Endpoints

**Categorias:**
- `GET /api/categories` - Listar categorias
- `GET /api/categories/{id}` - Detalhes da categoria
- `GET /api/categories/{id}/brands` - Marcas

**Busca:**
- `GET /api/search` - Buscar anúncios
- `GET /api/search/analyze` - Análise completa

**Pedidos:**
- `GET /api/orders` - Listar pedidos
- `GET /api/orders/all` - Todos os pedidos
- `POST /api/orders/sync` - Sincronizar

**Dashboard:**
- `GET /api/dashboard/metrics` - Métricas do dashboard

**Usuário:**
- `GET /api/user/me` - Dados do usuário atual
- `POST /api/user/profile` - Atualizar perfil
- `POST /api/user/change-password` - Alterar senha

**Configurações:**
- `POST /api/settings/notifications` - Salvar notificações
- `POST /api/settings/telegram` - Salvar Telegram
- `POST /api/settings/sync` - Salvar sincronização

**Cache:**
- `POST /api/cache/clear` - Limpar cache

**Contas ML:**
- `GET /api/auth/accounts` - Listar contas vinculadas

---

## 🚀 Primeiro Acesso

### Passo a Passo

1. **Verificar Sistema:**
   ```
   http://localhost/eskill/public/check.php
   ```

2. **Criar Conta:**
   ```
   http://localhost/eskill/public/auth/register
   ```

3. **Fazer Login:**
   ```
   http://localhost/eskill/public/auth/login
   ```

4. **Acessar Dashboard:**
   ```
   http://localhost/eskill/public/dashboard
   ```

5. **Vincular Conta ML:**
   - No dashboard, clique em "Vincular Conta ML"
   - Ou acesse: `http://localhost/eskill/public/auth/authorize`

---

## 📝 Notas Importantes

### URLs Públicas (Não Requerem Login)
- `/auth/login`
- `/auth/register`
- `/auth/callback`
- `/check.php`
- `/diagnostic.php`
- `/quick_test.php`
- `/test.php`

### URLs Protegidas (Requerem Login)
- `/dashboard` e todas as subpáginas
- `/api/user/*`
- `/api/settings/*`
- `/api/auth/accounts`

### Se Não Estiver Logado
- Ao acessar uma URL protegida, você será redirecionado para `/auth/login`
- Após o login, será redirecionado de volta para a página original

---

## 🔧 Configuração

### Base Path
O sistema detecta automaticamente o caminho base. Se estiver em:
- `C:\xampp\htdocs\eskill\` → Use `/eskill/public/`
- Se estiver na raiz → Use apenas `/public/`

### Verificar Caminho Correto
Acesse `check.php` ou `diagnostic.php` para verificar o caminho correto do seu ambiente.

---

## ✅ Checklist de Acesso

- [ ] XAMPP/Apache está rodando
- [ ] MySQL está rodando
- [ ] Arquivo `.env` configurado
- [ ] Banco de dados criado
- [ ] Acessar `check.php` para verificar
- [ ] Criar conta em `/auth/register`
- [ ] Fazer login em `/auth/login`
- [ ] Acessar dashboard em `/dashboard`

---

**URL Principal Recomendada:**
```
http://localhost/eskill/public/dashboard
```

**Primeiro Passo:**
```
http://localhost/eskill/public/check.php
```
