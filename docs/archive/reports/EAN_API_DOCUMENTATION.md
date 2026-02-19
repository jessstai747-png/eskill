# API de EANs - Documentação

Sistema de venda de códigos EAN por pacotes para sellers do Mercado Livre.

## Base URL
```
https://eskill.com.br/api/ean
```

## Autenticação

Todas as rotas requerem sessão autenticada (exceto webhooks). O `account_id` é obtido automaticamente da sessão.

---

## Endpoints Públicos

### Listar Pacotes
```http
GET /api/ean/packages
```

**Resposta:**
```json
{
    "success": true,
    "packages": [
        {
            "id": 1,
            "name": "Starter",
            "slug": "starter",
            "quantity": 10,
            "price": "149.00",
            "price_per_ean": "14.90",
            "discount_percent": 0,
            "description": "Ideal para começar",
            "badge": null,
            "is_featured": false
        }
    ]
}
```

---

## Endpoints do Seller

### Consultar Saldo
```http
GET /api/ean/balance
```

**Resposta:**
```json
{
    "success": true,
    "balance": {
        "available": 9,
        "used": 1,
        "total": 10
    }
}
```

### Listar Meus EANs
```http
GET /api/ean/my-eans
GET /api/ean/my-eans?available=1
```

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| available | int | Se `1`, retorna apenas EANs disponíveis |

**Resposta:**
```json
{
    "success": true,
    "eans": [
        {
            "id": 1,
            "ean_code": "7898123456789",
            "status": "available",
            "ml_item_id": null,
            "assigned_at": "2025-12-22 10:00:00"
        }
    ],
    "balance": {
        "available": 9,
        "used": 1,
        "total": 10
    }
}
```

### Histórico de Compras
```http
GET /api/ean/purchases
```

**Resposta:**
```json
{
    "success": true,
    "purchases": [
        {
            "id": 1,
            "package_id": 1,
            "package_name": "Starter",
            "quantity": 10,
            "total_amount": "149.00",
            "payment_status": "paid",
            "payment_method": "pix",
            "created_at": "2025-12-22 10:00:00",
            "paid_at": "2025-12-22 10:05:00"
        }
    ]
}
```

### Histórico de Transações
```http
GET /api/ean/transactions
```

**Resposta:**
```json
{
    "success": true,
    "transactions": [
        {
            "id": 1,
            "type": "purchase",
            "quantity": 10,
            "reference_type": "purchase",
            "reference_id": 1,
            "created_at": "2025-12-22 10:05:00"
        }
    ]
}
```

---

## Compra de EANs

### Iniciar Compra
```http
POST /api/ean/purchase
Content-Type: application/json

{
    "package_id": 1
}
```

**Resposta:**
```json
{
    "success": true,
    "purchase": {
        "id": 1,
        "quantity": 10,
        "total_amount": 149.00,
        "payment_status": "pending",
        "payment_expires_at": "2025-12-22 10:30:00"
    },
    "payment": {
        "qr_code": "00020126580014br.gov.bcb.pix...",
        "qr_code_base64": "data:image/png;base64,...",
        "external_id": "123456789"
    }
}
```

---

## Uso de EANs

### Usar EAN (marcar como usado)
```http
POST /api/ean/use
Content-Type: application/json

{
    "ean_id": 1,
    "ml_item_id": "MLB123456789"
}
```

**Resposta:**
```json
{
    "success": true,
    "ean": {
        "id": 1,
        "ean_code": "7898123456789",
        "status": "used",
        "ml_item_id": "MLB123456789"
    }
}
```

### Sugerir EAN Disponível
```http
GET /api/ean/suggest
```

**Resposta:**
```json
{
    "success": true,
    "ean": {
        "id": 2,
        "ean_code": "7898123456790"
    }
}
```

### Usar EAN para Item (integração)
```http
POST /api/ean/use-for-item
Content-Type: application/json

{
    "ml_item_id": "MLB123456789",
    "ean_id": 2
}
```

Se `ean_id` não for fornecido, o sistema sugere automaticamente um EAN disponível.

**Resposta:**
```json
{
    "success": true,
    "ean_code": "7898123456790",
    "ml_item_id": "MLB123456789"
}
```

### Consultar EAN de um Item
```http
GET /api/ean/by-item/{mlItemId}
```

**Resposta:**
```json
{
    "success": true,
    "ean": {
        "id": 2,
        "ean_code": "7898123456790",
        "ml_item_id": "MLB123456789",
        "used_at": "2025-12-22 11:00:00"
    }
}
```

### Exportar EANs (CSV)
```http
GET /api/ean/export
GET /api/ean/export?filter=available
GET /api/ean/export?filter=used
```

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| filter | string | `all` (padrão), `available`, ou `used` |

**Resposta:** Download de arquivo CSV com os seguintes campos:
- Código EAN
- Status
- Item ML
- Título do Produto
- SKU
- Data de Atribuição

