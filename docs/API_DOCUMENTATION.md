# 📚 Documentação da API - Mercado Livre Manager

Documentação completa de todos os endpoints da API interna do sistema.

---

## 🔐 Autenticação

A maioria dos endpoints requer autenticação via sessão ou token. Alguns endpoints públicos não requerem autenticação.

---

## 📋 Endpoints de Autenticação

### `GET /auth/authorize`
Inicia o fluxo de autorização OAuth2 do Mercado Livre.

**Parâmetros:** Nenhum

**Resposta:** Redireciona para página de autorização do ML

**Exemplo:**
```
GET /eskill/public/auth/authorize
```

---

### `GET /auth/callback`
Callback OAuth2 - recebe código e troca por tokens.

**Parâmetros:**
- `code` (query) - Código de autorização
- `state` (query) - Estado de segurança

**Resposta:** Redireciona para dashboard com mensagem de sucesso/erro

---

### `GET /api/auth/accounts`
Lista todas as contas ML vinculadas ao usuário.

**Resposta:**
```json
[
  {
    "id": 1,
    "ml_user_id": "123456",
    "nickname": "usuario_ml",
    "email": "usuario@email.com",
    "status": "active",
    "token_expires_at": "2024-12-22 10:00:00",
    "created_at": "2024-12-15 10:00:00"
  }
]
```

---

## 📊 Endpoints de Dashboard

### `GET /api/dashboard/metrics`
Obtém métricas consolidadas do dashboard.

**Parâmetros:**
- `account_id` (opcional) - Filtrar por conta específica

**Resposta:**
```json
{
  "active_accounts": 3,
  "recent_orders": 45,
  "total_revenue": 12500.50,
  "orders_by_status": [
    {"status": "paid", "count": 20},
    {"status": "shipped", "count": 15}
  ],
  "expiring_tokens": 1
}
```

---

## 🗂️ Endpoints de Categorias

### `GET /api/categories`
Lista todas as categorias do site MLB.

**Resposta:**
```json
[
  {
    "id": "MLB1747",
    "name": "Acessórios para Veículos"
  }
]
```

---

### `GET /api/categories/tree`
Obtém árvore hierárquica de categorias.

**Resposta:** Array de categorias com `parent_id`

---

### `GET /api/categories/{id}`
Obtém detalhes de uma categoria específica.

**Parâmetros:**
- `id` (path) - ID da categoria

**Resposta:**
```json
{
  "id": "MLB431935",
  "name": "Peças para Motos e Quadriciclos",
  "total_items_in_this_category": 50000
}
```

---

### `GET /api/categories/{id}/brands`
Obtém marcas disponíveis para uma categoria.

**Parâmetros:**
- `id` (path) - ID da categoria

**Resposta:**
```json
[
  {"id": "9344", "name": "AWA"},
  {"id": "9345", "name": "Yamaha"}
]
```

---

### `GET /api/categories/{id}/subcategories`
Obtém subcategorias de uma categoria.

**Parâmetros:**
- `id` (path) - ID da categoria

---

### `GET /api/categories/search`
Busca categoria por nome.

**Parâmetros:**
- `q` (query) - Termo de busca

**Exemplo:**
```
GET /api/categories/search?q=motos
```

---

## 🔍 Endpoints de Busca

### `GET /api/search`
Busca itens com filtros avançados.

**Parâmetros:**
- `category` - ID da categoria
- `brand` - Nome da marca
- `condition` - Condição (new/used)
- `price_min` - Preço mínimo
- `price_max` - Preço máximo
- `free_shipping` - Frete grátis (1/0)
- `limit` - Limite de resultados (padrão: 50)
- `offset` - Offset para paginação
- `sort` - Ordenação (price_asc, price_desc, relevance)

**Exemplo:**
```
GET /api/search?category=MLB431935&brand=AWA&condition=new&price_min=100&price_max=500
```

**Resposta:**
```json
{
  "results": [...],
  "paging": {
    "total": 150,
    "offset": 0,
    "limit": 50
  }
}
```

---

### `GET /api/search/analyze`
Análise detalhada diferenciando catálogo vs comum.

**Parâmetros:**
- `category` - ID da categoria (obrigatório)
- `brand` - Nome da marca (obrigatório)
- `condition` - Condição (opcional)
- `price_min` - Preço mínimo (opcional)
- `price_max` - Preço máximo (opcional)
- `free_shipping` - Frete grátis (opcional)
- `listing_type` - Tipo (catalog/common) (opcional)

