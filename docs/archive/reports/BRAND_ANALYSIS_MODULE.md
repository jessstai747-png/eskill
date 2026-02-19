# Módulo de Análise de Marca AWA

## Visão Geral

Este módulo fornece análise completa de anúncios da marca AWA no Mercado Livre, focando em motos e acessórios para motos. Identifica lacunas de dados, inconsistências na representação da marca e fornece recomendações para melhoria.

## Funcionalidades

### 1. Identificação da Marca
- Busca em categorias de motos e acessórios (MLB1051, MLB214858, MLB5750, MLB1747)
- Reconhece variações do nome (AWA, Awa, A.W.A, etc.)
- Valida se o atributo BRAND está corretamente preenchido

### 2. Detecção de Lacunas (Gaps)
- **missing_brand**: Anúncios sem atributo de marca
- **brand_in_title_not_attribute**: Marca mencionada no título mas não no atributo

### 3. Detecção de Inconsistências
- **wrong_brand**: Marca preenchida com valor diferente de AWA
- **misspelled_brand**: Erros de digitação no nome da marca

### 4. Coleta Completa de Dados
Para cada anúncio coletamos:
- ID, título, categoria
- Preço atual e promocional
- Moeda (BRL)
- Condição do produto (novo/usado)
- Estoque disponível e quantidade vendida
- Informações de frete (grátis, pago, Full)
- Dados do vendedor (ID, nickname, reputação, localização)

### 5. Análises e Métricas
- **Score de Consistência**: Percentual de anúncios com marca corretamente atribuída
- **Score de Saúde**: Avaliação geral da representação da marca (0-100)
- **Análise de Preços**: Mín, máx, média, mediana, distribuição por faixas
- **Análise de Frete**: Distribuição entre frete grátis, pago e Full
- **Top Vendedores**: Ranking de vendedores da marca

## Endpoints da API

### Dashboard
```
GET /api/brand/awa/dashboard
```
Retorna métricas rápidas para visualização em dashboard.

### Análise Completa
```
GET /api/brand/awa/analyze
```
**Parâmetros:**
- `categories` (opcional): IDs das categorias separados por vírgula
- `max_results` (opcional): Limite de resultados (padrão: 500)
- `include_details` (opcional): Incluir detalhes completos dos itens (padrão: true)

### Análise Rápida
```
GET /api/brand/awa/quick
```
**Parâmetros:**
- `category` (opcional): ID de uma categoria
- `max_results` (opcional): Limite de resultados (padrão: 100)

### Lacunas de Dados
```
GET /api/brand/awa/gaps
```
Retorna apenas os anúncios com lacunas de dados.

### Inconsistências
```
GET /api/brand/awa/inconsistencies
```
Retorna anúncios com inconsistências na marca.

### Vendedores
```
GET /api/brand/awa/sellers
```
Lista vendedores da marca AWA com estatísticas.

### Resumo Executivo
```
GET /api/brand/awa/summary
```
Retorna resumo formatado para apresentação executiva.

### Histórico
```
GET /api/brand/awa/history
```
**Parâmetros:**
- `limit` (opcional): Quantidade de registros (padrão: 30)

### Análise de Preços
```
GET /api/brand/awa/pricing
```

### Análise de Frete
```
GET /api/brand/awa/shipping
```

### Comparação com Concorrentes
```
GET /api/brand/awa/compare
```
**Parâmetros:**
- `category` (opcional): ID da categoria (padrão: MLB214858)
- `competitors` (opcional): Marcas concorrentes separadas por vírgula

### Tendências
```
GET /api/brand/awa/trends
```
**Parâmetros:**
- `days` (opcional): Período de análise em dias (padrão: 30)

### Alertas
```
GET /api/brand/awa/alerts
```
Retorna alertas baseados na análise (críticos, avisos, informativos).

### Produtos Mais Vendidos
```
GET /api/brand/awa/top-products
```
**Parâmetros:**
- `category` (opcional): ID da categoria
- `limit` (opcional): Quantidade de produtos (padrão: 20, máx: 50)

### Oportunidades de Mercado
```
GET /api/brand/awa/opportunities
```
**Parâmetros:**
- `category` (opcional): ID da categoria

### Estatísticas de Vendedores
```
GET /api/brand/awa/seller-stats
```

### Padrões de Inconsistência
```
GET /api/brand/awa/patterns
```

### Relatório Completo Consolidado
```
GET /api/brand/awa/report
```
Retorna todas as análises em um único relatório.

### Listagem de Itens
```
GET /api/brand/awa/items
```
**Parâmetros:**
- `has_brand` (opcional): Filtrar por presença de marca (true/false)
- `condition` (opcional): Filtrar por condição (new/used)
- `free_shipping` (opcional): Filtrar por frete grátis (true/false)
- `min_price` (opcional): Preço mínimo
- `max_price` (opcional): Preço máximo
- `sort` (opcional): Campo de ordenação
- `order` (opcional): Direção da ordenação (asc/desc)
- `page` (opcional): Página para paginação
- `per_page` (opcional): Itens por página

### Exportações

#### CSV
```
GET /api/brand/awa/export/csv
```