### Estatísticas do Seller
```http
GET /api/ean/stats
```

**Resposta:**
```json
{
    "success": true,
    "stats": {
        "total_purchased": 50,
        "available": 42,
        "used": 8,
        "usage_rate": 16.0,
        "total_spent": 1247.00,
        "purchases_count": 3,
        "avg_price_per_ean": 24.94,
        "usage_by_month": {
            "2025-01": 2,
            "2025-02": 3,
            "2025-03": 3
        }
    }
}
```

| Campo | Descrição |
|-------|-----------|
| total_purchased | Total de EANs comprados |
| available | EANs disponíveis para uso |
| used | EANs vinculados a itens |
| usage_rate | Taxa de utilização (%) |
| total_spent | Valor total investido (R$) |
| purchases_count | Número de compras realizadas |
| avg_price_per_ean | Preço médio por EAN |
| usage_by_month | Uso mensal de EANs (últimos meses) |

### Desvincular EAN
```http
POST /api/ean/unlink
Content-Type: application/json

{
    "assignment_id": 123
}
```

**Resposta:**
```json
{
    "success": true,
    "ean": "7898123456789",
    "message": "EAN desvinculado com sucesso"
}
```

Remove a vinculação de um EAN com um anúncio, deixando-o disponível para reutilização.

### Verificar Estoque Baixo
```http
GET /api/ean/low-stock
```

**Resposta:**
```json
{
    "success": true,
    "available": 3,
    "threshold": 5,
    "is_low": true,
    "is_critical": false,
    "message": "Estoque baixo (3 disponíveis)"
}
```

### Widget de EAN
```http
GET /api/ean/widget
```

Retorna dados resumidos para exibição em widgets compactos.

**Resposta:**
```json
{
    "success": true,
    "widget": {
        "available": 25,
        "total_purchased": 50,
        "total_used": 25,
        "alert_level": "ok",
        "alert_message": "",
        "can_use_ean": true,
        "purchase_url": "/dashboard/ean#packages"
    }
}
```

| alert_level | Descrição |
|-------------|-----------|
| ok | Estoque normal (>10) |
| warning | Estoque baixo (≤10) |
| danger | Estoque crítico (≤5) |

### Preview de EAN
```http
GET /api/ean/preview
```

Retorna o próximo EAN disponível sem marcá-lo como usado.

**Resposta:**
```json
{
    "success": true,
    "preview": {
        "ean": "7898123456789",
        "available_after_use": 24
    }
}
```

### Auto-Atribuição de EAN
```http
POST /api/ean/auto-assign
Content-Type: application/json

{
    "ml_item_id": "MLB123456789",
    "title": "Título do Produto"
}
```

Atribui automaticamente o próximo EAN disponível a um anúncio.

**Resposta:**
```json
{
    "success": true,
    "ean": "7898123456789",
    "assignment_id": 456
}
```

Se o item já tiver um EAN:
```json
{
    "success": true,
    "already_assigned": true,
    "ean": "7898123456789"
}
```

---

## Webhook Mercado Pago

### Notificação de Pagamento
```http
POST /api/ean/webhook/mercadopago
Content-Type: application/json

{
    "type": "payment",
    "data": {
        "id": "123456789"
    }
}
```

O webhook processa automaticamente:
- `approved` → Confirma compra e libera EANs
- `rejected/cancelled` → Cancela compra e libera reservas

---

## Endpoints Admin

### Dashboard
```http
GET /api/ean/admin/dashboard
```

**Resposta:**
```json
{
    "success": true,
    "data": {
        "inventory": {
            "available": 1000,
            "reserved": 5,
            "sold": 50
        },
        "sales": {
            "today": { "orders": 5, "revenue": 745.00 },
            "month": { "orders": 120, "revenue": 18900.00 }
        }
    }
}
```

### Listar Compras
```http
GET /api/ean/admin/purchases
GET /api/ean/admin/purchases?status=paid&page=1
```

### Listar Inventário
```http
GET /api/ean/admin/inventory
GET /api/ean/admin/inventory?status=available&page=1
```

### Adicionar EANs Manualmente
```http
POST /api/ean/admin/inventory/add
Content-Type: application/json

{
    "eans": ["7898123456789", "7898123456790"],
    "batch": "LOTE-001",
    "cost": 3.50,
    "supplier": "Fornecedor X"
}
```

### Importar EANs de Arquivo
```http
POST /api/ean/admin/inventory/import
Content-Type: multipart/form-data

file: (arquivo .txt ou .csv)
batch: LOTE-002
cost: 3.00
supplier: Fornecedor Y
```

### Confirmar Pagamento Manual
```http
POST /api/ean/admin/confirm-payment
Content-Type: application/json

{
    "purchase_id": 1
}
```

---

## Relatórios Admin

