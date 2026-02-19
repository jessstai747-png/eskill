# 📘 API de Gerenciamento de Contas do Mercado Livre

Documentação completa dos endpoints para adicionar, listar, desconectar e excluir contas do Mercado Livre.

---

## 🔐 Autenticação

Todos os endpoints requerem autenticação via sessão ou API token.

---

## 📍 Endpoints Disponíveis

### 1. Adicionar Nova Conta (OAuth2)

**Iniciar processo de vinculação:**

```http
GET /auth/authorize
```

**Descrição:**
- Redireciona o usuário para o Mercado Livre para autorizar o aplicativo
- Após autorização, o usuário é redirecionado para `/auth/callback`
- A conta é automaticamente salva no sistema

**Requisitos:**
- Usuário deve estar autenticado
- Credenciais OAuth configuradas no `.env`

**Exemplo:**
```javascript
// Redirecionar usuário para vincular conta
window.location.href = '/auth/authorize';
```

**Resposta (após callback):**
- Redireciona para `/dashboard` com mensagem de sucesso
- Conta salva em `ml_accounts` com status `active`

---

### 2. Listar Contas do Usuário

```http
GET /api/auth/accounts
```

**Descrição:**
Lista todas as contas do Mercado Livre vinculadas ao usuário autenticado.

**Resposta de Sucesso (200):**
```json
[
  {
    "id": 1,
    "ml_user_id": "806272575",
    "nickname": "PANTERAMOTOPEÇAS",
    "email": "contato@loja.com",
    "status": "active",
    "token_expires_at": "2026-02-15 10:30:00",
    "created_at": "2025-01-01 08:00:00"
  },
  {
    "id": 2,
    "ml_user_id": "1919779391",
    "nickname": "DIVINOESPELHOS",
    "email": "vendas@loja.com",
    "status": "active",
    "token_expires_at": "2026-02-20 14:45:00",
    "created_at": "2025-01-05 10:30:00"
  }
]
```

**Resposta de Erro (401):**
```json
{
  "error": "Não autenticado"
}
```

**Exemplo JavaScript:**
```javascript
async function loadAccounts() {
  try {
    const response = await fetch('/api/auth/accounts');
    const accounts = await response.json();
    
    accounts.forEach(account => {
      console.log(`${account.nickname} - ${account.status}`);
    });
  } catch (error) {
    console.error('Erro ao carregar contas:', error);
  }
}
```

---

### 3. Desconectar Conta (Soft Delete)

```http
POST /auth/disconnect/{accountId}
```

**Descrição:**
Desconecta uma conta do Mercado Livre mantendo o histórico. Os tokens são removidos e o status é alterado para `inactive`.

**Parâmetros:**
- `accountId` (path): ID da conta a ser desconectada

**Validações:**
- ✅ Usuário autenticado
- ✅ Conta pertence ao usuário
- ✅ Conta existe

**Resposta de Sucesso (200):**
```json
{
  "success": true,
  "message": "Conta desconectada com sucesso"
}
```

**Resposta de Erro (404):**
```json
{
  "success": false,
  "error": "Conta não encontrada ou não autorizada"
}
```

**Resposta de Erro (401):**
```json
{
  "success": false,
  "error": "Não autenticado"
}
```

