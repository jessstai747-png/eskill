# OpenClaw Connector — Referência Completa de Endpoints

> **Base URL:** `https://eskill.com.br/api/openclaw`
> **Auth:** Bearer Token no header `Authorization: Bearer <token>`
> **Scopes:** `openclaw:read`, `openclaw:write`, `openclaw:admin`
> **OpenAPI spec:** [`/api-docs/openapi.json`](https://eskill.com.br/api-docs/openapi.json)
> **Swagger UI:** [`/api-docs/`](https://eskill.com.br/api-docs/)

---

## 1. Stack & Arquitetura

| Item              | Detalhe                                                      |
|-------------------|--------------------------------------------------------------|
| **Framework**     | Custom PHP 8.0+ MVC (`Router → Controller → Service → Model`) |
| **Banco**         | MySQL 8 via PDO                                              |
| **HTTP Client**   | Guzzle 7                                                     |
| **Logging**       | Monolog 3                                                    |
| **Auth**          | Bearer Token (`ApiAuthMiddleware` + `ApiTokenService`)       |
| **Rate Limit**    | Por token (controlado no middleware)                          |
| **CORS**          | Habilitado para `/api/openclaw*`                              |

### Arquivos-chave

| Arquivo | Responsabilidade |
|---------|-----------------|
| `app/Routes/api.php` (linhas 170–189) | Registro das 17 rotas OpenClaw |
| `app/Controllers/OpenClawConnectorController.php` | Controller HTTP (540 linhas) |
| `app/Services/OpenClawConnectorService.php` | Lógica de negócio (638 linhas) |
| `app/Middleware/ApiAuthMiddleware.php` | Extração e validação do Bearer token |
| `app/Services/ApiTokenService.php` | CRUD + validação de tokens e escopos |
| `app/Services/ItemService.php` | Backend de itens (API ML + cache local) |
| `app/Services/OrderService.php` | Backend de pedidos (API ML + cache local) |
| `app/Services/AssistantConnectorService.php` | Infra compartilhada: sellers, actions |
| `public/api-docs/openapi.json` | Spec OpenAPI 3.0 |

---

## 2. Autenticação

Toda rota (exceto `GET /api/openclaw`) exige header:

```
Authorization: Bearer <token>
```

O token é validado pelo `ApiAuthMiddleware`:
1. Extraído do header `Authorization: Bearer ...`
2. Buscado na tabela `api_tokens`
3. Verificado: `is_active = 1`, `expires_at > NOW()`
4. Escopos verificados pelo controller

### Escopos

| Escopo | Acesso |
|--------|--------|
| `openclaw:read` | GET em sellers, items, orders, actions, webhooks |
| `openclaw:write` | POST em actions e webhooks; DELETE webhooks |
| `openclaw:admin` | Acesso completo (superset de read + write) |

### Erros de Auth

| HTTP | Body | Quando |
|------|------|--------|
| `401` | `{"error": "Unauthorized", "message": "Token não fornecido"}` | Header ausente |
| `401` | `{"error": "Unauthorized", "message": "Token inválido ou expirado"}` | Token inválido/expirado |
| `403` | `{"error": "Forbidden", "message": "Permissão insuficiente"}` | Escopo não autorizado |

---

## 3. Endpoints Completos

### 3.0 Discovery (Público)

#### `GET /api/openclaw`

> **Auth:** Nenhuma (público)

Lista todos os endpoints disponíveis, escopos e formato de auth.

**Response `200`:**
```json
{
  "success": true,
  "service": "openclaw-connector",
  "version": "1.0.0",
  "documentation": "/api-docs/",
  "endpoints": {
    "GET  /api/openclaw/health": "Health check (requer auth)",
    "GET  /api/openclaw/sellers": "Listar contas ML",
    "...": "..."
  },
  "auth": {
    "type": "Bearer Token",
    "header": "Authorization: Bearer <token>",
    "scopes": ["openclaw:read", "openclaw:write", "openclaw:admin"]
  }
}
```

---

### 3.1 Health

#### `GET /api/openclaw/health`

> **Auth:** Bearer Token (qualquer escopo)

Verifica comunicação com DB e estado do serviço.

**Response `200`:**
```json
{
  "success": true,
  "service": "openclaw-connector",
  "version": "1.0.0",
  "time": "2026-02-24T15:30:00-03:00",
  "db": "ok",
  "auth": {
    "user_id": 1,
    "token_id": 5
  },
  "capabilities": ["sellers", "items", "orders", "actions", "webhooks"]
}
```

---

### 3.2 Sellers (Contas ML)

#### `GET /api/openclaw/sellers`

> **Auth:** `openclaw:read` ou `openclaw:admin`

Lista todas as contas do Mercado Livre vinculadas ao usuário.

| Query Param | Tipo | Default | Descrição |
|-------------|------|---------|-----------|
| *(nenhum)* | — | — | Retorna todas as contas do usuário |

**Response `200`:**
```json
{
  "success": true,
  "sellers": [
    {
      "id": 1,
      "ml_user_id": 123456789,
      "nickname": "AWA_MOTOS",
      "email": "contato@awamotos.com.br",
      "site_id": "MLB",
      "status": "active",
      "last_synced_at": "2026-02-24T10:00:00",
      "created_at": "2025-06-15T08:30:00"
    },
    {
      "id": 2,
      "ml_user_id": 987654321,
      "nickname": "AWA_ACESSORIOS",
      "email": "loja2@awamotos.com.br",
      "site_id": "MLB",
      "status": "active",
      "last_synced_at": "2026-02-24T09:45:00",
      "created_at": "2025-09-20T14:00:00"
    }
  ]
}
```

**Campos importantes para monitoramento:**
- `created_at` — quando a conta foi criada (detectar contas novas)
- `last_synced_at` — última sincronização (detectar inatividade)
- `status` — estado da conta

---

#### `GET /api/openclaw/sellers/{id}`

> **Auth:** `openclaw:read` ou `openclaw:admin`

Detalhe de uma conta ML específica.

| Path Param | Tipo | Descrição |
|------------|------|-----------|
| `id` | int | ID interno da conta (`ml_accounts.id`) |

**Response `200`:**
```json
{
  "success": true,
  "seller": {
    "id": 1,
    "ml_user_id": 123456789,
    "nickname": "AWA_MOTOS",
    "email": "contato@awamotos.com.br",
    "site_id": "MLB",
    "status": "active",
    "last_synced_at": "2026-02-24T10:00:00",
    "created_at": "2025-06-15T08:30:00"
  }
}
```

**Erros:**
| HTTP | Quando |
|------|--------|
| `400` | `id` não é um inteiro positivo |
| `404` | Conta não encontrada ou não pertence ao usuário |

---

### 3.3 Items (Anúncios ML)

#### `GET /api/openclaw/sellers/{id}/items`

> **Auth:** `openclaw:read` ou `openclaw:admin`

Lista anúncios de uma conta ML com paginação e filtros.

| Path Param | Tipo | Descrição |
|------------|------|-----------|
| `id` | int | ID da conta (`ml_accounts.id`) |

| Query Param | Tipo | Default | Descrição |
|-------------|------|---------|-----------|
| `status` | string | *(todos)* | `active`, `paused`, `closed` |
| `category_id` | string | *(todos)* | Filtrar por categoria ML |
| `search` | string | *(vazio)* | Busca por texto no título |
| `page` | int | `1` | Página atual |
| `per_page` | int | `50` | Itens por página (máx: 200) |

**Response `200`:**
```json
{
  "success": true,
  "items": [
    {
      "id": "MLB1234567890",
      "title": "Bagageiro Para Honda CG 160 Titan Fan Start 2016 a 2025",
      "status": "active",
      "price": 189.90,
      "available_quantity": 42,
      "sold_quantity": 387,
      "catalog_product_id": null,
      "category_id": "MLB189462",
      "permalink": "https://produto.mercadolivre.com.br/MLB-1234567890",
      "thumbnail": "https://http2.mlstatic.com/D_NQ_NP_...",
      "date_created": "2025-03-10T14:22:00.000Z",
      "last_updated": "2026-02-23T08:15:00.000Z",
      "visits": 15230
    }
  ],
  "page": 1,
  "pages": 5,
  "limit": 50,
  "total": 237,
  "has_more": true
}
```

**Campos importantes para monitoramento:**
- `date_created` — criação do item (padrão de atividade)
- `last_updated` — última atualização
- `available_quantity` — estoque disponível
- `sold_quantity` — total vendido

---

#### `GET /api/openclaw/sellers/{id}/items/stats`

> **Auth:** `openclaw:read` ou `openclaw:admin`

Estatísticas agregadas de todos os itens da conta.

**Response `200`:**
```json
{
  "success": true,
  "stats": {
    "total": 237,
    "active": 195,
    "paused": 30,
    "closed": 12,
    "catalog": 45,
    "common": 192,
    "total_revenue": 458750.50,
    "total_quantity": 1520,
    "total_views": 385200,
    "total_sold": 4830,
    "low_stock": 18
  }
}
```

---

#### `GET /api/openclaw/sellers/{id}/items/{itemId}`

> **Auth:** `openclaw:read` ou `openclaw:admin`

Detalhe completo de um anúncio.

| Path Param | Tipo | Descrição |
|------------|------|-----------|
| `id` | int | ID da conta |
| `itemId` | string | ID do item ML (ex: `MLB1234567890`) |

**Response `200`:**
```json
{
  "success": true,
  "item": {
    "id": "MLB1234567890",
    "site_id": "MLB",
    "title": "Bagageiro Para Honda CG 160 Titan Fan Start 2016 a 2025",
    "subtitle": null,
    "seller_id": 123456789,
    "category_id": "MLB189462",
    "price": 189.90,
    "currency_id": "BRL",
    "available_quantity": 42,
    "sold_quantity": 387,
    "buying_mode": "buy_it_now",
    "listing_type_id": "gold_special",
    "condition": "new",
    "permalink": "https://produto.mercadolivre.com.br/MLB-1234567890",
    "thumbnail": "https://http2.mlstatic.com/D_NQ_NP_...",
    "pictures": [],
    "attributes": [],
    "shipping": { "free_shipping": true, "mode": "me2" },
    "status": "active",
    "date_created": "2025-03-10T14:22:00.000Z",
    "last_updated": "2026-02-23T08:15:00.000Z",
    "health": 0.85,
    "catalog_product_id": null
  }
}
```

**Erros:**
| HTTP | Quando |
|------|--------|
| `400` | `id` inválido |
| `404` | Item não encontrado |

---

### 3.4 Orders (Pedidos / Vendas)

#### `GET /api/openclaw/sellers/{id}/orders`

> **Auth:** `openclaw:read` ou `openclaw:admin`

Lista pedidos de uma conta ML com filtros por status e período.

| Path Param | Tipo | Descrição |
|------------|------|-----------|
| `id` | int | ID da conta |

| Query Param | Tipo | Default | Descrição |
|-------------|------|---------|-----------|
| `status` | string | *(todos)* | `paid`, `cancelled`, `shipped`, `delivered` |
| `date_from` | string | *(sem limite)* | ISO 8601 ou `YYYY-MM-DD`. Ex: `2026-02-01` |
| `date_to` | string | *(sem limite)* | ISO 8601 ou `YYYY-MM-DD`. Ex: `2026-02-24` |
| `search` | string | *(vazio)* | Busca por order_id ou buyer nickname |
| `sort` | string | `date_created` | Campo de ordenação: `date_created`, `total_amount`, `status` |
| `order` | string | `DESC` | Direção: `ASC` ou `DESC` |
| `page` | int | `1` | Página atual |
| `per_page` | int | `50` | Itens por página (máx: 200) |

**Response `200`:**
```json
{
  "success": true,
  "source": "ml_api",
  "orders": [
    {
      "id": 2000000123456789,
      "status": "paid",
      "total_amount": 189.90,
      "date_created": "2026-02-24T10:30:00.000-04:00",
      "buyer": {
        "id": 555777999,
        "nickname": "COMPRADOR_123"
      },
      "order_items": [
        {
          "item": {
            "id": "MLB1234567890",
            "title": "Bagageiro Para Honda CG 160",
            "variation_id": null
          },
          "quantity": 1,
          "unit_price": 189.90
        }
      ],
      "shipping": {
        "id": 43210987654
      },
      "payments": [
        {
          "id": 98765432,
          "status": "approved",
          "transaction_amount": 189.90,
          "payment_type": "credit_card"
        }
      ],
      "account_nickname": "AWA_MOTOS"
    }
  ],
  "page": 1,
  "pages": 8,
  "limit": 50,
  "total": 372,
  "has_more": true
}
```

**Campos importantes para monitoramento:**
- `status`: `paid` = venda confirmada, `cancelled` = cancelado
- `date_created`: timestamp da venda → detectar "conta sem vendas" comparando com NOW
- `total_amount`: valor total
- `buyer`: dados do comprador

---

#### `GET /api/openclaw/sellers/{id}/orders/{orderId}`

> **Auth:** `openclaw:read` ou `openclaw:admin`

Detalhe completo de um pedido.

| Path Param | Tipo | Descrição |
|------------|------|-----------|
| `id` | int | ID da conta |
| `orderId` | string | ID do pedido ML |

**Response `200`:**
```json
{
  "success": true,
  "source": "ml_api",
  "id": 2000000123456789,
  "status": "paid",
  "total_amount": 189.90,
  "date_created": "2026-02-24T10:30:00.000-04:00",
  "data": { "...": "objeto completo da API do ML" }
}
```

---

### 3.5 Actions (Ações Assíncronas)

#### `POST /api/openclaw/actions`

> **Auth:** `openclaw:write` ou `openclaw:admin`

Criar uma ação assíncrona. Suporta idempotência via `Idempotency-Key` header ou campo no body.

**Headers opcionais:**
| Header | Descrição |
|--------|-----------|
| `Idempotency-Key` | UUID para evitar ação duplicada |

**Body (JSON):**
```json
{
  "action": "update_stock",
  "account_id": 1,
  "parameters": {
    "item_id": "MLB1234567890",
    "quantity": 50
  },
  "idempotency_key": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Ações permitidas:**

| Action | Descrição |
|--------|-----------|
| `answer_question` | Responder pergunta de comprador |
| `update_stock` | Atualizar estoque |
| `update_price` | Atualizar preço |
| `reconcile_order` | Reconciliar pedido |
| `refresh_account_token` | Renovar token OAuth ML |
| `sync_item` | Sincronizar item com ML |
| `pause_item` | Pausar anúncio |
| `activate_item` | Ativar/reativar anúncio |

**Response `202` (Created):**
```json
{
  "success": true,
  "created": true,
  "action_run": {
    "id": 42,
    "action": "update_stock",
    "status": "pending",
    "created_at": "2026-02-24T15:30:00"
  },
  "action_run_id": 42,
  "job_id": "job_abc123"
}
```

**Response `200` (Idempotente — já existia):**
```json
{
  "success": true,
  "created": false,
  "action_run": { "id": 42, "status": "completed" },
  "action_run_id": 42,
  "job_id": null
}
```

**Erros:**
| HTTP | Error | Quando |
|------|-------|--------|
| `400` | `validation_error` | Ação inválida ou body malformado |
| `503` | *(variável)* | Falha interna ao criar a ação |

---

#### `GET /api/openclaw/actions/{id}`

> **Auth:** `openclaw:read` ou `openclaw:admin`

Consulta status de uma ação criada.

| Path Param | Tipo | Descrição |
|------------|------|-----------|
| `id` | int | ID do action_run |

**Response `200`:**
```json
{
  "success": true,
  "action_run": {
    "id": 42,
    "action": "update_stock",
    "status": "completed",
    "result": { "new_quantity": 50 },
    "created_at": "2026-02-24T15:30:00",
    "completed_at": "2026-02-24T15:30:05"
  }
}
```

**Status possíveis:** `pending`, `running`, `completed`, `failed`

---

### 3.6 Webhooks (Notificações Outbound)

#### `GET /api/openclaw/webhooks`

> **Auth:** `openclaw:read` ou `openclaw:admin`

Lista webhooks registrados.

**Response `200`:**
```json
{
  "success": true,
  "webhooks": [
    {
      "id": 1,
      "name": "OpenClaw Order Webhook",
      "url": "https://openclaw.example.com/webhook/eskill",
      "events": ["order.created", "order.updated"],
      "is_active": true,
      "last_triggered_at": "2026-02-24T10:30:00",
      "failure_count": 0,
      "created_at": "2026-02-20T14:00:00"
    }
  ]
}
```

---

#### `POST /api/openclaw/webhooks`

> **Auth:** `openclaw:write` ou `openclaw:admin`

Registra um novo webhook.

**Body (JSON):**
```json
{
  "name": "OpenClaw Events",
  "url": "https://openclaw.example.com/webhook/eskill",
  "events": ["order.created", "order.updated", "stock.changed"],
  "secret": "optional-hmac-secret"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `name` | string | Sim | Nome descritivo |
| `url` | string | Sim | URL de callback (HTTPS recomendado) |
| `events` | string[] | Sim | Eventos a receber (ver lista abaixo) |
| `secret` | string | Não | HMAC secret para assinatura. Auto-gerado se omitido |

**Response `201`:**
```json
{
  "success": true,
  "webhook": {
    "id": 2,
    "name": "OpenClaw Events",
    "url": "https://openclaw.example.com/webhook/eskill",
    "secret": "a1b2c3...auto-generated-64-hex...",
    "events": ["order.created", "order.updated", "stock.changed"],
    "is_active": true
  }
}
```

---

#### `DELETE /api/openclaw/webhooks/{id}`

> **Auth:** `openclaw:write` ou `openclaw:admin`

Remove um webhook.

**Response `200`:** `{"success": true}`
**Response `404`:** `{"success": false, "error": "Webhook não encontrado"}`

---

#### `POST /api/openclaw/webhooks/{id}/test`

> **Auth:** `openclaw:write` ou `openclaw:admin`

Envia um payload de teste para o webhook.

**Response `200`:**
```json
{
  "success": true,
  "status_code": 200
}
```

O payload de teste enviado ao seu endpoint:
```json
{
  "event": "webhook.test",
  "timestamp": "2026-02-24T15:30:00-03:00",
  "data": {
    "message": "Este é um teste de webhook do eskill.com.br para OpenClaw",
    "webhook_id": 2
  }
}
```

**Headers enviados:**
```
Content-Type: application/json
X-Signature: <hmac-sha256-do-body-com-secret>
X-Webhook-Source: eskill.com.br
User-Agent: eskill-openclaw-webhook/1.0
```

---

#### `GET /api/openclaw/webhook-events`

> **Auth:** `openclaw:read`, `openclaw:write` ou `openclaw:admin`

Lista todos os tipos de evento disponíveis para subscrição.

**Response `200`:**
```json
{
  "success": true,
  "events": [
    "order.created",
    "order.updated",
    "order.shipped",
    "item.updated",
    "item.paused",
    "item.activated",
    "stock.changed",
    "price.changed",
    "question.received",
    "question.answered",
    "message.received",
    "account.token_refreshed",
    "webhook.test"
  ]
}
```

---

## 4. Delta Sync — Como Buscar Apenas Mudanças

### 4.1 Orders (Pedidos)

O endpoint `GET /sellers/{id}/orders` suporta **filtragem por período**:

```bash
# Pedidos criados desde ontem
GET /api/openclaw/sellers/1/orders?date_from=2026-02-23&date_to=2026-02-24

# Apenas pedidos pagos na última hora  
GET /api/openclaw/sellers/1/orders?date_from=2026-02-24T14:00:00&status=paid

# Pedidos mais recentes primeiro
GET /api/openclaw/sellers/1/orders?sort=date_created&order=DESC&page=1&per_page=50
```

| Param | Para delta sync |
|-------|-----------------|
| `date_from` | **Use como `since`**: data/hora da última execução |
| `date_to` | Opcional: até quando buscar |
| `sort=date_created` | Ordenar por criação (default) |
| `order=DESC` | Mais recentes primeiro |
| `page` + `per_page` | Paginação offset-based |

**Estratégia recomendada:**
```
1. Armazenar localmente: last_sync_at = "2026-02-24T10:00:00"
2. Buscar: GET /sellers/{id}/orders?date_from=2026-02-24T10:00:00&sort=date_created&order=ASC
3. Paginar enquanto has_more = true
4. Atualizar last_sync_at = data do último pedido recebido
```

### 4.2 Items (Anúncios)

Items não têm filtro `since` nativo. Estratégias:

1. **Por status** — buscar `status=active` e comparar com cache local
2. **Stats primeiro** — chamar `/items/stats` e comparar totais
3. **Webhook `item.updated`** — registrar webhook para receber updates push

```bash
# Buscar todos os itens ativos, página por página
GET /api/openclaw/sellers/1/items?status=active&page=1&per_page=200

# Comparar com cache local via last_updated de cada item
# Items retornados incluem "date_created" e "last_updated"
```

### 4.3 Sellers (Contas)

Sellers raramente mudam. Use:
- `GET /sellers` → campo `created_at` para detectar contas novas
- `GET /sellers` → campo `last_synced_at` para detectar inatividade

### 4.4 Resumo de Paginação

| Endpoint | Paginação | Params |
|----------|-----------|--------|
| `/sellers` | Sem paginação (todas) | — |
| `/sellers/{id}/items` | Offset-based | `page`, `per_page` (max 200) |
| `/sellers/{id}/orders` | Offset-based | `page`, `per_page` (max 200) |
| `/actions/{id}` | Unitário | — |
| `/webhooks` | Sem paginação (todas) | — |

---

## 5. Monitoramento de Contas — Mapeamento

### 5.1 Detectar contas sem vendas

```bash
# Para cada seller, buscar pedidos dos últimos 30 dias
GET /api/openclaw/sellers
# → Para cada seller.id:
GET /api/openclaw/sellers/{id}/orders?date_from=2026-01-25&status=paid&per_page=1

# Se total = 0 → conta sem vendas nos últimos 30 dias
```

### 5.2 Detectar contas novas

```bash
GET /api/openclaw/sellers
# Comparar seller.created_at com date threshold
# Se created_at > (now - 7 dias) → conta nova
```

### 5.3 Detectar padrão de atividade 24/7

```bash
# Buscar pedidos de cada conta e analisar distribuição horária
GET /api/openclaw/sellers/{id}/orders?date_from=2026-02-17&per_page=200&page=1
# Analisar order.date_created para distribuição por hora
# Se há vendas uniformes em todas as horas (0h-6h inclusive) → suspeito

# Buscar items e verificar date_created / last_updated distribution
GET /api/openclaw/sellers/{id}/items?page=1&per_page=200
# Analisar item.date_created e item.last_updated
```

### 5.4 Detectar inatividade geral

```bash
GET /api/openclaw/sellers/{id}
# Se last_synced_at é muito antigo → conta possivelmente abandonada

GET /api/openclaw/sellers/{id}/items/stats
# Se total = 0 ou active = 0 → sem anúncios ativos
```

---

## 6. Erros Comuns

| HTTP | Formato | Descrição |
|------|---------|-----------|
| `400` | `{"success": false, "error": "...", "message": "..."}` | Parâmetro inválido |
| `401` | `{"error": "Unauthorized", "message": "Token não fornecido\|inválido"}` | Sem auth |
| `403` | `{"error": "Forbidden", "message": "Permissão insuficiente"}` | Escopo errado |
| `404` | `{"success": false, "error": "...não encontrado"}` | Recurso não existe |
| `422` | `{"success": false, "error": "validation_error", "message": "..."}` | Body malformado |
| `500` | `{"success": false, "error": "..."}` | Erro interno |
| `502` | `{"success": false, "error": "..."}` | Falha no webhook delivery |
| `503` | `{"success": false, "error": "..."}` | DB ou API ML indisponível |

---

## 7. Exemplos curl

```bash
# =============================================
# Variáveis (substitua pelo seu token real)
# =============================================
TOKEN="seu_token_aqui"
BASE="https://eskill.com.br/api/openclaw"

# =============================================
# Discovery (público, sem auth)
# =============================================
curl -s "$BASE" | jq .

# =============================================
# Health check
# =============================================
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/health" | jq .

# =============================================
# Listar sellers
# =============================================
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/sellers" | jq .

# =============================================
# Detalhe de um seller
# =============================================
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/sellers/1" | jq .

# =============================================
# Listar items (anúncios) ativos da conta 1
# =============================================
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/sellers/1/items?status=active&page=1&per_page=50" | jq .

# =============================================
# Estatísticas dos items
# =============================================
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/sellers/1/items/stats" | jq .

# =============================================
# Detalhe de um item
# =============================================
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/sellers/1/items/MLB1234567890" | jq .

# =============================================
# Listar pedidos (delta sync: desde ontem, só pagos)
# =============================================
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/sellers/1/orders?date_from=2026-02-23&status=paid&page=1&per_page=50" | jq .

# =============================================
# Detalhe de um pedido
# =============================================
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/sellers/1/orders/2000000123456789" | jq .

# =============================================
# Criar ação assíncrona (update stock)
# =============================================
curl -s -X POST -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{
    "action": "update_stock",
    "account_id": 1,
    "parameters": {
      "item_id": "MLB1234567890",
      "quantity": 50
    }
  }' "$BASE/actions" | jq .

# =============================================
# Consultar status de uma ação
# =============================================
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/actions/42" | jq .

# =============================================
# Listar webhook events disponíveis
# =============================================
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/webhook-events" | jq .

# =============================================
# Registrar webhook
# =============================================
curl -s -X POST -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "OpenClaw Monitor",
    "url": "https://openclaw.example.com/webhook/eskill",
    "events": ["order.created", "order.updated", "stock.changed", "item.updated"]
  }' "$BASE/webhooks" | jq .

# =============================================
# Listar webhooks registrados
# =============================================
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/webhooks" | jq .

# =============================================
# Testar webhook
# =============================================
curl -s -X POST -H "Authorization: Bearer $TOKEN" \
  "$BASE/webhooks/1/test" | jq .

# =============================================
# Deletar webhook
# =============================================
curl -s -X DELETE -H "Authorization: Bearer $TOKEN" \
  "$BASE/webhooks/1" | jq .
```

---

## 8. Testes Automatizados

| Tipo | Ferramenta | Status |
|------|-----------|--------|
| Unit + Integration | PHPUnit 9 | 2896 testes passando |
| E2E | Playwright (TS) | Configurado em `playwright.config.ts` |
| Postman Collection | *(não existe ainda)* | Para gerar, exportar os curls acima |

### Comando para rodar testes:
```bash
# Todos
php vendor/bin/phpunit

# Apenas unit
php vendor/bin/phpunit --testsuite=Unit

# Filtrar
php vendor/bin/phpunit --filter OpenClaw
```

---

## 9. Resumo Quick Reference

| # | Método | Path | Auth | Uso para monitoramento |
|---|--------|------|------|----------------------|
| 0 | `GET` | `/api/openclaw` | público | Discovery + lista endpoints |
| 1 | `GET` | `/api/openclaw/health` | qualquer | Verificar conectividade |
| 2 | `GET` | `/api/openclaw/sellers` | read | **Listar contas** (created_at, status) |
| 3 | `GET` | `/api/openclaw/sellers/{id}` | read | **Detalhe conta** (last_synced_at) |
| 4 | `GET` | `/api/openclaw/sellers/{id}/items` | read | **Listar anúncios** (date_created, last_updated) |
| 5 | `GET` | `/api/openclaw/sellers/{id}/items/stats` | read | **Totais**: ativos, pausados, estoque baixo |
| 6 | `GET` | `/api/openclaw/sellers/{id}/items/{itemId}` | read | Detalhe de anúncio |
| 7 | `GET` | `/api/openclaw/sellers/{id}/orders` | read | **Vendas por período** (date_from, status) |
| 8 | `GET` | `/api/openclaw/sellers/{id}/orders/{orderId}` | read | Detalhe do pedido |
| 9 | `POST` | `/api/openclaw/actions` | write | Executar ação (update_stock, etc.) |
| 10 | `GET` | `/api/openclaw/actions/{id}` | read | Status da ação |
| 11 | `GET` | `/api/openclaw/webhooks` | read | Listar webhooks registrados |
| 12 | `POST` | `/api/openclaw/webhooks` | write | **Registrar webhook** (push notifications) |
| 13 | `DELETE` | `/api/openclaw/webhooks/{id}` | write | Remover webhook |
| 14 | `POST` | `/api/openclaw/webhooks/{id}/test` | write | Testar webhook |
| 15 | `GET` | `/api/openclaw/webhook-events` | read | Tipos de evento para webhook |

---

*Gerado em 2026-02-24 — eskill.com.br OpenClaw Connector v1.0.0*