### Relatório de Vendas
```http
GET /api/ean/admin/reports/sales?start=2025-12-01&end=2025-12-31
```

**Resposta:**
```json
{
    "success": true,
    "data": {
        "period": { "start": "2025-12-01", "end": "2025-12-31" },
        "totals": {
            "total_orders": 50,
            "total_eans": 1500,
            "total_revenue": 12500.00
        },
        "daily": [...],
        "by_package": [...]
    }
}
```

### Relatório de Uso
```http
GET /api/ean/admin/reports/usage?start=2025-12-01&end=2025-12-31
```

### Relatório de Inventário
```http
GET /api/ean/admin/reports/inventory
```

**Resposta:**
```json
{
    "success": true,
    "data": {
        "current": {
            "available": 1000,
            "reserved": 10,
            "sold": 500,
            "total": 1510
        },
        "projection": {
            "avg_daily_sales": 15.5,
            "days_remaining": 64
        }
    }
}
```

### Exportar CSV
```http
GET /api/ean/admin/reports/sales/export?start=2025-12-01&end=2025-12-31
```

Retorna arquivo CSV para download.

### Enviar Relatório por Email
```http
POST /api/ean/admin/reports/send-daily
```

---

## Configuração Mercado Pago

### Salvar Credenciais
```http
POST /api/ean/admin/config/mercadopago
Content-Type: application/json

{
    "access_token": "APP_USR-...",
    "public_key": "APP_USR-...",
    "webhook_secret": "..."
}
```

### Testar Conexão
```http
GET /api/ean/admin/config/mercadopago/test
```

---

## Códigos de Erro

| Código | Descrição |
|--------|-----------|
| 400 | Parâmetros inválidos |
| 401 | Não autenticado |
| 403 | Sem permissão (admin) |
| 404 | Recurso não encontrado |
| 409 | Conflito (saldo insuficiente, EAN já usado) |
| 500 | Erro interno |

---

## Widget JavaScript

O sistema inclui um componente JavaScript reutilizável para integrar EANs em qualquer página.

### Incluir no HTML
```html
<script src="/assets/js/ean-widget.js"></script>
```

### Auto-Inicialização
Para inicialização automática, adicione um container com id `ean-widget-auto`:
```html
<div id="ean-widget-auto"></div>
```

### Inicialização Manual
```javascript
// Widget completo
const widget = new EanWidget('meu-container', {
    showBalance: true,
    showAlert: true,
    showPreview: false,
    compact: false,
    autoRefresh: true,
    refreshInterval: 60000,
    onEanUsed: (ean, assignmentId) => {
        console.log('EAN usado:', ean);
    },
    onLowStock: (data) => {
        console.log('Estoque baixo:', data.available);
    }
});

// Widget compacto (badge)
const miniWidget = new EanWidget('header-ean', { compact: true });
```

### Métodos do Widget

| Método | Descrição |
|--------|-----------|
| `refresh()` | Recarrega dados do servidor |
| `getPreview()` | Exibe preview do próximo EAN |
| `autoAssign(mlItemId, title)` | Atribui EAN automaticamente |
| `destroy()` | Remove widget e para auto-refresh |

### Propriedades

| Propriedade | Tipo | Descrição |
|-------------|------|-----------|
| `available` | int | Quantidade disponível |
| `hasEan` | bool | Se há EANs disponíveis |
| `isLowStock` | bool | Se estoque está baixo |

### Exemplo de Integração com Criação de Anúncio
```javascript
const eanWidget = new EanWidget('ean-status', {
    compact: true,
    onLowStock: (data) => {
        // Mostrar alerta ao usuário
        alert(`Atenção: Você tem apenas ${data.available} EANs disponíveis.`);
    }
});

// Ao criar anúncio
async function createListing(itemData) {
    // Atribuir EAN automaticamente
    try {
        const ean = await eanWidget.autoAssign(itemData.id, itemData.title);
        itemData.gtin = ean;
        console.log('EAN atribuído:', ean);
    } catch (error) {
        // Redirecionar para compra de pacotes
        window.location.href = '/dashboard/ean#packages';
    }
}
```

---

## Exemplo de Fluxo Completo

```javascript
// 1. Verificar saldo
const balance = await fetch('/api/ean/balance').then(r => r.json());
console.log('Saldo:', balance.balance.available);

// 2. Se precisar comprar
if (balance.balance.available < 1) {
    const packages = await fetch('/api/ean/packages').then(r => r.json());
    
    // Iniciar compra
    const purchase = await fetch('/api/ean/purchase', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ package_id: 1 })
    }).then(r => r.json());
    
    // Mostrar QR Code PIX
    console.log('PIX:', purchase.payment.qr_code);
}

// 3. Usar EAN para item
const result = await fetch('/api/ean/use-for-item', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ml_item_id: 'MLB123456789' })
}).then(r => r.json());

console.log('EAN usado:', result.ean_code);
```
