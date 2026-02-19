# API Documentation

Professional API for the Mercado Livre Manager — real-time database operations, SEO optimization, analytics intelligence, health monitoring, and marketplace management.

## Base URL

```
https://eskill.com.br/api
```

## Authentication

All API requests require a Bearer token in the Authorization header:

```
Authorization: Bearer <your-token-here>
```

## Rate Limiting

- 100 requests per hour per user
- Exceeding the limit returns HTTP 429 (Too Many Requests)

---

## Real-Time Database API (CRUD)

### GET /api/v1/{table}

Retrieve records with optional filtering, sorting, and pagination.

**Parameters:** `page`, `limit` (max 1000), `order_by`, `order_dir` (ASC/DESC), `filters[field]=value`

### GET /api/v1/{table}/{id}

Retrieve a single record by ID.

### POST /api/v1/{table}

Create a new record.

### PUT /api/v1/{table}/{id}

Update an existing record.

### DELETE /api/v1/{table}/{id}

Delete a record by ID.

---

## Health Check API

### GET /api/health

Full system health check (database, cache, memory, disk).

### GET /api/health/live

Liveness probe — returns `{"status": "alive"}`.

### GET /api/health/ready

Readiness probe — verifies database connectivity.

### GET /api/health/ml

Mercado Livre API health — checks credentials and API reachability.

### GET /api/health/integrations

**NEW** — Integration dependencies check. Verifies:

- **GD Library**: Image processing for AIImageAnalyzerService
- **Tesseract OCR**: Text extraction from product images
- **OpenAI API**: AI-powered optimization features
- **Database Tables**: Existence of critical tables (items, ml_orders, order_items, ml_questions, seo_performance_metrics, competitor_watchlist)
- **ML API**: Mercado Livre API connectivity
- **Storage**: Writable cache/logs directories

**Response:**

```json
{
  "status": "healthy|degraded|unhealthy",
  "timestamp": "2026-02-16T10:00:00-03:00",
  "checks": {
    "gd_library": { "status": "ok", "message": "GD extension loaded" },
    "tesseract_ocr": { "status": "ok", "message": "Tesseract found" },
    "openai_api": { "status": "ok", "message": "OpenAI API key configured" },
    "database_tables": { "status": "ok", "required": [...], "missing": [] },
    "ml_api": { "status": "ok", "message": "ML API reachable" },
    "storage": { "status": "ok", "message": "All storage directories writable" }
  }
}
```

---

## SEO API

### Phase 1: Synonyms & Semantics

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/seo/synonyms/{categoryId}` | Synonym hierarchy for a category |
| POST | `/api/seo/synonyms/expand` | Expand keyword synonyms |
| POST | `/api/seo/synonyms/model` | Generate semantic model |
| POST | `/api/seo/score/calculate` | Calculate SEO score |
| GET | `/api/seo/contexts/{categoryId}` | Get category contexts |

### Phase 2: Keyword Distribution

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/seo/keywords/distribute` | Distribute keywords across listings |
| POST | `/api/seo/keywords/classify` | Classify keyword intent |
| GET | `/api/seo/keywords/fetch/{categoryId}` | Fetch category keywords |
| POST | `/api/seo/keywords/generate/{categoryId}` | AI-generate keywords |
| POST | `/api/seo/density/validate` | Validate keyword density |
| POST | `/api/seo/density/calculate` | Calculate density |
| GET | `/api/seo/weights` | Get keyword weight configuration |

### Keyword Mining (ML API)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/seo/keywords/mine/{categoryId}` | Mine keywords from ML trends |
| GET | `/api/seo/keywords/attributes/{categoryId}` | Extract attribute-based keywords |
| GET | `/api/seo/keywords/discover` | Discover new keyword opportunities |
| POST | `/api/seo/keywords/suggest-title` | AI title suggestion |

### Phase 3: Description Builder

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/seo/description/build` | Build optimized description |
| POST | `/api/seo/description/block` | Generate content block |
| POST | `/api/seo/description/faq` | Generate FAQ section |
| POST | `/api/seo/description/validate` | Validate description quality |
| POST | `/api/seo/longtail/generate` | Generate long-tail keywords |

### Phase 4: Hidden Fields & Coverage

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/seo/hidden-fields/{itemId}` | Detect hidden attribute fields |
| POST | `/api/seo/hidden-fields/generate` | Generate hidden field values |
| POST | `/api/seo/hidden-fields/apply` | Apply values to listing |
| GET | `/api/seo/coverage/{itemId}` | Full coverage analysis |
| GET | `/api/seo/coverage/gaps/{itemId}` | Identify coverage gaps |

### Phase 5: Strategies & Monitoring

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/seo/strategies/optimize/full/{itemId}` | Full SEO optimization |
| POST | `/api/seo/strategies/optimize/partial/{itemId}` | Partial optimization |
| GET | `/api/seo/strategies/preview/{itemId}` | Preview changes |
| POST | `/api/seo/strategies/apply/{itemId}` | Apply optimization |
| GET | `/api/seo/strategies/score/{itemId}` | Get SEO score |
| GET | `/api/seo/strategies/history/{itemId}` | Optimization history |
| POST | `/api/seo/monitoring/schedule/{itemId}` | Schedule monitoring |
| GET | `/api/seo/monitoring/metrics/{itemId}` | Get performance metrics |

### SEO Killer

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/seo-killer/title` | AI title generation |
| POST | `/api/seo-killer/description` | AI description generation |
| POST | `/api/seo-killer/optimize` | Full item optimization |
| POST | `/api/seo-killer/attributes` | Auto-fill attributes |
| GET | `/api/seo-killer/diagnose` | SEO diagnosis |
| GET | `/api/seo-killer/report` | Completeness report |

---

## Market Data API

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/market/analyze/{categoryId}` | Full market analysis |
| GET | `/api/market/pricing/{categoryId}` | Pricing analysis |
| GET | `/api/market/competitors/{categoryId}` | Competitor analysis |
| GET | `/api/market/trends/{categoryId}` | Category trends |
| GET | `/api/market/quality/{itemId}` | Listing quality analysis |
| POST | `/api/market/suggest-price` | AI price suggestion |
| GET | `/api/market/search` | Market search |
| GET | `/api/market/item/{itemId}` | Item details |
| GET | `/api/market/attributes/{categoryId}` | Category attributes |

---

## Response Format

**Success:**

```json
{
  "success": true,
  "data": { ... },
  "message": "Operation completed"
}
```

**List with pagination:**

```json
{
  "data": [ ... ],
  "pagination": {
    "current_page": 1,
    "per_page": 50,
    "total": 123,
    "total_pages": 3
  }
}
```

**Error:**

```json
{
  "success": false,
  "error": "Error message"
}
```

## Allowed Tables (CRUD API)

- users
- items
- orders
- products
- categories
- ml_accounts
- account_health_history
- seo_analysis_cache
- notifications
- settings
- logs
- analytics

## Status Codes

- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `404` - Not Found
- `429` - Too Many Requests
- `500` - Internal Server Error
- `503` - Service Unavailable (health check failed)

## Security

- Input validation and sanitization
- SQL injection prevention through prepared statements
- Authentication required for all operations
- Rate limiting to prevent abuse
- CSRF protection on form endpoints
- Structured logging for audit trails