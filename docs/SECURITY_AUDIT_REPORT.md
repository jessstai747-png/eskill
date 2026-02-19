# Relatório de Auditoria de Segurança e Qualidade

**Data**: 2026-02-15  
**Escopo**: `app/`, `public/index.php`, `config/`  
**PHP**: 8.4.15 | **MySQL** | **Nginx** (CloudPanel)

---

## A) Relatório de Achados

### CRÍTICO (P0) — Corrigir IMEDIATAMENTE

| # | Achado | Arquivo:Linha | Impacto | Status |
|---|--------|--------------|---------|--------|
| C1 | **Open Redirect** no login — `$redirect = $_SESSION['redirect_after_login']` usado diretamente em `header('Location: ' . $redirect)` sem validação | `AuthController.php:138-141` | Phishing: atacante injeta URL externa na sessão | ✅ DONE |
| C2 | **`unserialize()` em dados de arquivo** — `AdvancedCacheService` desserializa arquivos de cache sem `allowed_classes` | `AdvancedCacheService.php:359,393,415` | RCE via object injection se cache for comprometido | ✅ DONE |
| C3 | **SSL verification desabilitada** em produção — `CURLOPT_SSL_VERIFYPEER => false` | `NotificationService.php:282-283`, `AIImageAnalyzerService.php:822` | MITM: dados interceptáveis em produção | ✅ DONE |

### ALTO (P1) — Corrigir esta semana

| # | Achado | Arquivo:Linha | Impacto | Status |
|---|--------|--------------|---------|--------|
| A1 | **Rate limit usa apenas `REMOTE_ADDR`** — proxy headers não consultados, fácil de bypassar atrás de CDN/load balancer | `RateLimitMiddleware.php:35` | DDoS/brute force facilitado | ✅ DONE |
| A2 | **Rate limit armazena em DB** — cada request faz INSERT + possível DELETE. Sob ataque, amplifica a carga no banco | `RateLimitMiddleware.php:85-93` | Auto-DoS: banco saturado pelo próprio rate limiter | 📝 NOTA |
| A3 | **Router vaza path na 404 JSON** — `echo json_encode(['error' => ..., 'path' => $path])` | `Router.php:182` | Info disclosure: revela estrutura de rotas | ✅ DONE |
| A4 | **Controllers usam `$_GET`/`$_POST` diretamente** em ~50+ locais ao invés de `$this->request->get()` | Múltiplos controllers | Bypass da sanitização centralizada do Request | 📝 MIGRAÇÃO |
| A5 | **`shell_exec()` em services** — `ErrorMonitoringService::tail()`, `AdvancedMonitoringService` | `ErrorMonitoringService.php:219`, `AdvancedMonitoringService.php:482` | RCE se filepath manipulável (mitigado por escapeshellarg em tail) | 📝 NOTA |
| A6 | **Remember-me cookie sem sanitização `SameSite`** — `setcookie()` sem param SameSite explícito | `AuthController.php:133` | CSRF via cookie fixation em browsers antigos | ✅ DONE |

### MÉDIO (P2) — Corrigir em 2 semanas

| # | Achado | Arquivo:Linha | Impacto | Status |
|---|--------|--------------|---------|--------|
| M1 | **SQL dinâmico em `ItemService::updateItemPricing()`** — `implode(', ', $fields) . " WHERE " . $where` | `ItemService.php:616` | Injeção se `$fields` contiver user-controlled keys (mitigado: whitelist implícita) | 📝 REVISAR |
| M2 | **Exception messages expostas na 500** — Router catch expõe `$e->getMessage()` em JSON | `Router.php:154` | Info disclosure: paths, classes, mensagens internas | ✅ DONE |
| M3 | **`WebhookService` tem opção `insecure`** que desabilita SSL verify | `Webhooks/WebhookService.php:102` | MITM condicional | 📝 NOTA |
| M4 | **Inconsistência JSON response** — alguns controllers retornam `{error: ...}`, outros `{success, data, message}` | Múltiplos controllers | DX ruim, dificuldade de error handling no frontend | 📝 MIGRAÇÃO |

### BAIXO (P3) — Backlog

| # | Achado | Arquivo:Linha | Impacto | Status |
|---|--------|--------------|---------|--------|
| B1 | **CSRF token não rotaciona por request** — 1 token por hora, reutilizável | `public/index.php:108-111` | Token leaking por referrer/log tem janela de 1h | 📝 NOTA |
| B2 | **`X-XSS-Protection` é deprecated** — browsers modernos removeram suporte | `SecurityMiddleware.php:161` | Header desnecessário (CSP cobre) | ✅ DONE |
| B3 | **CSP permite `connect-src https:`** via CDN — amplo demais | `SecurityMiddleware.php:176` | Exfiltração via connect-src | 📝 NOTA |

---

## Pontos POSITIVOS encontrados