#### JSON
```
GET /api/brand/awa/export/json
```

#### PDF
```
GET /api/pdf/brand/awa
```

## Interface Web

Acesse a interface de análise em:
- `/brand-analysis`
- `/awa`
- `/brand/awa`

A interface oferece:
- Filtros por categoria e quantidade de resultados
- Visualização de KPIs principais
- Gráficos de distribuição de preços e frete
- Lista de lacunas e inconsistências
- Ranking de vendedores
- Recomendações de melhoria
- Exportação em CSV, JSON e PDF

## Estrutura de Resposta

### Análise Completa
```json
{
  "success": true,
  "data": {
    "brand": "AWA",
    "analysis_date": "2025-01-15 10:30:00",
    "total_listings": 150,
    "listings_with_brand": 120,
    "listings_without_brand": 25,
    "listings_with_wrong_brand": 5,
    "brand_consistency_score": 80.0,
    "gaps_detected": [...],
    "inconsistencies": [...],
    "sellers": {...},
    "price_analysis": {
      "min": 50.00,
      "max": 2500.00,
      "avg": 350.00,
      "median": 280.00,
      "price_ranges": {...}
    },
    "shipping_analysis": {
      "free_shipping": { "count": 80, "percentage": 53.33 },
      "paid_shipping": { "count": 70, "percentage": 46.67 },
      "full_shipping": { "count": 30, "percentage": 20.00 }
    },
    "summary": {
      "health_status": {
        "score": 75,
        "status": "good"
      },
      "critical_issues": [...],
      "recommendations": [...]
    }
  }
}
```

## Uso Programático

### Exemplo PHP
```php
use App\Services\BrandAnalyzerService;

$analyzer = new BrandAnalyzerService($accountId);

// Análise completa
$results = $analyzer->analyzeAwaBrand([
    'categories' => ['MLB214858', 'MLB5750'],
    'max_results' => 500,
    'include_details' => true,
]);

// Análise rápida
$quick = $analyzer->quickAnalysis([
    'category' => 'MLB214858',
    'max_results' => 100,
]);

// Detectar padrões de inconsistência
$patterns = $analyzer->detectInconsistencyPatterns($results);

// Comparar com concorrentes
$comparison = $analyzer->compareWithCompetitors('MLB214858', ['PRO TORK', 'RIFFEL']);

// Analisar tendências (últimos 30 dias)
$trends = $analyzer->analyzeTrends(30);

// Gerar alertas
$alerts = $analyzer->generateAlerts($results);

// Produtos mais vendidos
$topProducts = $analyzer->getTopSellingProducts('MLB214858', 20);

// Análise de oportunidades
$opportunities = $analyzer->analyzeOpportunities('MLB214858');

// Estatísticas de vendedores
$sellerStats = $analyzer->getSellerStatistics();

// Exportar relatório
$csv = $analyzer->exportReport($results, 'csv');
$summary = $analyzer->exportReport($results, 'summary');

// Histórico de análises
$history = $analyzer->getAnalysisHistory('AWA', 30);
```

## Categorias Suportadas

| ID | Nome |
|---|---|
| MLB1051 | Motos |
| MLB214858 | Acessórios para Motos |
| MLB5750 | Peças de Motos |
| MLB1747 | Acessórios para Veículos |

## Variações de Marca Reconhecidas

- AWA
- Awa
- awa
- A.W.A
- A.W.A.
- a.w.a
- A W A

## Métricas de Saúde

| Score | Status | Descrição |
|---|---|---|
| 95-100 | excellent | Excelente representação da marca |
| 85-94 | good | Boa representação com pequenos ajustes |
| 70-84 | fair | Regular, requer atenção |
| 50-69 | poor | Ruim, necessita correções urgentes |
| 0-49 | critical | Crítico, problemas graves identificados |

## Banco de Dados

O módulo cria automaticamente a tabela `brand_analysis_history` para armazenar histórico:

```sql
CREATE TABLE brand_analysis_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    brand VARCHAR(100) NOT NULL,
    analysis_date DATETIME NOT NULL,
    total_listings INT NOT NULL,
    listings_with_brand INT NOT NULL,
    listings_without_brand INT NOT NULL,
    consistency_score DECIMAL(5,2),
    health_score INT,
    health_status VARCHAR(20),
    gaps_count INT,
    inconsistencies_count INT,
    sellers_count INT,
    result_json TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
```

## Considerações de Performance

- Utilize a análise rápida (`/quick`) para verificações frequentes
- A análise completa pode levar alguns minutos para muitos itens
- Resultados são cacheados por 5 minutos para itens individuais
- Dados de vendedores são cacheados por 30 minutos

## Recomendações de Uso

1. **Monitoramento Regular**: Execute análise rápida diariamente
2. **Análise Completa**: Execute semanalmente para relatórios detalhados
3. **Correções Prioritárias**: Foque primeiro em inconsistências (marca errada)
4. **Lacunas**: Corrija anúncios onde a marca aparece no título mas não no atributo
5. **Exportação**: Use PDF para relatórios executivos, CSV para análise em planilhas
