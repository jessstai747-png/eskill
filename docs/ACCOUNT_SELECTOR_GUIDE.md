# 🎯 Sistema de Seleção de Conta por Módulo

## Visão Geral

Sistema que **força a seleção explícita de conta** no início de cada módulo, evitando confusão entre múltiplas contas do Mercado Livre.

---

## ✨ Funcionalidades

### 1. **Banner Fixo de Conta Ativa**
- Mostra qual conta está ativa no topo de cada módulo
- Sticky position (acompanha scroll)
- Opção de trocar conta rapidamente
- Pode ser ocultado (preferência salva)

### 2. **Modal de Seleção Obrigatória**
- Abre automaticamente se nenhuma conta estiver selecionada
- Não pode ser fechado até selecionar (modal backdrop="static")
- Lista visual de todas as contas
- Indicador da conta atual

### 3. **Indicadores Visuais Claros**
- Avatar colorido de cada conta
- Nickname e ID visíveis
- Checkmark na conta ativa
- Estados hover e seleção

### 4. **Persistência de Contexto**
- Conta selecionada salva na sessão
- Preferências de UI no localStorage
- Recarrega página ao trocar conta

---

## 🚀 Como Usar

### Incluir no Início do Módulo

```php
<?php
// No início do seu arquivo de módulo (ex: orders.php, items.php, etc)

$requireAccountSelection = true;  // Força seleção se não houver conta
$showAccountBanner = true;        // Mostra banner fixo no topo
$moduleTitle = 'Gestão de Pedidos'; // Nome do módulo

include __DIR__ . '/../components/account-selector.php';
?>

<!-- Seu conteúdo do módulo aqui -->
```

### Opções de Configuração

| Variável | Tipo | Padrão | Descrição |
|----------|------|--------|-----------|
| `$requireAccountSelection` | bool | `true` | Se `true`, abre modal obrigatório quando não há conta |
| `$showAccountBanner` | bool | `true` | Exibe banner fixo no topo |
| `$moduleTitle` | string | `'Este Módulo'` | Nome exibido no modal |

---

## 📱 Interface do Usuário

### Banner Fixo (Top)
```
┌────────────────────────────────────────────────────────┐
│  [PA]  Conta Ativa:                   [Trocar Conta] [X]│
│        PANTERAMOTOPEÇAS (ID: 806272575)                  │
└────────────────────────────────────────────────────────┘
```

### Modal de Seleção
```
┌──────────────────────────────────────────────────┐
│  🏪 Selecionar Conta do Mercado Livre        [X] │
├──────────────────────────────────────────────────┤
│  Este módulo requer uma conta do Mercado Livre   │
│  ✓ Conta atual: PANTERAMOTOPEÇAS                 │
│                                                   │
│  ┌────────────────────────────────────────────┐  │
│  │ ○ [PA] PANTERAMOTOPEÇAS          ✓         │  │
│  │        ID: 806272575 • loja@email.com      │  │
│  ├────────────────────────────────────────────┤  │
│  │ ○ [DI] DIVINOESPELHOS                      │  │
│  │        ID: 1919779391 • vendas@email.com   │  │
│  └────────────────────────────────────────────┘  │
│                                                   │
│  [+ Adicionar Nova Conta]   [Confirmar Seleção]  │
└──────────────────────────────────────────────────┘
```

---

## 🎨 Estilos Visuais

### Cores do Banner
- **Ativo:** Gradiente azul-verde (`primary` → `info`)
- **Sem Conta:** Gradiente laranja-vermelho (`warning` → `danger`)

### Estados da Conta
- **Normal:** Borda transparente
- **Hover:** Fundo cinza claro, borda azul
- **Ativa:** Fundo azul claro, borda azul forte

---

## 🔧 JavaScript API

### Métodos Globais

```javascript
// Abrir modal de seleção
AccountSelector.openModal();

// Selecionar conta (sem confirmar)
AccountSelector.selectAccount(accountId);

// Confirmar e aplicar seleção
AccountSelector.confirmSelection();

// Ocultar/mostrar banner
AccountSelector.toggleBanner();
AccountSelector.showBanner();

// Estado atual
console.log(AccountSelector.currentAccountId);
console.log(AccountSelector.selectedAccountId);
```

### Eventos

```javascript
// Após trocar conta, página recarrega automaticamente
// Você pode interceptar antes:
window.addEventListener('beforeunload', function() {
    // Salvar estado antes de recarregar
});
```

---

## 📋 Exemplos de Implementação

### 1. Módulo de Pedidos

```php
<?php
// app/Views/dashboard/orders.php

$requireAccountSelection = true;
$showAccountBanner = true;
$moduleTitle = 'Gestão de Pedidos';

include __DIR__ . '/../components/account-selector.php';
?>

<div class="container-fluid mt-4">
    <h2>Pedidos - <?= $activeAccount['nickname'] ?></h2>
    <!-- Seu código aqui -->
</div>
```

### 2. Módulo de Análise (Opcional)