| Item | Evidência |
|------|-----------|
| ✅ Sessão segura | `HttpOnly`, `Secure`, `SameSite=Lax`, `strict_mode`, `use_only_cookies` |
| ✅ `session_regenerate_id(true)` no login | `AuthController.php:92` |
| ✅ CSRF middleware global | `public/index.php:177-183` com exceção justificada para Bearer tokens |
| ✅ PDO com `EMULATE_PREPARES=false` e `ERRMODE_EXCEPTION` | `Database.php:42-44`, `config/database.php:37-39` |
| ✅ CSP com nonce | `SecurityMiddleware.php:168-176` |
| ✅ Request class com sanitização | `app/Core/Request.php` — `sanitizeString()`, `getEnum()`, `getIntClamped()` |
| ✅ SecurityMiddleware robusto | Attack patterns, suspicious agents, IP blocking, HSTS |
| ✅ `DatabaseMigrationTrait` sanitiza table/column | `preg_replace('/[^a-zA-Z0-9_]/', '')` |
| ✅ `exec()` com `escapeshellarg()` | `OpenSpecController.php:237`, `SyncController.php:150` |
| ✅ File upload MIME validation | `Request::file()` usa `finfo` para validar MIME real |

---

## B) Plano de Execução

### Onda 1 — Crítico + Alto (hoje)
1. **C1**: Validar redirect no login (whitelist de paths internos)
2. **C2**: Adicionar `allowed_classes: false` ao `unserialize()`
3. **C3**: Habilitar SSL verification (ou condicional por env)
4. **A1**: Rate limit usando IP real (mesmo helper do SecurityMiddleware)
5. **A3**: Remover path do 404 JSON
6. **A6**: Remember-me cookie com SameSite
7. **M2**: Sanitizar exception messages em produção

### Onda 2 — Consistência (em andamento)
1. **M4**: Migrar controllers restantes para `$this->request->get()`
	- ✅ `CategoryController::search()` migrado de `$_GET['q']` para `Request::get('q')`
	- ✅ `TrendsController` migrado (`limit`, `category_id`, `months`, `min_volume`, `days`) para `Request`
	- ✅ `DynamicPricingController::demandBasedPricing()` migrado de `$_GET['days']` para `Request::getInt()`
	- ✅ `ListingBuilderController::listTemplates()` migrado de `$_GET['category_id']` para `Request::get()`
	- ✅ `SeoSynonymsController` migrado (`title`, `categoryId`, `word`) de `$_POST` para `Request::post()`
	- ✅ `AuthMonitorApiController` migrado (`active_only`, `limit`, `offset`, `ip`, `since`) para `Request`
	- ✅ `QualityController::getDashboardItems()` migrado (`page`, `per_page`, `min_score`, `max_score`, `status`) para `Request`
	- ✅ `ShippingController` migrado (`zip_code`, `include_full`, `target_margin`) para `Request`
	- ✅ `TokenDashboardController` migrado (`status`, `sort`, `order`, `mode`, `limit`, `action`, `period`) para `Request`
	- ✅ `MessagingController` migrado (`limit`, `offset`, `category`, `start_date`, `end_date`) para `Request`
	- ✅ `CompetitorController` migrado (`category`, `brand`, `account_id`) para `Request`
	- ✅ `CloneAutomationController` migrado (`status`, `trigger_type`, `limit`) para `Request`
	- ✅ `SEOToolsController` migrado (`account_id`, `keyword`) para `Request`
	- ✅ `BackupController::clean()` migrado (`days`) para `Request`
	- ✅ `PromotionController::detail()` migrado (`id`) removendo acesso direto a `$_GET`
	- ✅ `CompetitorMonitorController::getAlerts()` migrado (`limit`) para `Request`
	- ✅ `AgentController::listLogs()` migrado (`code`) para `Request`
	- ✅ `ChatbotAIController::getStats()` migrado (`days`) para `Request`
	- ✅ `RealTimeNotificationController::unread()` migrado (`type`, `limit`) para `Request`
	- ✅ `CloneNotificationController::getHistory()` migrado (`limit`, `event`) para `Request`
	- ✅ `InventoryAdvancedController::getMovements()` migrado (`type`, `limit`) para `Request`
	- ✅ `SettingsController` migrado (`account_id`) para `Request`
	- ✅ `AccountHealthController::getHistory()` migrado (`days`) para `Request`
	- ✅ `StatisticsController` migrado (`start_date`, `end_date`, `limit`) para `Request`
	- ✅ `AdsController::getProducts()` migrado (`limit`) para `Request`
	- ✅ `MessageController::index()` migrado (`account_id`) para `Request`
	- ✅ `CacheController`, `CloneEventTriggerController`, `BrandCentralController`, `AlertController`, `ExportController` migrados para `Request`/`filter_input_array`
	- ✅ `CloneSellerRecommendationController`, `MultiAccountController`, `CloneAdvancedController`, `SyncController`, `CloneABTestingController` migrados para `Request`
	- ✅ `AIController`, `AIMLController`, `SeoDescriptionController`, `Api/SeoStrategiesController`, `MarketDataController` migrados para `Request`/`filter_input_array`
	- ✅ Restante: **0 ocorrências** de acesso direto a `$_GET`/`$_POST` em `app/Controllers/**`