**Exemplo JavaScript:**
```javascript
async function disconnectAccount(accountId) {
  if (!confirm('Desconectar esta conta?')) return;
  
  try {
    const response = await fetch(`/auth/disconnect/${accountId}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      }
    });
    
    const result = await response.json();
    
    if (result.success) {
      alert('Conta desconectada com sucesso!');
      loadAccounts(); // Recarregar lista
    } else {
      alert('Erro: ' + result.error);
    }
  } catch (error) {
    console.error('Erro ao desconectar conta:', error);
  }
}
```

**O que acontece:**
1. Token de acesso e refresh são removidos
2. Status da conta muda para `inactive`
3. Histórico é mantido (pedidos, itens, etc.)
4. Audit log registra a ação
5. Se era a conta ativa na sessão, ela é removida

---

### 4. Excluir Conta Permanentemente (Hard Delete)

```http
DELETE /auth/account/{accountId}
```

**⚠️ ATENÇÃO: Esta ação é irreversível!**

**Descrição:**
Exclui permanentemente uma conta do banco de dados, removendo todos os dados relacionados.

**Parâmetros:**
- `accountId` (path): ID da conta a ser excluída

**Validações:**
- ✅ Usuário autenticado
- ✅ Conta pertence ao usuário
- ✅ Conta existe

**Resposta de Sucesso (200):**
```json
{
  "success": true,
  "message": "Conta excluída permanentemente com sucesso"
}
```

**Resposta de Erro (404):**
```json
{
  "success": false,
  "error": "Conta não encontrada ou não autorizada"
}
```

**Exemplo JavaScript:**
```javascript
async function deleteAccount(accountId) {
  if (!confirm('⚠️ ATENÇÃO: Esta ação é IRREVERSÍVEL! Deseja realmente excluir esta conta permanentemente?')) {
    return;
  }
  
  try {
    const response = await fetch(`/auth/account/${accountId}`, {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json'
      }
    });
    
    const result = await response.json();
    
    if (result.success) {
      alert('Conta excluída permanentemente!');
      loadAccounts(); // Recarregar lista
    } else {
      alert('Erro: ' + result.error);
    }
  } catch (error) {
    console.error('Erro ao excluir conta:', error);
  }
}
```

**O que acontece:**
1. Transação iniciada no banco de dados
2. Registro da conta é deletado permanentemente
3. Audit log registra a exclusão
4. Transação commitada
5. Se era a conta ativa na sessão, ela é removida

---

## 🔒 Segurança

### Validações Implementadas

1. **Autenticação Obrigatória:** Todos os endpoints verificam se o usuário está autenticado
2. **Validação de Propriedade:** Usuário só pode gerenciar suas próprias contas
3. **Audit Log:** Todas as ações são registradas para auditoria
4. **Security Log:** Ações críticas são logadas no sistema de segurança
5. **Transações:** Exclusões usam transações para garantir integridade

### Logs Registrados

**Desconectar:**
```php
[
  'action' => 'account_disconnected',
  'user_id' => 1,
  'account_id' => 2,
  'ml_user_id' => '806272575',
  'nickname' => 'PANTERAMOTOPEÇAS',
  'ip' => '192.168.1.100'
]
```

**Excluir:**
```php
[
  'action' => 'account_deleted',
  'user_id' => 1,
  'account_id' => 2,
  'ml_user_id' => '806272575',
  'nickname' => 'PANTERAMOTOPEÇAS',
  'ip' => '192.168.1.100'
]
```

---

## 📊 Fluxo Completo

### Adicionar Conta
```
1. Usuário clica em "Vincular Conta"
2. GET /auth/authorize
3. Redirecionamento para ML
4. Usuário autoriza
5. Callback: GET /auth/callback?code=xxx
6. Sistema troca código por tokens
7. Conta salva em ml_accounts
8. Redirecionamento para /dashboard
```

### Desconectar Conta
```
1. Usuário clica em "Desconectar"
2. Confirmação no frontend
3. POST /auth/disconnect/{id}
4. Validação de propriedade
5. UPDATE ml_accounts SET status='inactive', tokens=NULL
6. Audit log registrado
7. Resposta JSON
```

### Excluir Conta
```
1. Usuário clica em "Excluir Permanentemente"
2. Dupla confirmação no frontend
3. DELETE /auth/account/{id}
4. BEGIN TRANSACTION
5. Validação de propriedade
6. DELETE FROM ml_accounts
7. Audit log registrado
8. COMMIT
9. Resposta JSON
```

---

## 🧪 Testes

Execute o script de teste:

```bash
php tests/test_account_management.php
```

**Saída esperada:**
- ✓ Estrutura da tabela verificada
- ✓ Contagem de contas ativas/inativas
- ✓ Listagem por usuário
- ✓ Tokens expirando
- ✓ Rotas implementadas

---

## 📝 Notas Importantes

1. **Soft Delete vs Hard Delete:**
   - Use `disconnect` para desconectar temporariamente (recomendado)
   - Use `deleteAccount` apenas quando realmente necessário excluir

2. **Conta Ativa na Sessão:**
   - Ao desconectar/excluir a conta ativa, a sessão é atualizada
   - Próxima requisição usará outra conta disponível ou None

3. **Multi-Conta:**
   - Sistema suporta múltiplas contas por usuário
   - Use `/api/dashboard/switch-account` para trocar conta ativa

4. **Renovação de Tokens:**
   - Tokens expiram periodicamente
   - Sistema detecta expirações próximas (7 dias)
   - Use notificações para alertar usuário

---

## 🎯 Exemplos Práticos

### Componente React de Gerenciamento

```jsx
function AccountManager() {
  const [accounts, setAccounts] = useState([]);
  
  useEffect(() => {
    loadAccounts();
  }, []);
  
  async function loadAccounts() {
    const response = await fetch('/api/auth/accounts');
    const data = await response.json();
    setAccounts(data);
  }
  
  async function handleDisconnect(id) {
    if (!confirm('Desconectar esta conta?')) return;
    
    await fetch(`/auth/disconnect/${id}`, { method: 'POST' });
    loadAccounts();
  }
  
  return (
    <div>
      {accounts.map(account => (
        <div key={account.id}>
          <h3>{account.nickname}</h3>
          <span>{account.status}</span>
          <button onClick={() => handleDisconnect(account.id)}>
            Desconectar
          </button>
        </div>
      ))}
      <a href="/auth/authorize">+ Adicionar Conta</a>
    </div>
  );
}
```

---

## 🔗 Endpoints Relacionados

- `GET /api/dashboard/accounts` - Lista contas com métricas
- `POST /api/dashboard/switch-account` - Trocar conta ativa
- `GET /api/multi-account/dashboard` - Dashboard multi-conta
- `GET /auth/mobile/status` - Status de conexão (mobile)

---

## 📞 Suporte

Para dúvidas ou problemas:
1. Verificar logs em `storage/logs/`
2. Consultar audit log em `system_logs`
3. Revisar documentação do Mercado Livre: https://developers.mercadolivre.com.br