**Resposta:**
```json
{
  "total": 347,
  "catalog": {
    "count": 89,
    "items": [...]
  },
  "common": {
    "count": 258,
    "items": [...]
  },
  "prices": {
    "min": 15.90,
    "max": 2890.00,
    "avg": 245.67,
    "count": 347
  },
  "conditions": {
    "new": 312,
    "used": 35
  },
  "shipping": {
    "free": 198,
    "paid": 149
  }
}
```

---

## 📦 Endpoints de Pedidos

### `GET /api/orders`
Lista pedidos de uma conta específica.

**Parâmetros:**
- `account_id` (obrigatório) - ID da conta
- `status` - Filtrar por status
- `date_from` - Data inicial (formato: YYYY-MM-DD)
- `date_to` - Data final
- `limit` - Limite (padrão: 50)
- `offset` - Offset

---

### `GET /api/orders/all`
Lista pedidos de todas as contas vinculadas.

**Parâmetros:** Mesmos de `/api/orders` (exceto `account_id`)

---

### `GET /api/orders/{id}`
Obtém detalhes de um pedido específico.

**Parâmetros:**
- `id` (path) - ID do pedido

---

### `POST /api/orders/sync`
Sincroniza pedidos de uma conta.

**Parâmetros:**
- `account_id` (obrigatório) - ID da conta
- `limit` - Limite de pedidos (padrão: 100)

**Resposta:**
```json
{
  "synced": 45,
  "errors": 0,
  "total": 45
}
```

---

## 📤 Endpoints de Exportação

### `GET /api/export/analysis/csv`
Exporta análise para CSV.

**Parâmetros:**
- `category` - ID da categoria (obrigatório)
- `brand` - Nome da marca (obrigatório)

**Resposta:** Arquivo CSV para download

---

### `GET /api/export/analysis/json`
Exporta análise para JSON.

**Parâmetros:** Mesmos de CSV

**Resposta:** Arquivo JSON para download

---

## 🔔 Endpoints de Webhooks

### `POST /webhook/ml`
Recebe notificações do Mercado Livre.

**Body:**
```json
{
  "topic": "orders",
  "resource": "/orders/123456789",
  "user_id": "123456"
}
```

**Resposta:** `200 OK` com `{"status": "ok"}`

---

## ⚠️ Endpoints de Alertas

### `GET /api/alerts`
Lista alertas do sistema.

**Parâmetros:**
- `account_id` - Filtrar por conta
- `unread` - Apenas não lidos (1/0)
- `limit` - Limite (padrão: 50)

**Resposta:**
```json
[
  {
    "id": 1,
    "type": "token_expiring",
    "severity": "warning",
    "message": "Token da conta usuario_ml expira em breve",
    "data": {...},
    "read_at": null,
    "created_at": "2024-12-15 10:00:00"
  }
]
```

---

### `GET /api/alerts/count`
Conta alertas não lidos.

**Resposta:**
```json
{
  "count": 5
}
```

---

### `POST /api/alerts/{id}/read`
Marca alerta como lido.

**Resposta:**
```json
{
  "status": "ok"
}
```

---

### `POST /api/alerts/read-all`
Marca todos os alertas como lidos.

**Resposta:**
```json
{
  "status": "ok",
  "updated": 5
}
```

---

## 🏆 Endpoints de Concorrência

### `GET /api/competitors/analyze`
Analisa concorrência em uma categoria/marca.

**Parâmetros:**
- `category` - ID da categoria (obrigatório)
- `brand` - Nome da marca (obrigatório)
- `account_id` - ID da conta (opcional)

**Resposta:**
```json
{
  "total_sellers": 15,
  "sellers": [
    {
      "seller_id": "123456",
      "nickname": "vendedor1",
      "items": 25,
      "total_sales": 150,
      "avg_price": 245.50,
      "min_price": 100.00,
      "max_price": 500.00
    }
  ],
  "market_avg_price": 245.67,
  "market_min_price": 15.90,
  "market_max_price": 2890.00
}
```

---

### `GET /api/competitors/opportunities`
Detecta oportunidades de mercado.

**Parâmetros:** Mesmos de `/api/competitors/analyze`