```php
<?php
// app/Views/dashboard/analysis.php

$requireAccountSelection = false; // Não obrigatório
$showAccountBanner = true;
$moduleTitle = 'Análise de Anúncios';

include __DIR__ . '/../components/account-selector.php';
?>

<div class="container-fluid mt-4">
    <?php if ($activeAccount): ?>
        <!-- Conteúdo com conta -->
    <?php else: ?>
        <div class="alert alert-info">
            Selecione uma conta para ver análises específicas.
        </div>
    <?php endif; ?>
</div>
```

### 3. Módulo SEO Killer

```php
<?php
// app/Views/seo/index.php

$requireAccountSelection = true;
$showAccountBanner = true;
$moduleTitle = 'SEO Killer';

include __DIR__ . '/../components/account-selector.php';
?>

<script>
// Incluir account_id em todas as requisições
const accountId = <?= json_encode($activeAccountId) ?>;

function optimizeItem(itemId) {
    fetch('/api/seo/optimize', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            item_id: itemId,
            account_id: accountId // ✓ Sempre incluir
        })
    });
}
</script>
```

---

## 🔒 Segurança

### Validação Backend

**Sempre valide** a conta no backend, mesmo com seleção no frontend:

```php
// No controller
use App\Helpers\SessionHelper;

$accountId = SessionHelper::getActiveAccountId();

if (!$accountId) {
    http_response_code(400);
    echo json_encode(['error' => 'Nenhuma conta selecionada']);
    exit;
}

// Validar propriedade
$db = Database::getInstance();
$stmt = $db->prepare("
    SELECT id FROM ml_accounts 
    WHERE id = :account_id AND user_id = :user_id
");
$stmt->execute([
    'account_id' => $accountId,
    'user_id' => $_SESSION['user_id']
]);

if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Conta não autorizada']);
    exit;
}
```

---

## 📊 Fluxo de Seleção

```
1. Usuário acessa módulo
   ↓
2. Componente verifica conta ativa
   ↓
3a. Se tem conta ativa → Mostra banner
3b. Se não tem conta → Abre modal
   ↓
4. Usuário seleciona conta no modal
   ↓
5. Clica "Confirmar Seleção"
   ↓
6. POST /api/dashboard/switch-account
   ↓
7. Sessão atualizada
   ↓
8. Página recarrega
   ↓
9. Banner mostra nova conta ativa
```

---

## 🎯 Benefícios

### ✅ Evita Confusão
- Sempre claro qual conta está ativa
- Impossível usar módulo sem selecionar
- Visual destacado e persistente

### ✅ Melhora UX
- Troca rápida de conta (1 clique)
- Preferências salvas (banner oculto)
- Feedback visual imediato

### ✅ Reduz Erros
- Impossível enviar dados para conta errada
- Validação dupla (frontend + backend)
- Logs de troca de conta

### ✅ Multi-Tenant Seguro
- Isolamento por conta
- Validação de propriedade
- Audit trail completo

---

## 🔧 Personalização

### Alterar Cores

```css
/* No seu CSS customizado */
.account-context-banner {
    background: linear-gradient(135deg, #your-color-1, #your-color-2);
}

.account-avatar {
    background: linear-gradient(135deg, #your-color-1, #your-color-2);
}
```

### Posição do Banner

```css
/* Banner não sticky (normal) */
.account-context-banner {
    position: relative; /* em vez de sticky */
}
```

### Tema Escuro

```css
[data-theme="dark"] .account-selection-item:hover {
    background-color: #2d2d2d;
}

[data-theme="dark"] .account-selection-item.active {
    background-color: #1a3a52;
}
```

---

## 📝 Checklist de Implementação

Para cada módulo que precisa de conta:

- [ ] Incluir `account-selector.php` no início
- [ ] Configurar `$requireAccountSelection`
- [ ] Configurar `$showAccountBanner`
- [ ] Definir `$moduleTitle`
- [ ] Usar `$activeAccountId` nas chamadas de API
- [ ] Validar conta no backend
- [ ] Testar troca de conta
- [ ] Testar modal obrigatório
- [ ] Verificar responsividade

---

## 🐛 Troubleshooting

### Banner não aparece
- Verificar se `$showAccountBanner = true`
- Verificar localStorage `accountBannerHidden`
- Verificar se há contas vinculadas

### Modal não abre
- Verificar se Bootstrap JS está carregado
- Verificar console para erros
- Verificar se `$requireAccountSelection = true`

### Conta não troca
- Verificar rota `/api/dashboard/switch-account`
- Verificar sessão PHP
- Verificar logs do servidor
- Verificar permissões da conta

---

## 📞 Suporte

**Arquivos relacionados:**
- `app/Views/components/account-selector.php` - Componente principal
- `app/Helpers/SessionHelper.php` - Gerenciamento de sessão
- `app/Controllers/DashboardController.php` - Endpoint de troca
- `app/Middleware/AccountContextMiddleware.php` - Contexto de conta

**Testes:**
```bash
php tests/test_account_management.php
```

---

## 🎉 Resultado Final

Com este sistema implementado, você terá:

1. ✅ **Clareza total** sobre qual conta está ativa
2. ✅ **Impossibilidade de confusão** entre contas
3. ✅ **Troca rápida** quando necessário
4. ✅ **Visual profissional** e moderno
5. ✅ **Segurança garantida** com validação dupla

**Cada módulo agora tem consciência de conta explícita!** 🚀
