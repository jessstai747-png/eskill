# 🔐 Sistema de Autenticação de Usuários

## ✅ Funcionalidades Implementadas

### 1. UserService
Serviço completo para gestão de usuários:
- ✅ Registro de novos usuários
- ✅ Login com validação de credenciais
- ✅ Verificação de autenticação
- ✅ Logout
- ✅ Atualização de perfil
- ✅ Alteração de senha
- ✅ Hash seguro de senhas (bcrypt)

### 2. Views de Autenticação
- ✅ Página de login (`/auth/login`)
- ✅ Página de registro (`/auth/register`)
- ✅ Design responsivo e moderno
- ✅ Validação de formulários
- ✅ Mensagens de erro/sucesso

### 3. Middleware de Autenticação
- ✅ `AuthMiddleware` para proteger rotas
- ✅ Verificação automática de sessão
- ✅ Redirecionamento para login quando não autenticado
- ✅ Preservação de URL de destino após login

### 4. AuthController Atualizado
- ✅ Métodos de login/registro/logout
- ✅ Integração com UserService
- ✅ Proteção CSRF
- ✅ Gestão de sessões
- ✅ Integração com autenticação OAuth2 do Mercado Livre

### 5. Rotas Configuradas
- ✅ `GET /auth/login` - Exibe página de login
- ✅ `POST /auth/login` - Processa login
- ✅ `GET /auth/register` - Exibe página de registro
- ✅ `POST /auth/register` - Processa registro
- ✅ `GET /auth/logout` - Faz logout

### 6. Dashboard Atualizado
- ✅ Exibe informações do usuário logado
- ✅ Menu dropdown com opções do usuário
- ✅ Botão de logout
- ✅ Links de login/registro quando não autenticado

## 🔒 Segurança

### Implementado
- ✅ Hash de senhas com bcrypt (cost 12)
- ✅ Proteção CSRF em formulários
- ✅ Validação de e-mail
- ✅ Sanitização de inputs
- ✅ Verificação de senha mínima (6 caracteres)
- ✅ Verificação de e-mail único

### Sessões
- ✅ Gestão de sessões PHP
- ✅ Armazenamento seguro de dados do usuário
- ✅ Logout seguro (destruição de sessão)

## 📋 Como Usar

### Registro de Novo Usuário
1. Acesse `/auth/register`
2. Preencha nome, e-mail e senha
3. Confirme a senha
4. Clique em "Cadastrar"
5. Faça login com suas credenciais

### Login
1. Acesse `/auth/login`
2. Informe e-mail e senha
3. Opcionalmente marque "Lembrar-me"
4. Clique em "Entrar"

### Logout
1. Clique no nome do usuário no menu
2. Selecione "Sair"

## 🔄 Integração com Mercado Livre

O sistema de autenticação está totalmente integrado com a vinculação de contas do Mercado Livre:
- Usuários devem estar logados para vincular contas ML
- Cada conta ML é vinculada ao usuário logado
- Dados são isolados por usuário

## 📊 Banco de Dados

### Tabela `users`
- `id` - ID único do usuário
- `name` - Nome completo
- `email` - E-mail (único)
- `password` - Hash da senha
- `status` - Status da conta (active/inactive/suspended)
- `last_login` - Último login
- `created_at` - Data de criação
- `updated_at` - Data de atualização

### Migration
Execute a migration `010_add_user_status_and_last_login.sql` para adicionar campos opcionais:
```sql
ALTER TABLE users 
ADD COLUMN status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
ADD COLUMN last_login TIMESTAMP NULL;
```

## 🎯 Próximas Melhorias Sugeridas

- [ ] Recuperação de senha por e-mail
- [ ] Verificação de e-mail
- [ ] Autenticação de dois fatores (2FA)
- [ ] Histórico de logins
- [ ] Bloqueio de conta após tentativas falhas
- [ ] Cookie de "lembrar-me" persistente
