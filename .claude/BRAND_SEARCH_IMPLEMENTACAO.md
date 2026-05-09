# eskill.com.br — Módulo 20: Brand Search
## Documento técnico de implementação completa

> **Produto:** eskill.com.br  
> **Empresa:** AWA Motos — Distribuidora de peças para motos (Araraquara, SP)  
> **Módulo PRD:** Módulo 20 — BRAND-003: Brand Search  
> **Stack:** PHP 8.0+ | MySQL/PDO | Redis | Guzzle 7 | Monolog 3 | PHPUnit 9  
> **Estimativa total:** 4 a 6 dias  
> **Versão:** 1.0 — 2026-04-06  

---

## Índice

1. [Visão geral do módulo](#1-visão-geral-do-módulo)
2. [Arquitetura e fluxo completo](#2-arquitetura-e-fluxo-completo)
3. [Banco de dados — migrations](#3-banco-de-dados--migrations)
4. [Plano de implementação por fases](#4-plano-de-implementação-por-fases)
   - [Fase 1 — Backend](#fase-1--backend-1-2-dias)
   - [Fase 2 — Worker](#fase-2--worker-1-dia)
   - [Fase 3 — Frontend](#fase-3--frontend-1-2-dias)
   - [Fase 4 — Testes](#fase-4--testes-e-homologação-1-dia)
5. [Checklist de qualidade](#5-checklist-de-qualidade)
6. [Resumo de todos os arquivos](#6-resumo-de-todos-os-arquivos)

---

## 1. Visão geral do módulo

O módulo **Brand Search** (BRAND-003) permite mapear, em tempo real, todas as lojas ativas no Mercado Livre que anunciam produtos de uma marca específica — como a AWA Motos. A busca é feita via API pública do ML usando o `BRAND ID` da marca como filtro, paginando automaticamente por categorias para superar o limite de 1.000 resultados por requisição.

O resultado final é uma tabela completa de vendedores com: nome da loja (nickname), nível de reputação, total de anúncios da marca, preço médio praticado e tendência de crescimento.

### 1.1 Contexto de negócio

| Campo | Valor |
|---|---|
| Marca alvo | AWA |
| Brand ID no ML | `7297804` |
| Site | `MLB` (Mercado Livre Brasil) |
| Total estimado de anúncios | 60.454 anúncios ativos (abril/2026) |
| URL de referência | `https://lista.mercadolivre.com.br/acessorios-veiculos/retrovisor_BRAND_7297804_NoIndex_True` |
| Objetivo principal | Mapear todos os vendedores que comercializam produtos AWA |
| Objetivo secundário | Alimentar o Módulo 07 (Análise de Concorrentes) com dados reais |

### 1.2 Limitação técnica — paginação obrigatória por categoria

> ⚠️ **IMPORTANTE:** A API do ML retorna no máximo 1.000 resultados por busca (offset máximo = 950, limit = 50). Para cobrir os 60k+ anúncios da AWA, a coleta deve ser quebrada por categoria. Cada combinação `BRAND + category` retorna até 1.000 resultados independentes.

**Estratégia de coleta:**

```
# Passo 1: descobrir categorias disponíveis para a marca
GET /sites/MLB/search?BRAND=7297804&limit=1
→ Ler available_filters[id=category].values[]

# Passo 2: para cada categoria, paginar completamente
GET /sites/MLB/search?BRAND=7297804&category=MLB1747&limit=50&offset=0
GET /sites/MLB/search?BRAND=7297804&category=MLB1747&limit=50&offset=50
... (até atingir o total da categoria ou offset=950)

# Passo 3: repetir para cada categoria retornada no passo 1
GET /sites/MLB/search?BRAND=7297804&category=MLB5726&limit=50&offset=0
...
```

---

## 2. Arquitetura e fluxo completo

### 2.1 Padrão arquitetural do projeto

O projeto usa MVC customizado. **Nunca criar padrões novos**. Seguir exatamente:

```
HTTP Request
    │
    ▼
Router (app/Router.php)
    │
    ▼
Middleware Pipeline (Auth, CSRF, RateLimit)
    │
    ▼
Controller (app/Controllers/)
    │
    ▼
Service (app/Services/MercadoLivre/)
    │
    ├── Model (app/Models/) → MySQL/PDO
    └── Guzzle 7 → api.mercadolibre.com
```

### 2.2 Fluxo de dados completo — passo a passo

```
1. Usuário clica "Buscar" na tela
   └─> POST /api/brand-search/start
         └─> BrandSearchController::start()
               └─> BrandSearchService::initSearch()
                     └─> INSERT brand_searches (status=pending)
                     └─> Retorna {search_id: 42} — HTTP 202 imediato

2. brand-search-worker.php (processo background)
   └─> Seleciona brand_searches WHERE status='pending'
         └─> BrandSearchService::executeSearch(42)
               └─> Passo A: GET /sites/MLB/search?BRAND=7297804&limit=1
                     └─> Extrai available_filters → lista de categorias
               └─> Passo B: Para cada categoria:
                     └─> Pagina até total (máx offset=950)
                     └─> Coleta seller_id únicos de cada item
                     └─> UPDATE brand_searches SET progress = X
               └─> Passo C: Para cada seller_id único:
                     └─> GET /users/{seller_id}
                     └─> INSERT brand_sellers
               └─> UPDATE brand_searches SET status='completed'

3. Frontend — polling a cada 2 segundos
   └─> GET /api/brand-search/42/progress
         └─> Retorna {progress: 67, status: "running"}
   └─> Quando status=completed:
         └─> GET /api/brand-search/42/sellers
               └─> Renderiza tabela de resultados
```

### 2.3 Endpoints da API

| Método | Endpoint | Retorno | Descrição |
|---|---|---|---|
| `POST` | `/api/brand-search/start` | JSON | Inicia busca, retorna `search_id` |
| `GET` | `/api/brand-search/{id}/progress` | JSON | Progresso 0-100 + status |
| `GET` | `/api/brand-search/{id}/sellers` | JSON | Lista paginada de vendedores |
| `GET` | `/api/brand-search/{id}/items/{seller_id}` | JSON | Anúncios de um vendedor |
| `GET` | `/api/brand-search/{id}/export` | CSV | Export completo |
| `GET` | `/brand-search` | HTML | View da tela |

**Parâmetros aceitos em `GET /sellers`:**

```
?reputation=platinum|gold|silver|new   → filtro por nível
?min_items=100                          → mínimo de anúncios da marca
?sort=total_items|reputation|avg_price  → ordenação
?order=desc|asc
?page=1&per_page=20
```

---

## 3. Banco de dados — migrations

> ✅ As 3 migrations e o runner já foram gerados. Apenas copiar para `database/migrations/` e executar.

### 3.1 Executar as migrations

```bash
# Sempre fazer dry-run primeiro
php bin/migrate-brand-search.php --dry-run

# Aplicar as 3 tabelas
php bin/migrate-brand-search.php

# Desfazer se necessário
php bin/migrate-brand-search.php --rollback
```

### 3.2 Tabela `brand_searches`

Registra cada busca iniciada. Uma busca = uma execução do worker para uma marca/site.

```sql
CREATE TABLE `brand_searches` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `account_id`      BIGINT UNSIGNED NOT NULL,         -- conta ML (multi-conta)
    `brand_id`        VARCHAR(20)     NOT NULL,         -- ex: 7297804
    `brand_name`      VARCHAR(100)    NOT NULL,         -- ex: AWA
    `site_id`         VARCHAR(10)     NOT NULL DEFAULT 'MLB',
    `category_id`     VARCHAR(20)     NULL,             -- NULL = todas as categorias
    `status`          ENUM('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
    `total_items`     INT UNSIGNED    NOT NULL DEFAULT 0,
    `total_sellers`   INT UNSIGNED    NOT NULL DEFAULT 0,
    `progress`        TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- 0-100
    `error_message`   TEXT            NULL,
    `started_at`      DATETIME        NULL,
    `completed_at`    DATETIME        NULL,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_account_brand` (`account_id`, `brand_id`),
    INDEX `idx_status`        (`status`),
    INDEX `idx_created_at`    (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.3 Tabela `brand_sellers`

Vendedores únicos por busca. Chave única: `(search_id, seller_id)`.

```sql
CREATE TABLE `brand_sellers` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `search_id`           BIGINT UNSIGNED NOT NULL,       -- FK → brand_searches.id
    `seller_id`           BIGINT UNSIGNED NOT NULL,       -- ID numérico do vendedor no ML
    `nickname`            VARCHAR(100)    NOT NULL,       -- nome público da loja
    `seller_type`         VARCHAR(30)     NULL,
    `permalink`           VARCHAR(255)    NULL,
    `reputation_level`    VARCHAR(20)     NULL,           -- platinum|gold|silver|bronze|new
    `reputation_score`    TINYINT UNSIGNED NULL,          -- 0-100 calculado
    `power_seller_status` VARCHAR(20)     NULL,           -- gold|gold_special|null
    `total_items_brand`   INT UNSIGNED    NOT NULL DEFAULT 0,
    `avg_price`           DECIMAL(12,2)   NULL,
    `site_status`         VARCHAR(20)     NULL,           -- active|paused|suspended
    `country_id`          CHAR(2)         NULL DEFAULT 'BR',
    `city`                VARCHAR(100)    NULL,
    `state`               VARCHAR(50)     NULL,
    `trend`               ENUM('up','down','stable') NOT NULL DEFAULT 'stable',
    `last_synced_at`      DATETIME        NULL,
    `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_search_seller` (`search_id`, `seller_id`),
    INDEX `idx_seller_id`         (`seller_id`),
    INDEX `idx_reputation`        (`reputation_level`),
    INDEX `idx_total_items`       (`total_items_brand`),
    CONSTRAINT `fk_bs_search`
        FOREIGN KEY (`search_id`) REFERENCES `brand_searches` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.4 Tabela `brand_items`

Anúncios individuais coletados. Chave única: `(search_id, item_id)`.

```sql
CREATE TABLE `brand_items` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `search_id`     BIGINT UNSIGNED NOT NULL,       -- FK → brand_searches.id
    `seller_id`     BIGINT UNSIGNED NOT NULL,       -- referência (sem FK rígida)
    `item_id`       VARCHAR(20)     NOT NULL,       -- ex: MLB123456
    `title`         VARCHAR(255)    NOT NULL,
    `category_id`   VARCHAR(20)     NULL,
    `category_name` VARCHAR(100)    NULL,
    `price`         DECIMAL(12,2)   NULL,
    `currency_id`   CHAR(3)         NULL DEFAULT 'BRL',
    `condition`     ENUM('new','used','not_specified') NOT NULL DEFAULT 'new',
    `listing_type`  VARCHAR(30)     NULL,           -- gold_pro|gold_special|gold
    `permalink`     VARCHAR(500)    NULL,
    `thumbnail`     VARCHAR(500)    NULL,
    `available_qty` INT UNSIGNED    NULL,
    `status`        VARCHAR(20)     NULL DEFAULT 'active',
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_search_item` (`search_id`, `item_id`),
    INDEX `idx_search_id`       (`search_id`),
    INDEX `idx_seller_id`       (`seller_id`),
    INDEX `idx_category`        (`category_id`),
    INDEX `idx_price`           (`price`),
    CONSTRAINT `fk_bi_search`
        FOREIGN KEY (`search_id`) REFERENCES `brand_searches` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Plano de implementação por fases

> ⚠️ **Seguir a ordem das fases obrigatoriamente.** Não iniciar a Fase 2 sem validar a Fase 1 via `curl`. Não iniciar a Fase 3 sem a Fase 2 funcionando com dados reais no banco.

---

## Fase 1 — Backend (1-2 dias)

> Camada de dados e lógica de negócio. Nenhuma interface ainda.

### Arquivo 1: `app/Models/BrandSearchModel.php`

CRUD sobre as 3 tabelas. Usar PDO puro, sem ORM. Seguir padrão dos Models existentes em `app/Models/`.

**Métodos obrigatórios:**

```php
<?php

declare(strict_types=1);

namespace App\Models;

class BrandSearchModel
{
    public function __construct(private \PDO $pdo) {}

    // Cria registro inicial — retorna o ID gerado
    public function createSearch(array $data): int;

    // Atualiza progresso durante a coleta (0-100)
    public function updateProgress(int $searchId, int $progress, string $status): void;

    // Marca como concluída com totais finais
    public function updateCompleted(int $searchId, int $totalItems, int $totalSellers): void;

    // Marca como falha com mensagem de erro
    public function updateFailed(int $searchId, string $errorMessage): void;

    // INSERT em lote com INSERT IGNORE (evita duplicatas entre categorias)
    public function saveSellers(int $searchId, array $sellers): void;

    // INSERT em lote com INSERT IGNORE
    public function saveItems(int $searchId, array $items): void;

    // Retorna dados de uma busca por ID
    public function getSearch(int $searchId): ?array;

    // Lista sellers com filtros e paginação
    public function getSellersBySearchId(int $searchId, array $filters, int $limit, int $offset): array;

    // Conta total de sellers (para paginação)
    public function countSellersBySearchId(int $searchId, array $filters): int;

    // Retorna buscas com status=pending — consumido pelo worker
    public function getPendingSearches(): array;

    // Retorna anúncios de um seller específico em uma busca
    public function getItemsBySeller(int $searchId, int $sellerId, int $limit, int $offset): array;
}
```

> ⚠️ Usar `INSERT IGNORE` em `saveSellers` e `saveItems`. A coleta percorre múltiplas categorias e o mesmo seller/item pode aparecer em mais de uma. As UNIQUE KEYs `(search_id, seller_id)` e `(search_id, item_id)` garantem integridade — o `INSERT IGNORE` apenas ignora silenciosamente as duplicatas.

---

### Arquivo 2: `app/Services/MercadoLivre/BrandSearchService.php`

Serviço principal. Toda lógica de chamada à API ML, paginação, deduplicação e persistência.

> ⚠️ Antes de criar este arquivo, verificar os Services existentes em `app/Services/MercadoLivre/` para herdar o padrão de cliente Guzzle, tratamento de erro e uso de token OAuth já estabelecido no projeto.

**Constantes da classe:**

```php
private const ML_API_BASE     = 'https://api.mercadolibre.com';
private const PAGE_LIMIT      = 50;    // itens por página (máximo da API ML)
private const MAX_OFFSET      = 950;   // limite máximo de offset da API ML
private const RATE_LIMIT_WAIT = 350;   // ms de espera entre requisições em lote
private const USERS_BATCH     = 20;    // sellers buscados por lote via multiget
```

**Métodos obrigatórios:**

```php
<?php

declare(strict_types=1);

namespace App\Services\MercadoLivre;

use App\Models\BrandSearchModel;
use GuzzleHttp\Client;
use Monolog\Logger;

class BrandSearchService
{
    public function __construct(
        private BrandSearchModel $model,
        private Client $httpClient,
        private Logger $logger
    ) {}

    // Cria registro pending e retorna search_id (chamado pelo Controller)
    public function initSearch(
        int $accountId,
        string $brandId,
        string $brandName,
        string $siteId,
        ?string $categoryId = null
    ): int;

    // Método principal — chamado pelo worker
    // Orquestra todo o fluxo: categorias → itens → sellers → persistência
    public function executeSearch(int $searchId): void;

    // Faz 1 requisição ao ML para descobrir todas as categorias da marca
    // GET /sites/{siteId}/search?BRAND={brandId}&limit=1
    // Retorna array de ['id' => 'MLB1747', 'name' => 'Retrovisores', 'results' => 12500]
    private function fetchCategories(string $brandId, string $siteId): array;

    // Pagina completamente uma categoria, retornando todos os itens
    // Respeita MAX_OFFSET (950) — para buscas maiores a paginação por categoria resolve
    private function fetchItemsByCategory(
        string $brandId,
        string $categoryId,
        string $siteId
    ): array;

    // Busca detalhes de sellers em lote usando multiget do ML
    // GET /users?ids=id1,id2,...,id20 (máx 20 por chamada)
    private function fetchSellerDetails(array $sellerIds): array;

    // Mapeia resposta da API ML /users/{id} para o schema de brand_sellers
    private function mapSellerData(int $searchId, array $mlUser, int $totalItems, float $avgPrice): array;

    // Mapeia item da resposta /search para o schema de brand_items
    private function mapItemData(int $searchId, array $mlItem): array;

    // Calcula score de reputação 0-100 a partir dos dados do ML
    private function calculateReputationScore(array $mlUser): int;

    // Controle de rate limit — aguarda entre requisições se necessário
    private function respectRateLimit(): void;
}
```

**Lógica de `executeSearch` — pseudocódigo:**

```php
public function executeSearch(int $searchId): void
{
    $search = $this->model->getSearch($searchId);
    $this->model->updateProgress($searchId, 0, 'running');
    $this->logger->info("BrandSearch iniciada", ['search_id' => $searchId]);

    try {
        // Passo 1: descobrir categorias
        $categories = $this->fetchCategories($search['brand_id'], $search['site_id']);
        $totalCategories = count($categories);
        $allSellerIds = [];

        // Passo 2: coletar itens por categoria
        foreach ($categories as $index => $category) {
            $items = $this->fetchItemsByCategory(
                $search['brand_id'],
                $category['id'],
                $search['site_id']
            );

            // Salvar itens e coletar seller_ids únicos
            $mappedItems = array_map(fn($item) => $this->mapItemData($searchId, $item), $items);
            $this->model->saveItems($searchId, $mappedItems); // INSERT IGNORE

            foreach ($items as $item) {
                $allSellerIds[$item['seller']['id']] = true;
            }

            // Atualizar progresso (0-70% para coleta de itens)
            $progress = (int)(($index + 1) / $totalCategories * 70);
            $this->model->updateProgress($searchId, $progress, 'running');
        }

        // Passo 3: buscar detalhes dos sellers únicos
        $uniqueSellerIds = array_keys($allSellerIds);
        $chunks = array_chunk($uniqueSellerIds, self::USERS_BATCH);

        foreach ($chunks as $chunkIndex => $chunk) {
            $sellersData = $this->fetchSellerDetails($chunk);

            foreach ($sellersData as $mlUser) {
                // Calcular total de itens e preço médio para este seller nesta busca
                // (buscar dos brand_items já salvos)
                $sellerStats = $this->model->getSellerStats($searchId, $mlUser['id']);
                $mapped = $this->mapSellerData(
                    $searchId, $mlUser,
                    $sellerStats['total_items'],
                    $sellerStats['avg_price']
                );
                $this->model->saveSellers($searchId, [$mapped]);
            }

            // Atualizar progresso (70-100% para enriquecimento de sellers)
            $progress = 70 + (int)(($chunkIndex + 1) / count($chunks) * 30);
            $this->model->updateProgress($searchId, $progress, 'running');
        }

        $totalSellers = count($uniqueSellerIds);
        $totalItems   = $this->model->countItems($searchId);
        $this->model->updateCompleted($searchId, $totalItems, $totalSellers);
        $this->logger->info("BrandSearch concluída", [
            'search_id'     => $searchId,
            'total_sellers' => $totalSellers,
            'total_items'   => $totalItems,
        ]);

    } catch (\Throwable $e) {
        $this->model->updateFailed($searchId, $e->getMessage());
        $this->logger->error("BrandSearch falhou", [
            'search_id' => $searchId,
            'error'     => $e->getMessage(),
        ]);
        throw $e;
    }
}
```

---

### Arquivo 3: `app/Controllers/BrandSearchController.php`

Seguir o padrão dos Controllers existentes. Verificar um Controller da API antes de criar.

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MercadoLivre\BrandSearchService;
use App\Models\BrandSearchModel;
use App\Core\Request;

class BrandSearchController
{
    public function __construct(
        private BrandSearchService $service,
        private BrandSearchModel $model,
        private Request $request
    ) {}

    // GET /brand-search → retorna a view
    public function index(): void;

    // POST /api/brand-search/start → inicia busca, retorna {search_id, status}
    // Body: {brand_id, brand_name, site_id, category_id?}
    // Validações: brand_id obrigatório, brand_name obrigatório, site_id default MLB
    // Retorna HTTP 202 com {search_id: int, status: "pending"}
    public function start(): void;

    // GET /api/brand-search/{id}/progress → retorna progresso
    // Retorna {search_id, progress, status, total_items, total_sellers, error_message?}
    // Cache Redis TTL 1s para evitar N queries de polling simultâneas
    public function progress(int $searchId): void;

    // GET /api/brand-search/{id}/sellers → lista sellers paginados
    // Parâmetros: reputation, min_items, sort, order, page, per_page
    // Retorna {data: [...], total: int, page: int, per_page: int, last_page: int}
    public function sellers(int $searchId): void;

    // GET /api/brand-search/{id}/items/{sellerId} → anúncios de um seller
    public function items(int $searchId, int $sellerId): void;

    // GET /api/brand-search/{id}/export → download CSV
    // Headers: Content-Type: text/csv, Content-Disposition: attachment; filename="sellers_AWA_YYYY-MM-DD.csv"
    public function export(int $searchId): void;
}
```

---

### Arquivo 4: Rotas — editar arquivos existentes

> ⚠️ **NÃO criar novo arquivo de rotas.** Adicionar apenas nos arquivos existentes.

**Em `app/Routes/web.php` — adicionar:**

```php
Router::get('/brand-search', [BrandSearchController::class, 'index']);
```

**Em `app/Routes/api.php` — adicionar:**

```php
Router::post('/api/brand-search/start',                     [BrandSearchController::class, 'start']);
Router::get('/api/brand-search/{id}/progress',              [BrandSearchController::class, 'progress']);
Router::get('/api/brand-search/{id}/sellers',               [BrandSearchController::class, 'sellers']);
Router::get('/api/brand-search/{id}/items/{sellerId}',      [BrandSearchController::class, 'items']);
Router::get('/api/brand-search/{id}/export',                [BrandSearchController::class, 'export']);
```

---

### Validação da Fase 1 — obrigatória antes de avançar

```bash
# 1. Iniciar uma busca
curl -X POST http://localhost/api/brand-search/start \
  -H 'Authorization: Bearer SEU_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{"brand_id":"7297804","brand_name":"AWA","site_id":"MLB"}'
# Esperado: {"search_id": 1, "status": "pending"}

# 2. Confirmar no banco
SELECT * FROM brand_searches WHERE id = 1;
# Esperado: status=pending, progress=0

# 3. Consultar progresso
curl -H 'Authorization: Bearer SEU_TOKEN' \
  http://localhost/api/brand-search/1/progress
# Esperado: {"search_id":1,"progress":0,"status":"pending"}

# 4. Listar sellers (vazio por enquanto — worker ainda não rodou)
curl -H 'Authorization: Bearer SEU_TOKEN' \
  http://localhost/api/brand-search/1/sellers
# Esperado: {"data":[],"total":0}
```

---

## Fase 2 — Worker (1 dia)

> A busca de 60k+ anúncios nunca pode rodar de forma síncrona. O HTTP retorna `search_id` imediatamente. O worker executa em background.

### Arquivo 5: `bin/brand-search-worker.php`

Seguir o padrão dos 24 workers existentes em `bin/`. Verificar um worker existente antes de criar.

```php
<?php

declare(strict_types=1);

/**
 * Brand Search Worker
 *
 * Uso:
 *   php bin/brand-search-worker.php                → processa fila pendente (execução única)
 *   php bin/brand-search-worker.php --search-id=42 → processa busca específica
 *   php bin/brand-search-worker.php --daemon        → loop contínuo (para Supervisor)
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap idêntico aos workers existentes (App\Core\Config, PDO, Logger, etc.)
// Verificar bin/orders-sync-worker.php ou bin/competitor-monitor-worker.php como referência

$searchId = null;
$isDaemon = false;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--search-id=')) {
        $searchId = (int) substr($arg, 12);
    }
    if ($arg === '--daemon') {
        $isDaemon = true;
    }
}

// Modo daemon: loop com sleep entre verificações
if ($isDaemon) {
    $logger->info('BrandSearchWorker daemon iniciado');
    while (true) {
        processPendingSearches($service, $model, $logger);
        sleep(10);
    }
}

// Modo busca específica
if ($searchId !== null) {
    $logger->info('Processando busca específica', ['search_id' => $searchId]);
    $service->executeSearch($searchId);
    exit(0);
}

// Modo execução única: processar toda a fila
processPendingSearches($service, $model, $logger);

function processPendingSearches(
    BrandSearchService $service,
    BrandSearchModel $model,
    Logger $logger
): void {
    $pending = $model->getPendingSearches();

    if (empty($pending)) {
        $logger->debug('Nenhuma busca pendente');
        return;
    }

    foreach ($pending as $search) {
        $logger->info('Iniciando busca', ['search_id' => $search['id'], 'brand' => $search['brand_name']]);
        try {
            $service->executeSearch((int) $search['id']);
        } catch (\Throwable $e) {
            $logger->error('Busca falhou', ['search_id' => $search['id'], 'error' => $e->getMessage()]);
        }
    }
}
```

---

### Arquivo 6: `config/supervisor/brand-search-worker.conf`

Seguir o padrão dos workers existentes no Supervisor.

```ini
[program:brand-search-worker]
command=php /var/www/bin/brand-search-worker.php --daemon
directory=/var/www
user=www-data
autostart=true
autorestart=true
startretries=3
startsecs=5
stopwaitsecs=30
stderr_logfile=/var/log/eskill/brand-search-worker.err.log
stdout_logfile=/var/log/eskill/brand-search-worker.out.log
stderr_logfile_maxbytes=10MB
stdout_logfile_maxbytes=10MB
```

**Ativar:**

```bash
supervisorctl reread
supervisorctl update
supervisorctl start brand-search-worker
supervisorctl status brand-search-worker
```

---

### Validação da Fase 2

```bash
# Rodar o worker manualmente para a busca criada na Fase 1
php bin/brand-search-worker.php --search-id=1

# Acompanhar logs em tempo real
tail -f storage/logs/brand-search-worker.log

# Verificar progresso via API
watch -n 2 'curl -s -H "Authorization: Bearer SEU_TOKEN" \
  http://localhost/api/brand-search/1/progress | python3 -m json.tool'

# Confirmar dados no banco após conclusão
SELECT COUNT(*) AS sellers FROM brand_sellers WHERE search_id = 1;
SELECT COUNT(*) AS items   FROM brand_items   WHERE search_id = 1;
SELECT status, progress, total_sellers, total_items FROM brand_searches WHERE id = 1;
```

---

## Fase 3 — Frontend (1-2 dias)

> A tela foi projetada e validada. Implementar **exatamente** conforme a especificação abaixo.

---

### Especificação visual completa da tela

#### Layout geral

```
┌─────────────────────────────────────────────────────────────────┐
│ BREADCRUMB: Dashboard > Marca e Posicionamento > Pesq. vendedores│
├─────────────────────────────────────────────────────────────────┤
│ TÍTULO: Pesquisa de vendedores por marca          [CSV] [Buscar] │
│ SUBTÍTULO: Mapeie todas as lojas que anunciam AWA no ML          │
├─────────────────────────────────────────────────────────────────┤
│ BARRA DE BUSCA (card branco, borda 0.5px)                        │
│ [Marca: AWA] [ID: 7297804] [Categoria ▼] [Ordenar ▼] [Buscar]   │
├─────────────────────────────────────────────────────────────────┤
│ BARRA DE PROGRESSO (oculta por padrão, aparece durante busca)    │
│ Texto do passo atual...                              67%         │
│ ████████████████████░░░░░░░░░░░░                                 │
├─────────────────────────────────────────────────────────────────┤
│ MÉTRICAS (4 cards cinza claro, grid 4 colunas)                   │
│ [347 vendedores] [60.454 anúncios] [R$ 87 preço médio] [89 líderes]│
├─────────────────────────────────────────────────────────────────┤
│ FILTROS RÁPIDOS                                                   │
│ Reputação: [Todos●] [Platinum] [Gold] [Silver] [Novos]           │
│ Anúncios:  [Todos●] [+100] [+500] [+1000]                       │
├─────────────────────────────────────────────────────────────────┤
│ TABELA DE VENDEDORES (card branco, borda 0.5px)                  │
│ "347 vendedores encontrados — marca AWA"      [Ver anúncios]     │
├────┬────────────────────┬────────┬──────────┬───────┬───┬───┬───┤
│ #  │ Loja / Vendedor    │Anúncios│ Reputação│Preço  │Tipo│↑↓│ ⋯ │
├────┼────────────────────┼────────┼──────────┼───────┼───┼───┼───┤
│ 1  │ [AW] AWA Oficial   │ 4.821  │ ████ 98% │R$112  │🟣 │ ▲ │ ⋯ │
│ 2  │ [MP] Moto Peças BR │ 2.340  │ ███  94% │R$ 89  │🟡 │ ▲ │ ⋯ │
│ ...│ ...                │ ...    │ ...      │ ...   │...│...│...│
├─────────────────────────────────────────────────────────────────┤
│ Mostrando 1–20 de 347              [‹] [1] [2] [3] ... [18] [›] │
└─────────────────────────────────────────────────────────────────┘
```

---

#### Componente: barra de busca

Quatro campos + botão em linha horizontal dentro de um `card` com borda `0.5px solid #e5e5e5`, `border-radius: 12px`, `padding: 1rem 1.25rem`.

| Campo | Tipo | Valor padrão | Observação |
|---|---|---|---|
| Marca | `input[type=text]` | `AWA` | Livre para digitar qualquer marca |
| ID da marca | `input[type=text]` | `7297804` | Largura fixa 100px |
| Categoria | `select` | `Todas as categorias` | Populado via `/available_filters` |
| Ordenar por | `select` | `Mais anúncios` | Mais anúncios / Melhor reputação / Menor preço / Maior preço |
| Buscar | `button` | — | Cor `#1D9E75`, texto branco |

---

#### Componente: barra de progresso

Oculta por padrão (`display: none`). Exibir quando `status === 'running'`. Ocultar quando `status === 'completed'` ou `'failed'`.

```
Altura da barra: 6px
Cor de preenchimento: #1D9E75
Transição: width 0.4s ease

Passos exibidos (texto à esquerda, % à direita):
  0%  → "Iniciando busca AWA..."
 20%  → "Paginando categorias (AWA)..."
 45%  → "Coletando seller_ids únicos..."
 70%  → "Consultando perfis dos vendedores..."
 90%  → "Calculando métricas e reputação..."
100%  → "Busca concluída!"
```

---

#### Componente: cards de métricas

Quatro cards em `grid-template-columns: repeat(4, 1fr)`, gap `10px`.

Cada card: `background: #f5f5f3`, `border-radius: 8px`, `padding: 12px 14px`.

| Card | Valor | Subtítulo |
|---|---|---|
| Total de vendedores | `total_sellers` da busca | "lojas ativas com AWA" |
| Total de anúncios | `total_items` da busca | "itens ativos encontrados" |
| Preço médio | `AVG(avg_price)` dos sellers | "média entre vendedores" |
| MercadoLíderes | `COUNT WHERE reputation IN ('gold','platinum')` | "gold + platinum" |

---

#### Componente: filtros rápidos (chips)

Dois grupos separados por `|`:

```
Reputação: [Todos] [Platinum] [Gold] [Silver] [Novos]
Anúncios:  [Todos] [+100] [+500] [+1000]
```

**Estilos dos chips:**

```css
/* inativo */
.filter-chip {
  font-size: 12px;
  padding: 4px 10px;
  border-radius: 20px;
  border: 0.5px solid #d3d1c7;
  background: white;
  color: #888780;
  cursor: pointer;
}

/* ativo */
.filter-chip.active {
  background: #E1F5EE;
  border-color: #5DCAA5;
  color: #085041;
  font-weight: 500;
}
```

Filtros devem funcionar **client-side** sem nova requisição ao servidor. Apenas mostrar/ocultar linhas da tabela já carregada.

---

#### Componente: tabela de vendedores

Colunas fixas com `table-layout: fixed`. Nenhuma coluna quebra linha.

| Coluna | Largura | Conteúdo |
|---|---|---|
| `#` | 36px | Número sequencial |
| Loja / Vendedor | 220px | Avatar (círculo 32px com iniciais) + nome em bold + ID em `font-family: monospace; color: #888780` |
| Anúncios | 90px | `total_items_brand` formatado com milhar (ex: `4.821`) |
| Reputação | 110px | Barra de progresso 60px + percentual `reputation_score` |
| Preço médio | 100px | `R$ XX` formatado |
| Tipo | 90px | Badge colorido (ver cores abaixo) |
| Tendência | 80px | `▲ subindo` verde / `▼ caindo` vermelho / `— estável` cinza |
| Ações | 80px | 3 icon buttons: lupa, download, gráfico |

**Cores dos badges de reputação:**

```css
/* Platinum */
.badge-platinum { background: #EEEDFE; color: #3C3489; }

/* Gold */
.badge-gold { background: #FAEEDA; color: #633806; }

/* Silver */
.badge-silver { background: #F1EFE8; color: #444441; }

/* Novo */
.badge-new { background: #E6F1FB; color: #0C447C; }
```

**Cor da barra de reputação:**

```javascript
function getReputationColor(score) {
  if (score >= 90) return '#1D9E75'; // verde
  if (score >= 75) return '#EF9F27'; // âmbar
  return '#E24B4A';                  // vermelho
}
```

**Avatar (círculo com iniciais):**

```javascript
function getInitials(nickname) {
  return nickname.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

// Cor de fundo por nível
const avatarColors = {
  platinum: { bg: '#EEEDFE', text: '#3C3489' },
  gold:     { bg: '#FAEEDA', text: '#633806' },
  silver:   { bg: '#F1EFE8', text: '#444441' },
  new:      { bg: '#E6F1FB', text: '#0C447C' },
};
```

**Ações por linha (icon buttons 28x28px):**

- Lupa → abre modal ou navega para `/brand-search/{searchId}/seller/{sellerId}` com todos os anúncios
- Download → chama `GET /api/brand-search/{id}/export?seller_id={id}` (export individual)
- Gráfico → navega para o Módulo 07 passando o `seller_id` como concorrente

---

#### Componente: paginação

```
Esquerda: "Mostrando 1–20 de 347"
Direita:  [‹] [1] [2] [3] ... [18] [›]

Página atual: background #E1F5EE, color #0F6E56, font-weight 500
Demais páginas: border 0.5px solid #d3d1c7
```

Padrão: `per_page = 20`.

---

### Arquivo 7: `app/Views/brand_analysis/brand_search.php`

View PHP pura. Estender o layout base do projeto (verificar qual arquivo é o `app.php` / `main.php` em `app/Views/layouts/` antes de criar).

**Regras da view:**

- **Zero lógica PHP inline** — nenhum `$variable`, nenhum `foreach`, nenhum `if` PHP na view
- Todos os dados carregados exclusivamente via JavaScript/AJAX após o carregamento da página
- Apenas HTML estrutural + `<script src="/js/brand-search.js"></script>`
- Incluir os `data-attributes` necessários nos elementos que o JS vai manipular:

```html
<!-- Exemplos de data-attributes necessários -->
<div id="brand-search-app"
     data-default-brand-id="7297804"
     data-default-brand-name="AWA"
     data-site-id="MLB">
</div>

<div id="progress-wrap" style="display:none">
  <div id="progress-fill" style="width:0%"></div>
  <span id="progress-text">Aguardando...</span>
  <span id="progress-pct">0%</span>
</div>

<div id="stats-row">
  <div class="stat" id="stat-sellers"><span class="stat-value">—</span></div>
  <div class="stat" id="stat-items"><span class="stat-value">—</span></div>
  <div class="stat" id="stat-price"><span class="stat-value">—</span></div>
  <div class="stat" id="stat-leaders"><span class="stat-value">—</span></div>
</div>

<table id="sellers-table">
  <thead>...</thead>
  <tbody id="sellers-tbody"></tbody>
</table>

<div id="pagination-wrap"></div>
```

---

### Arquivo 8: `public/js/brand-search.js`

JavaScript vanilla. Sem jQuery. Sem framework. Seguir o padrão dos JS existentes em `public/js/`.

**Estrutura completa do arquivo:**

```javascript
// public/js/brand-search.js
// Módulo 20 — Brand Search
// eskill.com.br

'use strict';

// ── Estado ─────────────────────────────────────────────────────────────────
const state = {
  searchId:      null,
  pollInterval:  null,
  currentPage:   1,
  perPage:       20,
  totalSellers:  0,
  activeFilters: { reputation: null, minItems: null },
  sortBy:        'total_items',
  sortOrder:     'desc',
};

// ── Init ───────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  bindEvents();
});

function bindEvents() {
  document.getElementById('btn-search').addEventListener('click', startSearch);
  document.getElementById('btn-export').addEventListener('click', () => exportCSV(state.searchId));
  document.querySelectorAll('.filter-chip[data-reputation]').forEach(chip => {
    chip.addEventListener('click', () => setReputationFilter(chip.dataset.reputation));
  });
  document.querySelectorAll('.filter-chip[data-min-items]').forEach(chip => {
    chip.addEventListener('click', () => setMinItemsFilter(parseInt(chip.dataset.minItems)));
  });
}

// ── Busca ──────────────────────────────────────────────────────────────────

async function startSearch() {
  const brandId   = document.getElementById('inp-brand-id').value.trim();
  const brandName = document.getElementById('inp-brand').value.trim();
  const siteId    = 'MLB';
  const categoryId = document.getElementById('sel-cat').value || null;

  if (!brandId || !brandName) {
    alert('Preencha o nome e o ID da marca.');
    return;
  }

  showProgressBar();
  updateProgress(0, 'Iniciando busca ' + brandName + '...');

  try {
    const res = await apiPost('/api/brand-search/start', { brand_id: brandId, brand_name: brandName, site_id: siteId, category_id: categoryId });
    state.searchId = res.search_id;
    startPolling(res.search_id);
  } catch (err) {
    hideProgressBar();
    showError('Erro ao iniciar busca: ' + err.message);
  }
}

function startPolling(searchId) {
  state.pollInterval = setInterval(async () => {
    try {
      const data = await apiGet(`/api/brand-search/${searchId}/progress`);
      updateProgressFromApi(data);

      if (data.status === 'completed') {
        stopPolling();
        hideProgressBar();
        updateStats(data);
        loadSellers(searchId, 1);
      }

      if (data.status === 'failed') {
        stopPolling();
        hideProgressBar();
        showError('Busca falhou: ' + (data.error_message || 'erro desconhecido'));
      }
    } catch (err) {
      stopPolling();
      showError('Erro no polling: ' + err.message);
    }
  }, 2000);
}

function stopPolling() {
  if (state.pollInterval) {
    clearInterval(state.pollInterval);
    state.pollInterval = null;
  }
}

// ── Sellers ────────────────────────────────────────────────────────────────

async function loadSellers(searchId, page) {
  state.currentPage = page;

  const params = new URLSearchParams({
    page:      page,
    per_page:  state.perPage,
    sort:      state.sortBy,
    order:     state.sortOrder,
  });

  if (state.activeFilters.reputation) params.set('reputation', state.activeFilters.reputation);
  if (state.activeFilters.minItems)   params.set('min_items',  state.activeFilters.minItems);

  const data = await apiGet(`/api/brand-search/${searchId}/sellers?${params}`);
  state.totalSellers = data.total;

  renderTable(data.data);
  renderPagination(data.total, data.page, data.last_page);
  updateTableTitle(data.total);
}

// ── Render ─────────────────────────────────────────────────────────────────

function renderTable(sellers) {
  const tbody = document.getElementById('sellers-tbody');

  if (!sellers.length) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:2rem;color:#888780">Nenhum vendedor encontrado.</td></tr>';
    return;
  }

  tbody.innerHTML = sellers.map((s, i) => {
    const rank = (state.currentPage - 1) * state.perPage + i + 1;
    return `
      <tr>
        <td>${rank}</td>
        <td>
          <div class="seller-cell">
            <div class="avatar" style="background:${getAvatarBg(s.reputation_level)};color:${getAvatarColor(s.reputation_level)}">
              ${getInitials(s.nickname)}
            </div>
            <div>
              <div class="seller-name">${escapeHtml(s.nickname)}</div>
              <div class="seller-id">ML-${s.seller_id}</div>
            </div>
          </div>
        </td>
        <td>${formatNumber(s.total_items_brand)}</td>
        <td>${renderReputationBar(s.reputation_score)}</td>
        <td>${formatCurrency(s.avg_price)}</td>
        <td>${renderBadge(s.reputation_level)}</td>
        <td>${renderTrend(s.trend)}</td>
        <td>
          <div class="action-cell">
            <button class="icon-btn" onclick="viewSellerItems(${s.seller_id})" title="Ver anúncios">
              <!-- ícone lupa SVG -->
            </button>
            <button class="icon-btn" onclick="exportSellerCSV(${s.seller_id})" title="Exportar">
              <!-- ícone download SVG -->
            </button>
            <button class="icon-btn" onclick="analyzeCompetitor(${s.seller_id})" title="Analisar concorrente">
              <!-- ícone gráfico SVG -->
            </button>
          </div>
        </td>
      </tr>`;
  }).join('');
}

function renderBadge(level) {
  const badges = {
    platinum: ['#EEEDFE', '#3C3489', 'Platinum'],
    gold:     ['#FAEEDA', '#633806', 'Gold'],
    silver:   ['#F1EFE8', '#444441', 'Silver'],
    new:      ['#E6F1FB', '#0C447C', 'Novo'],
  };
  const [bg, color, label] = badges[level] || badges['new'];
  return `<span class="badge" style="background:${bg};color:${color}">${label}</span>`;
}

function renderReputationBar(score) {
  const color = score >= 90 ? '#1D9E75' : score >= 75 ? '#EF9F27' : '#E24B4A';
  return `
    <div class="rep-bar">
      <div class="rep-track">
        <div class="rep-fill" style="width:${score}%;background:${color}"></div>
      </div>
      <span class="rep-val">${score}%</span>
    </div>`;
}

function renderTrend(trend) {
  if (trend === 'up')   return '<span style="color:#1D9E75">▲ subindo</span>';
  if (trend === 'down') return '<span style="color:#E24B4A">▼ caindo</span>';
  return '<span style="color:#888780">— estável</span>';
}

function renderPagination(total, currentPage, lastPage) {
  // Implementar paginação com botões ‹ [1] [2] ... [N] ›
  // Página atual com background #E1F5EE, color #0F6E56
}

// ── Filtros ────────────────────────────────────────────────────────────────

function setReputationFilter(value) {
  state.activeFilters.reputation = value === 'all' ? null : value;
  document.querySelectorAll('.filter-chip[data-reputation]').forEach(c => c.classList.remove('active'));
  document.querySelector(`.filter-chip[data-reputation="${value}"]`).classList.add('active');
  if (state.searchId) loadSellers(state.searchId, 1);
}

function setMinItemsFilter(value) {
  state.activeFilters.minItems = value === 0 ? null : value;
  document.querySelectorAll('.filter-chip[data-min-items]').forEach(c => c.classList.remove('active'));
  document.querySelector(`.filter-chip[data-min-items="${value}"]`).classList.add('active');
  if (state.searchId) loadSellers(state.searchId, 1);
}

// ── Helpers ────────────────────────────────────────────────────────────────

async function apiPost(url, body) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify(body),
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

async function apiGet(url) {
  const res = await fetch(url, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

function formatCurrency(value) {
  if (!value) return '—';
  return 'R$ ' + Math.round(value).toLocaleString('pt-BR');
}

function formatNumber(value) {
  return parseInt(value || 0).toLocaleString('pt-BR');
}

function getInitials(nickname) {
  return (nickname || '?').split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

function getAvatarBg(level) {
  const map = { platinum: '#EEEDFE', gold: '#FAEEDA', silver: '#F1EFE8', new: '#E6F1FB' };
  return map[level] || '#F1EFE8';
}

function getAvatarColor(level) {
  const map = { platinum: '#3C3489', gold: '#633806', silver: '#444441', new: '#0C447C' };
  return map[level] || '#444441';
}

function escapeHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showProgressBar()  { document.getElementById('progress-wrap').style.display = 'block'; }
function hideProgressBar()  { document.getElementById('progress-wrap').style.display = 'none';  }

function updateProgress(pct, text) {
  document.getElementById('progress-fill').style.width = pct + '%';
  document.getElementById('progress-pct').textContent  = pct + '%';
  document.getElementById('progress-text').textContent = text;
}

function updateProgressFromApi(data) {
  const texts = {
    pending:   'Aguardando worker...',
    running:   data.progress < 20 ? 'Iniciando busca...'
             : data.progress < 45 ? 'Paginando categorias...'
             : data.progress < 70 ? 'Coletando seller_ids únicos...'
             : data.progress < 90 ? 'Consultando perfis dos vendedores...'
             : 'Calculando métricas e reputação...',
    completed: 'Busca concluída!',
    failed:    'Busca falhou.',
  };
  updateProgress(data.progress || 0, texts[data.status] || '');
}

function updateStats(data) {
  document.querySelector('#stat-sellers .stat-value').textContent = formatNumber(data.total_sellers);
  document.querySelector('#stat-items .stat-value').textContent   = formatNumber(data.total_items);
}

function updateTableTitle(total) {
  document.getElementById('table-title').textContent =
    formatNumber(total) + ' vendedores encontrados — marca AWA';
}

function showError(msg) {
  console.error(msg);
  // Usar o sistema de alertas/toast existente no projeto
}

function exportCSV(searchId) {
  if (!searchId) return;
  window.location.href = `/api/brand-search/${searchId}/export`;
}

function viewSellerItems(sellerId) {
  window.location.href = `/brand-search/${state.searchId}/seller/${sellerId}`;
}

function analyzeCompetitor(sellerId) {
  window.location.href = `/competitors/analyze?seller_id=${sellerId}`;
}
```

---

### Arquivo 9: Menu lateral — editar `app/Views/layouts/sidebar.php`

> ⚠️ Verificar o arquivo correto do sidebar antes de editar. Adicionar **apenas 1 item** dentro do grupo "Marca e Posicionamento" já existente.

```html
<!-- Dentro do grupo "Marca e Posicionamento" -->
<a href="/brand-search" class="nav-item <?= $currentRoute === 'brand-search' ? 'active' : '' ?>">
    Pesquisa de vendedores
</a>
```

---

## Fase 4 — Testes e homologação (1 dia)

### Arquivo 10: `tests/Unit/BrandSearchServiceTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\MercadoLivre\BrandSearchService;

class BrandSearchServiceTest extends TestCase
{
    // Cenários obrigatórios:

    // 1. fetchCategories() retorna array com id, name, results
    public function testFetchCategoriesReturnsList(): void;

    // 2. Paginação para quando offset >= 950 (MAX_OFFSET)
    public function testPaginationStopsAtMaxOffset(): void;

    // 3. seller_ids iguais de categorias diferentes contam 1 vez
    public function testDeduplicatesSellerIds(): void;

    // 4. API retorna 429 → retry com backoff exponencial
    public function testRateLimitBackoffOnHttp429(): void;

    // 5. progress é atualizado a cada categoria processada
    public function testUpdateProgressOnEachCategory(): void;

    // 6. Exceção na API → status=failed, error_message preenchido
    public function testStatusFailedOnApiException(): void;

    // 7. calculateReputationScore() retorna 0-100
    public function testCalculateReputationScoreRange(): void;

    // 8. mapSellerData() preenche todos os campos obrigatórios
    public function testMapSellerDataAllRequiredFields(): void;
}
```

### Arquivo 11: `tests/Unit/BrandSearchModelTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\BrandSearchModel;

class BrandSearchModelTest extends TestCase
{
    // 1. saveSellers com duplicatas não lança exceção (INSERT IGNORE)
    public function testSaveSellersIgnoresDuplicates(): void;

    // 2. getSellersBySearchId filtra por reputation corretamente
    public function testGetSellersByReputationFilter(): void;

    // 3. getSellersBySearchId filtra por min_items corretamente
    public function testGetSellersByMinItemsFilter(): void;

    // 4. countSellersBySearchId retorna total correto
    public function testCountSellersCorrectTotal(): void;

    // 5. getPendingSearches retorna apenas status=pending
    public function testGetPendingSearchesOnlyPending(): void;

    // 6. updateCompleted muda status e preenche totais
    public function testUpdateCompletedSetsStatus(): void;
}
```

### Arquivo 12: `tests/e2e/brand-search.spec.ts`

```typescript
import { test, expect } from '@playwright/test';

test.describe('Brand Search — Módulo 20', () => {

  test.beforeEach(async ({ page }) => {
    // Login usando helper existente no projeto
    await page.goto('/login');
    await page.fill('#email', process.env.TEST_EMAIL!);
    await page.fill('#password', process.env.TEST_PASSWORD!);
    await page.click('[type=submit]');
    await page.waitForURL('/dashboard');
  });

  test('carrega a tela sem erros', async ({ page }) => {
    await page.goto('/brand-search');
    await expect(page.locator('h1')).toContainText('Pesquisa de vendedores por marca');
    await expect(page.locator('#inp-brand')).toHaveValue('AWA');
    await expect(page.locator('#inp-brand-id')).toHaveValue('7297804');
  });

  test('inicia busca e exibe barra de progresso', async ({ page }) => {
    await page.goto('/brand-search');
    await page.click('#btn-search');
    await expect(page.locator('#progress-wrap')).toBeVisible();
    await expect(page.locator('#progress-text')).not.toBeEmpty();
  });

  test('conclui busca e exibe tabela de resultados', async ({ page }) => {
    await page.goto('/brand-search');
    await page.click('#btn-search');

    // Aguardar conclusão (timeout 5 minutos para busca real)
    await page.waitForFunction(
      () => document.querySelector('#progress-pct')?.textContent === '100%',
      { timeout: 300000 }
    );

    await expect(page.locator('#sellers-tbody tr')).toHaveCount.toBeGreaterThan(0);
    await expect(page.locator('#stat-sellers .stat-value')).not.toHaveText('—');
  });

  test('filtro de reputação funciona', async ({ page }) => {
    // Assumindo busca já realizada — usar search_id de fixture
    await page.goto('/brand-search');
    // ... carregar busca existente
    await page.click('[data-reputation="gold"]');
    // Verificar que apenas Gold aparecem na tabela
    const badges = await page.locator('.badge-gold').count();
    const allBadges = await page.locator('.badge').count();
    expect(badges).toBe(allBadges);
  });

  test('exporta CSV', async ({ page }) => {
    await page.goto('/brand-search');
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.click('#btn-export'),
    ]);
    expect(download.suggestedFilename()).toMatch(/sellers_AWA_\d{4}-\d{2}-\d{2}\.csv/);
  });

});
```

**Executar:**

```bash
# Todos os testes unitários do módulo
php vendor/bin/phpunit --filter BrandSearch

# Teste específico
php vendor/bin/phpunit --filter BrandSearchServiceTest
php vendor/bin/phpunit --filter BrandSearchModelTest

# Lint PHP em todos os arquivos novos
php -l app/Models/BrandSearchModel.php
php -l app/Services/MercadoLivre/BrandSearchService.php
php -l app/Controllers/BrandSearchController.php
php -l bin/brand-search-worker.php

# E2E
./run-prod-validation.sh $EMAIL $PASS --spec brand-search
```

---

## 5. Checklist de qualidade

Verificar **todos** os itens antes de fazer commit.

### Padrões PHP obrigatórios (conforme PRD seção 26)

- [ ] `declare(strict_types=1)` no topo de **todos** os arquivos PHP novos
- [ ] Type hints completos em **todos** os parâmetros e retornos de métodos
- [ ] **Nenhum** `var_dump`, `print_r` ou `echo` em código de produção
- [ ] **Nenhum** `catch (\Exception $e) {}` vazio — sempre logar ou relançar
- [ ] PSR-12 — `php -l` em cada arquivo novo sem erros
- [ ] **Nenhum** arquivo acima de 300 linhas — refatorar se necessário
- [ ] Logs via Monolog exclusivamente — nunca `error_log()` diretamente

### Segurança

- [ ] Token OAuth **nunca** logado em texto plano — mascarar: `substr($token, 0, 8) . '...'`
- [ ] Todos os parâmetros de entrada validados e sanitizados no Controller
- [ ] `search_id` e `seller_id` validados como inteiros positivos antes de queries
- [ ] Rate limiting no endpoint `POST /api/brand-search/start` (máx 5 req/min por usuário)
- [ ] CSRF token validado em todas as requisições POST

### Performance

- [ ] `saveSellers` e `saveItems` usam `INSERT` em lote — não loop de INSERTs individuais
- [ ] Índices verificados com `EXPLAIN` nas queries mais frequentes
- [ ] Cache Redis no endpoint `/progress` (TTL 1s) para evitar N queries simultâneas de polling
- [ ] Rate limit da API ML respeitado: máx 3.000 req/hora, sleep entre chamadas em lote

### Testes

- [ ] `php vendor/bin/phpunit --filter BrandSearch` — todos passando
- [ ] Teste manual do fluxo completo via browser (busca real com `BRAND=7297804`)
- [ ] Verificar que dados chegaram nas 3 tabelas: `brand_searches`, `brand_sellers`, `brand_items`

### Regras críticas — NÃO fazer

- [ ] ❌ **NÃO** criar arquivo de rotas novo — adicionar apenas em `api.php` e `web.php`
- [ ] ❌ **NÃO** usar dados mockados em dev ou produção
- [ ] ❌ **NÃO** sobrescrever o `.env` sem confirmação explícita
- [ ] ❌ **NÃO** criar ORM, framework ou biblioteca nova sem esgotar opções existentes
- [ ] ❌ **NÃO** iniciar a Fase 3 sem validar os endpoints da Fase 1 via `curl`
- [ ] ❌ **NÃO** usar conta ML pessoal para testes — usar usuários de teste

---

## 6. Resumo de todos os arquivos

| # | Arquivo | Ação | Fase |
|---|---|---|---|
| 1 | `database/migrations/100_brand_searches.sql` | ✅ JÁ GERADO — copiar | Pré-requisito |
| 2 | `database/migrations/101_brand_sellers.sql` | ✅ JÁ GERADO — copiar | Pré-requisito |
| 3 | `database/migrations/102_brand_items.sql` | ✅ JÁ GERADO — copiar | Pré-requisito |
| 4 | `bin/migrate-brand-search.php` | ✅ JÁ GERADO — copiar | Pré-requisito |
| 5 | `app/Models/BrandSearchModel.php` | 🆕 CRIAR | Fase 1 |
| 6 | `app/Services/MercadoLivre/BrandSearchService.php` | 🆕 CRIAR | Fase 1 |
| 7 | `app/Controllers/BrandSearchController.php` | 🆕 CRIAR | Fase 1 |
| 8 | `app/Routes/api.php` | ✏️ EDITAR — adicionar 5 rotas | Fase 1 |
| 9 | `app/Routes/web.php` | ✏️ EDITAR — adicionar 1 rota | Fase 1 |
| 10 | `bin/brand-search-worker.php` | 🆕 CRIAR | Fase 2 |
| 11 | `config/supervisor/brand-search-worker.conf` | 🆕 CRIAR | Fase 2 |
| 12 | `app/Views/brand_analysis/brand_search.php` | 🆕 CRIAR | Fase 3 |
| 13 | `public/js/brand-search.js` | 🆕 CRIAR | Fase 3 |
| 14 | `app/Views/layouts/sidebar.php` | ✏️ EDITAR — 1 linha no menu | Fase 3 |
| 15 | `tests/Unit/BrandSearchServiceTest.php` | 🆕 CRIAR | Fase 4 |
| 16 | `tests/Unit/BrandSearchModelTest.php` | 🆕 CRIAR | Fase 4 |
| 17 | `tests/Integration/BrandSearchIntegrationTest.php` | 🆕 CRIAR | Fase 4 |
| 18 | `tests/e2e/brand-search.spec.ts` | 🆕 CRIAR | Fase 4 |

---

*eskill.com.br — Módulo 20 BRAND-003 v1.0 — AWA Motos — 2026-04-06*