**Resposta:**
```json
{
  "opportunities": [
    {
      "type": "low_competition",
      "message": "Baixa concorrência detectada: apenas 3 vendedores",
      "severity": "success"
    }
  ],
  "competition": {...}
}
```

---

## 💡 Endpoints de Oportunidades

### `GET /api/opportunities/products-without-catalog`
Detecta produtos sem catálogo.

**Parâmetros:**
- `category` - ID da categoria (obrigatório)
- `brand` - Nome da marca (obrigatório)

---

### `GET /api/opportunities/low-competition`
Detecta categorias com pouca concorrência.

**Parâmetros:**
- `parent_category` - ID da categoria pai (opcional)

---

### `GET /api/opportunities/best-sellers`
Detecta produtos mais vendidos sem anúncio do usuário.

**Parâmetros:**
- `category` - ID da categoria (obrigatório)
- `brand` - Nome da marca (obrigatório)
- `account_id` - ID da conta (obrigatório)

---

## 📈 Endpoints de Histórico de Preços

### `POST /api/price-history/record`
Registra histórico de preços.

**Parâmetros:**
- `category` - ID da categoria (obrigatório)
- `brand` - Nome da marca (obrigatório)

**Resposta:**
```json
{
  "success": true
}
```

---

### `GET /api/price-history`
Obtém histórico de preços.

**Parâmetros:**
- `category` - ID da categoria (obrigatório)
- `brand` - Nome da marca (obrigatório)
- `days` - Período em dias (padrão: 30)

**Resposta:**
```json
[
  {
    "id": 1,
    "category_id": "MLB431935",
    "brand": "AWA",
    "avg_price": 245.67,
    "min_price": 15.90,
    "max_price": 2890.00,
    "total_items": 347,
    "recorded_at": "2024-12-15 10:00:00"
  }
]
```

---

### `GET /api/price-history/trend`
Analisa tendência de preços.

**Parâmetros:**
- `category` - ID da categoria (obrigatório)
- `brand` - Nome da marca (obrigatório)

**Resposta:**
```json
{
  "trend": "increasing",
  "change": 25.50,
  "change_percent": 10.5,
  "first_price": 245.67,
  "last_price": 271.17,
  "data_points": 30
}
```

**Valores de `trend`:**
- `increasing` - Preços aumentando (>5%)
- `decreasing` - Preços diminuindo (<-5%)
- `stable` - Preços estáveis
- `insufficient_data` - Dados insuficientes

---

## 📋 Endpoints de Auditoria

### `GET /api/audit`
Lista logs de auditoria.

**Parâmetros:**
- `user_id` - Filtrar por usuário
- `account_id` - Filtrar por conta
- `action` - Filtrar por ação
- `date_from` - Data inicial
- `date_to` - Data final
- `limit` - Limite (padrão: 100)

**Resposta:**
```json
[
  {
    "id": 1,
    "user_id": 1,
    "ml_account_id": 1,
    "action": "account_linked",
    "ip_address": "192.168.1.1",
    "user_agent": "Mozilla/5.0...",
    "data": {...},
    "created_at": "2024-12-15 10:00:00"
  }
]
```

---

## 🚦 Rate Limiting

A maioria dos endpoints tem rate limiting de **100 requisições por minuto por IP**.

Quando o limite é excedido:
- **Status:** `429 Too Many Requests`
- **Header:** `Retry-After: 60`
- **Body:**
```json
{
  "error": "Muitas requisições. Tente novamente mais tarde.",
  "code": "RATE_LIMIT_EXCEEDED",
  "retry_after": 60
}
```

---

## 🔒 Segurança

### CSRF Protection

Endpoints POST/PUT/DELETE requerem token CSRF:

**Header:**
```
X-CSRF-Token: seu_token_aqui
```

**Ou no formulário:**
```html
<input type="hidden" name="_token" value="seu_token_aqui">
```

---

## 📝 Códigos de Status HTTP

- `200` - Sucesso
- `400` - Requisição inválida (parâmetros faltando)
- `403` - Token CSRF inválido
- `404` - Recurso não encontrado
- `429` - Rate limit excedido
- `500` - Erro interno do servidor

---

## 🔗 Base URL

Todas as URLs são relativas a:
```
http://localhost/eskill/public
```

Em produção, substitua pelo seu domínio.

---

**Última atualização:** 15 de Dezembro de 2024