2. **B2**: Remover `X-XSS-Protection` deprecated ✅

### Onda 3 — Testes ✅
1. ✅ `tests/Unit/SecurityPatchesTest.php` — 11 testes (C1 redirect, C2 unserialize, A1 IP, A3/M2 info leak, A6 cookie, B2 header, SSL verify)
2. ✅ `tests/Unit/SEOKillerEngineTest.php` — 6 testes (analyzeTitles, DIAGNOSIS_WEIGHTS)
3. ✅ `tests/Unit/KeywordKillerTest.php` — 16 testes (extractBaseKeywords, isStopword, extractModifiers, generateBuyingIntentKeywords, generateLongTail, classifyIntent)
4. ✅ `tests/Unit/RequestTest.php` — 20 testes (XSS sanitization, getInt, getBool, post, postArray, validateRequired, getEnum, getIntClamped, getSortDir, isAjax)
5. ✅ `tests/Unit/Services/PricingStrategyServiceTest.php` — pré-existente (median, percentile, stddev, suggested price, margin)
6. ✅ `tests/Unit/Services/ListingBuilderServiceTest.php` — pré-existente (instanciação, métodos, templates, return types)

---

## D) Checklist de Produção

- [x] HTTPS forçado + HSTS preload
- [x] display_errors = 0 em produção
- [x] CSP com nonce (strict-dynamic)
- [x] HttpOnly + Secure + SameSite cookies
- [x] CSRF global em mutating requests
- [x] PDO ERRMODE_EXCEPTION + EMULATE_PREPARES=false
- [x] Rate limiting global
- [x] IP blocking / attack pattern detection
- [x] Security headers (X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, COOP, CORP)
- [x] **FIX**: Open redirect validation (C1) — `AuthController.php:138-141` → regex + scheme check
- [x] **FIX**: unserialize allowed_classes (C2) — `AdvancedCacheService.php:359,393,415` → `['allowed_classes' => false]`
- [x] **FIX**: SSL verification em produção (C3) — `NotificationService.php`, `AIImageAnalyzerService.php` → condicional por APP_ENV
- [x] **FIX**: Rate limit IP via proxy headers (A1) — `RateLimitMiddleware.php` → `getClientIp()` com CF/proxy headers
- [x] **FIX**: Remover path de 404 response (A3) — `Router.php:182` → removido path/method do JSON
- [x] **FIX**: Remember-me SameSite (A6) — `AuthController.php:133` → array-form setcookie com `SameSite=Lax`
- [x] **FIX**: Exception sanitization prod (M2) — `Router.php:154,163` → mensagens ocultas em produção
- [x] **FIX**: Remover X-XSS-Protection deprecated (B2) — `SecurityMiddleware.php:161` → removido
### Onda 4 — Hardening Final (2026-02-15)

#### 1. Rate Limit Duplicado Removido
- **Problema**: `public/index.php` aplica `RateLimitMiddleware` E `SecurityMiddleware` também tinha rate limit interno → 429 duplicado + custo extra de DB
- **Solução**: Flag `SECURITY_MW_RATE_LIMIT_ENABLED` (default: `false`) em `SecurityMiddleware.php:64-72`
- **Impacto**: Zero risco de 429 duplicado, latência otimizada
- **Commits**: ✅ `app/Middleware/SecurityMiddleware.php`

#### 2. ExceptionHandler Diferencia API vs HTML
- **Problema**: Handler sempre retornava JSON, quebrando UX em rotas HTML/views
- **Solução**: 
  - Método `wantsJson()` detecta `/api/*`, `X-Requested-With: XMLHttpRequest`, ou `Accept: application/json`
  - JSON apenas para requisições API/AJAX
  - HTML seguro para views via `app/Views/errors/500.php`
  - Fallback text/plain sem detalhes em produção
- **Testes**: ✅ `tests/Unit/ExceptionHandlerTest.php` — 2 cenários (API path, HTML request)
- **Commits**: ✅ `app/Core/ExceptionHandler.php`

#### 3. Drift de Headers Reduzido
- **Problema**: `SecurityHeadersMiddleware` e `SecurityMiddleware` duplicam headers → manutenção difícil
- **Solução**: Flag `SECURITY_HEADERS_LEGACY_ENABLED` (default: `false`) desabilita legacy middleware
- **Fonte única**: `SecurityMiddleware` aplicado em `public/index.php:147`
- **Commits**: ✅ `app/Middleware/SecurityHeadersMiddleware.php`

#### 4. Method Reference Fixes
- **Problema**: Chamadas de métodos inexistentes detectadas por análise estática
- **Fixes**:
  - `CloneAdvancedController::updateSeoSettings()` → `saveSettings()` → `updateSettings()` (método real do serviço)
  - `SettingsController::mlDiagnostico()` → `MercadoLivreClient::listAccounts()` → `SessionHelper::getUserAccounts()`
- **Validação**: Codacy clean, 0 erros estáticos
- **Commits**: ✅ `app/Controllers/CloneAdvancedController.php`, ✅ `app/Controllers/SettingsController.php`

---

## E) Próximos Passos (Backlog)