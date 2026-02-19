# 📋 Plano de Implementação por Fases: Sistema SEO Avançado

**Versão:** 1.0.0  
**Data:** 22 de Janeiro de 2026  
**Duração Total Estimada:** 30 dias úteis  
**Equipe:** 1-2 desenvolvedores

---

## 📊 Visão Geral das Fases

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    ROADMAP DE IMPLEMENTAÇÃO (30 DIAS)                       │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  FASE 1 (Dias 1-7)     FASE 2 (Dias 8-14)    FASE 3 (Dias 15-21)           │
│  ┌─────────────┐      ┌─────────────┐       ┌─────────────┐                │
│  │ FUNDAÇÃO    │ ──▶  │ DISTRIBUIÇÃO│ ──▶   │ DESCRIÇÃO   │                │
│  │ Sinônimos   │      │ Keywords    │       │ Builder     │                │
│  │ E1, E9      │      │ E3, E5, E8  │       │ E6, E7, E11 │                │
│  └─────────────┘      └─────────────┘       └─────────────┘                │
│         │                    │                     │                        │
│         ▼                    ▼                     ▼                        │
│  FASE 4 (Dias 22-26)   FASE 5 (Dias 27-30)                                 │
│  ┌─────────────┐      ┌─────────────┐                                      │
│  │ CAMPOS      │ ──▶  │ INTEGRAÇÃO  │                                      │
│  │ OCULTOS     │      │ DASHBOARD   │                                      │
│  │ E2, E4, E10 │      │ E12, API    │                                      │
│  └─────────────┘      └─────────────┘                                      │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 🌐 Escopo do Sistema

> **IMPORTANTE:** Este sistema é **GENÉRICO** e funciona para **TODAS as categorias** do Mercado Livre, não apenas para Baús/Bagageiros (MLB3530). A categoria MLB3530 é utilizada como **piloto** com dados pré-populados para testes e validação inicial.

### Arquitetura Híbrida de Keywords

O sistema utiliza uma abordagem híbrida de 3 camadas para obter keywords:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    ARQUITETURA HÍBRIDA DE KEYWORDS                          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐         │
│  │  MERCADO LIVRE  │    │   AI / LLM      │    │   DATABASE      │         │
│  │  API (Primário) │    │  (Expansão)     │    │   (Cache)       │         │
│  └────────┬────────┘    └────────┬────────┘    └────────┬────────┘         │
│           │                      │                      │                   │
│           ▼                      ▼                      ▼                   │
│  • Trends API            • Sinônimos         • Hierarquias curadas         │
│  • Autocomplete          • Classificação     • Contextos validados         │
│  • Atributos categoria   • Long Tail         • Histórico performance       │
│  • Análise concorrentes  • Contextos uso     • Keywords rankeadas          │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

| Camada | Fonte | Quando Usar |
|--------|-------|-------------|
| **ML API** | Mercado Livre | Dados frescos de tendências, autocomplete, atributos |
| **AI/LLM** | OpenAI/Claude | Expansão de sinônimos, classificação, geração de contextos |
| **Database** | Cache local | Dados curados, hierarquias validadas, fallback offline |

### Fluxo por Tipo de Categoria

- **Categorias com dados pré-populados** (ex: MLB3530): Usa dados do banco + ML API
- **Novas categorias**: AI gera hierarquia inicial → valida com ML API → salva no banco
- **Fallback**: Se ML API indisponível, usa cache + AI para expansão

---

## 📅 Cronograma Detalhado

| Fase | Dias | Estratégias | Entregáveis | Dependências |
|------|------|-------------|-------------|--------------|
| **1** | 1-7 | E1, E9 | SynonymExpansionService, SemanticScoreService | Nenhuma |
| **2** | 8-14 | E3, E5, E8 | KeywordDistributionService | Fase 1 |
| **3** | 15-21 | E6, E7, E11 | DescriptionBuilderService, ContextInjector | Fases 1, 2 |
| **4** | 22-26 | E2, E4, E10 | HiddenAttributesDetector (expandido), SearchCoverage | Fases 1, 2, 3 |
| **5** | 27-30 | E12 | Dashboard, APIs, Monitoramento | Todas |

---

# 🔵 FASE 1: Fundação - Hierarquia de Sinônimos (Dias 1-7)

## Objetivo
Criar a base do sistema de sinônimos com 4 níveis hierárquicos e score de relevância semântica.

## Estratégias Implementadas
- ✅ **E1**: Hierarquia de Sinônimos
- ✅ **E9**: Score de Relevância Semântica

## Entregáveis

### 1.1 SynonymExpansionService.php

**Localização:** `app/Services/SEO/SynonymExpansionService.php`

**Estrutura:**

```php
<?php

namespace App\Services\SEO;

use App\Database;
use App\Services\MercadoLivreClient;

class SynonymExpansionService
{
    private Database $db;
    private MercadoLivreClient $client;
    
    // Hierarquia padrão (pode ser sobrescrita por categoria)
    private array $defaultHierarchy = [
        'nivel_1_generico' => [
            'weight' => 1.0,
            'destination' => 'title',
            'max_words' => 5
        ],
        'nivel_2_qualificado' => [
            'weight' => 0.8,
            'destination' => 'model',
            'max_words' => 7
        ],
        'nivel_3_contexto' => [
            'weight' => 0.6,
            'destination' => 'model_description',
            'max_words' => 10
        ],
        'nivel_4_long_tail' => [
            'weight' => 0.4,
            'destination' => 'description_keywords',
            'max_words' => 15
        ]
    ];

    public function __construct(?int $accountId = null);
    
    /**
     * Expande sinônimos para um título
     */
    public function expand(string $title, string $categoryId): array;
    
    /**
     * Retorna hierarquia de sinônimos para categoria
     */
    public function getHierarchy(string $categoryId): array;
    
    /**
     * Identifica nível hierárquico de um texto
     */
    public function identifyLevel(string $text): string;
    
    /**
     * Seleciona sinônimos para campo específico
     */
    public function selectForField(string $title, string $field, string $categoryId): array;
    
    /**
     * Gera campo MODELO otimizado
     */
    public function generateOptimizedModel(string $title, string $categoryId): array;
    
    /**
     * Carrega hierarquia do banco de dados
     */
    private function loadHierarchyFromDB(string $categoryId): ?array;
    
    /**
     * Salva hierarquia customizada
     */
    public function saveHierarchy(string $categoryId, array $hierarchy): bool;
    
    /**
     * Gera hierarquia automaticamente para nova categoria (via AI + ML API)
     * Usado quando não há dados pré-populados no banco
     */
    public function generateHierarchyForCategory(string $categoryId): array;
    
    /**
     * Busca sinônimos via ML API (Trends + Autocomplete)
     */
    private function fetchFromMLAPI(string $categoryId, string $baseKeyword): array;
    
    /**
     * Expande sinônimos via AI/LLM
     */
    private function expandViaAI(string $keyword, string $categoryId): array;
}
```

### 1.2 SemanticScoreService.php

**Localização:** `app/Services/SEO/SemanticScoreService.php`

**Estrutura:**

```php
<?php

namespace App\Services\SEO;

class SemanticScoreService
{
    private SynonymExpansionService $synonymService;
    private array $useContexts = [];
    
    public function __construct(?int $accountId = null);
    
    /**
     * Calcula score de relevância semântica
     */
    public function calculateScore(string $word, string $title, string $categoryId): float;
    
    /**
     * Calcula score para lista de palavras
     */
    public function scoreWords(array $words, string $title, string $categoryId): array;
    
    /**
     * Rankeia palavras por score
     */
    public function rankByScore(array $words, string $title, string $categoryId): array;
    
    /**
     * Verifica se palavra tem contexto de uso
     */
    public function hasUseContext(string $word): bool;
    
    /**
     * Retorna contextos disponíveis
     */
    public function getContexts(string $categoryId): array;
    
    /**
     * Componentes do score
     */
    private function getScoreComponents(string $word, string $title): array;
}
```

### 1.3 Migração de Banco de Dados

**Arquivo:** `database/migrations/2026_01_22_create_seo_synonyms_tables.sql`

```sql
-- Tabela de hierarquia de sinônimos
CREATE TABLE IF NOT EXISTS seo_synonym_hierarchy (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id VARCHAR(20) NOT NULL,
    level ENUM('nivel_1', 'nivel_2', 'nivel_3', 'nivel_4') NOT NULL,
    word VARCHAR(100) NOT NULL,
    weight DECIMAL(3,2) DEFAULT 1.00,
    destination ENUM('title', 'model', 'description', 'keywords') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_category_level_word (category_id, level, word),
    INDEX idx_category (category_id),
    INDEX idx_level (level),
    INDEX idx_word (word)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de contextos de uso
CREATE TABLE IF NOT EXISTS seo_use_contexts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id VARCHAR(20) NOT NULL,
    context_type VARCHAR(50) NOT NULL,
    keyword VARCHAR(100) NOT NULL,
    weight DECIMAL(3,2) DEFAULT 1.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_category_context_keyword (category_id, context_type, keyword),
    INDEX idx_category_context (category_id, context_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DADOS PILOTO: Categoria Baús/Bagageiros (MLB3530)
-- NOTA: Estes são dados de exemplo para a categoria piloto.
-- Para outras categorias, o sistema gera automaticamente via AI + ML API.
-- ============================================================================
INSERT INTO seo_synonym_hierarchy (category_id, level, word, weight, destination) VALUES
-- Nível 1 - Genérico (TÍTULO)
('MLB3530', 'nivel_1', 'bauleto', 1.00, 'title'),
('MLB3530', 'nivel_1', 'baú', 1.00, 'title'),
('MLB3530', 'nivel_1', 'bagageiro', 1.00, 'title'),
('MLB3530', 'nivel_1', 'maleiro', 1.00, 'title'),
-- Nível 2 - Qualificado (MODELO)
('MLB3530', 'nivel_2', 'bau traseiro', 0.80, 'model'),
('MLB3530', 'nivel_2', 'porta objetos', 0.80, 'model'),
('MLB3530', 'nivel_2', 'caixa traseira', 0.80, 'model'),
('MLB3530', 'nivel_2', 'compartimento', 0.80, 'model'),
-- Nível 3 - Contexto (MODELO + DESCRIÇÃO)
('MLB3530', 'nivel_3', 'bau moto', 0.60, 'model'),
('MLB3530', 'nivel_3', 'bagageiro motocicleta', 0.60, 'model'),
('MLB3530', 'nivel_3', 'maleiro delivery', 0.60, 'description'),
-- Nível 4 - Long Tail (DESCRIÇÃO + KEYWORDS)
('MLB3530', 'nivel_4', 'bauleto para motoboy', 0.40, 'description'),
('MLB3530', 'nivel_4', 'baú entrega delivery', 0.40, 'keywords'),
('MLB3530', 'nivel_4', 'bagageiro viagem', 0.40, 'description');

-- Contextos de uso
INSERT INTO seo_use_contexts (category_id, context_type, keyword, weight) VALUES
('MLB3530', 'profissional', 'delivery', 1.20),
('MLB3530', 'profissional', 'motoboy', 1.20),
('MLB3530', 'profissional', 'entrega', 1.10),
('MLB3530', 'profissional', 'trabalho', 1.00),
('MLB3530', 'lazer', 'viagem', 1.00),
('MLB3530', 'lazer', 'passeio', 0.90),
('MLB3530', 'lazer', 'turismo', 0.90),
('MLB3530', 'urbano', 'cidade', 0.90),
('MLB3530', 'urbano', 'dia a dia', 0.90),
('MLB3530', 'carga', 'capacete', 1.10),
('MLB3530', 'carga', 'transporte', 1.00);
```

### 1.4 Endpoints da API

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/seo/synonyms/{categoryId}` | Retorna hierarquia de sinônimos |
| POST | `/api/seo/synonyms/expand` | Expande sinônimos para título |
| POST | `/api/seo/synonyms/model` | Gera campo MODELO otimizado |
| POST | `/api/seo/score/calculate` | Calcula score semântico |
| GET | `/api/seo/contexts/{categoryId}` | Retorna contextos de uso |

### 1.5 Testes Unitários

**Arquivo:** `tests/Unit/Services/SEO/SynonymExpansionServiceTest.php`

```php
<?php

namespace Tests\Unit\Services\SEO;

use PHPUnit\Framework\TestCase;
use App\Services\SEO\SynonymExpansionService;

class SynonymExpansionServiceTest extends TestCase
{
    private SynonymExpansionService $service;
    
    protected function setUp(): void
    {
        $this->service = new SynonymExpansionService();
    }
    
    public function testIdentifyLevelGenerico(): void
    {
        $title = "Bauleto 41 Litros Universal";
        $level = $this->service->identifyLevel($title);
        $this->assertEquals('nivel_1', $level);
    }
    
    public function testGenerateOptimizedModel(): void
    {
        $title = "Bauleto Baú 41 Litros Universal";
        $result = $this->service->generateOptimizedModel($title, 'MLB3530');
        
        $this->assertArrayHasKey('model', $result);
        $this->assertArrayHasKey('synonyms_used', $result);
        $this->assertArrayHasKey('score', $result);
        
        // Não deve repetir palavras do título
        $this->assertStringNotContainsString('bauleto', strtolower($result['model']));
        $this->assertStringNotContainsString('baú', strtolower($result['model']));
    }
    
    public function testSelectForFieldModel(): void
    {
        $title = "Bauleto 41L Universal";
        $synonyms = $this->service->selectForField($title, 'model', 'MLB3530');
        
        $this->assertIsArray($synonyms);
        $this->assertLessThanOrEqual(7, count($synonyms));
        
        // Deve retornar sinônimos de níveis 2 e 3
        foreach ($synonyms as $synonym) {
            $this->assertNotEmpty($synonym['word']);
            $this->assertArrayHasKey('score', $synonym);
        }
    }
}
```

## Checklist Fase 1

- [ ] Criar `SynonymExpansionService.php`
- [ ] Criar `SemanticScoreService.php`
- [ ] Executar migração de banco de dados
- [ ] Implementar endpoints da API
- [ ] Escrever testes unitários
- [ ] Documentar métodos
- [ ] Testar com categoria piloto MLB3530 (Baús/Bagageiros)
- [ ] Testar geração automática para outra categoria (ex: MLB1071 - Capacetes)
- [ ] Code review

## Métricas de Sucesso

| Métrica | Alvo |
|---------|------|
| Cobertura de testes | > 80% |
| Tempo de resposta | < 200ms |
| Sinônimos por título | 5-15 |
| Score médio | > 70 |

---

# 🟢 FASE 2: Distribuição de Keywords (Dias 8-14)

## Objetivo
Implementar sistema de distribuição inteligente de keywords por peso de campo.

## Estratégias Implementadas
- ✅ **E3**: Injeção Natural de Keywords
- ✅ **E5**: Peso de Campo por Indexação
- ✅ **E8**: Densidade Controlada

## Dependências
- ✅ Fase 1 completa (SynonymExpansionService, SemanticScoreService)

## Entregáveis

### 2.1 KeywordDistributionService.php

**Localização:** `app/Services/SEO/KeywordDistributionService.php`

**Estrutura:**

```php
<?php

namespace App\Services\SEO;

use App\Services\KeywordResearchService;

class KeywordDistributionService
{
    private SynonymExpansionService $synonymService;
    private SemanticScoreService $scoreService;
    private KeywordResearchService $keywordService;
    
    // Pesos de indexação por campo
    private const FIELD_WEIGHTS = [
        'title' => 100,
        'model' => 70,
        'attributes' => 50,
        'description' => 30,
        'hidden_keywords' => 50
    ];
    
    // Limites por campo
    private const FIELD_LIMITS = [
        'title' => ['min' => 3, 'max' => 5],
        'model' => ['min' => 5, 'max' => 7],
        'attributes' => ['min' => 8, 'max' => 12],
        'description' => ['min' => 15, 'max' => 25],
        'hidden_keywords' => ['min' => 10, 'max' => 15]
    ];
    
    // Densidade ideal
    private const DENSITY_LIMITS = [
        'min' => 0.5,
        'ideal_min' => 1.0,
        'ideal_max' => 2.5,
        'max' => 3.0,
        'critical' => 5.0
    ];

    public function __construct(?int $accountId = null);
    
    /**
     * Distribui keywords por todos os campos
     */
    public function distribute(array $item, string $categoryId): array;
    
    /**
     * Classifica keywords em CORE, SUPORTE, TÉCNICA, CONTEXTO
     */
    public function classifyKeywords(array $keywords): array;
    
    /**
     * Mapeia keywords para campos específicos
     */
    public function mapToFields(array $classified, string $title): array;
    
    /**
     * Valida densidade de keywords
     */
    public function validateDensity(string $text, array $keywords): array;
    
    /**
     * Calcula densidade de uma keyword
     */
    public function calculateDensity(string $text, string $keyword): float;
    
    /**
     * Gera plano de injeção para descrição
     */
    public function generateInjectionPlan(array $keywords, int $targetWordCount): array;
    
    /**
     * Retorna pesos de indexação
     */
    public function getFieldWeights(): array;
}
```

### 2.2 KeywordSourceService.php (Arquitetura Híbrida)

**Localização:** `app/Services/SEO/KeywordSourceService.php`

**Propósito:** Orquestra a obtenção de keywords das 3 fontes (ML API, AI, Database)

```php
<?php

namespace App\Services\SEO;

use App\Services\MercadoLivreClient;
use App\Services\AIService; // OpenAI/Claude

class KeywordSourceService
{
    private MercadoLivreClient $mlClient;
    private AIService $aiService;
    private Database $db;
    
    // Prioridade de fontes
    private const SOURCE_PRIORITY = [
        'database' => 1,  // Cache primeiro (mais rápido)
        'ml_api' => 2,    // ML API segundo (dados frescos)
        'ai' => 3         // AI terceiro (expansão/geração)
    ];

    /**
     * Obtém keywords usando arquitetura híbrida
     */
    public function getKeywords(string $categoryId, string $baseKeyword): array;
    
    /**
     * Busca no cache local primeiro
     */
    private function fetchFromDatabase(string $categoryId): ?array;
    
    /**
     * Busca via ML API (Trends, Autocomplete, Atributos)
     */
    private function fetchFromMLAPI(string $categoryId, string $keyword): array;
    
    /**
     * Gera/expande via AI quando não há dados
     */
    private function generateViaAI(string $categoryId, string $keyword): array;
    
    /**
     * Salva keywords no cache para uso futuro
     */
    private function cacheKeywords(string $categoryId, array $keywords): void;
    
    /**
     * Invalida cache quando dados ficam obsoletos
     */
    public function invalidateCache(string $categoryId): void;
}
```

### 2.3 Expansão do KeywordResearchService

**Arquivo:** `app/Services/KeywordResearchService.php`

**Novos métodos a adicionar:**

```php
/**
 * Classifica keywords por tipo
 * @return array ['core' => [], 'suporte' => [], 'tecnica' => [], 'contexto' => []]
 */
public function classifyByType(array $keywords, string $categoryId): array;

/**
 * Calcula volume de busca estimado
 */
public function estimateSearchVolume(string $keyword, string $categoryId): array;

/**
 * Retorna keywords com score de competição
 */
public function getWithCompetitionScore(array $keywords): array;
```

### 2.4 Endpoints da API

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/api/seo/keywords/distribute` | Distribui keywords por campo |
| POST | `/api/seo/keywords/classify` | Classifica keywords por tipo |
| GET | `/api/seo/keywords/fetch/{categoryId}` | Busca keywords (híbrido) |
| POST | `/api/seo/keywords/generate/{categoryId}` | Gera keywords via AI |
| POST | `/api/seo/density/validate` | Valida densidade de texto |
| POST | `/api/seo/density/calculate` | Calcula densidade |
| GET | `/api/seo/weights` | Retorna pesos de campos |
| DELETE | `/api/seo/keywords/cache/{categoryId}` | Invalida cache de categoria |

### 2.4 Testes Unitários

```php
public function testDistributeKeywords(): void
{
    $item = [
        'title' => 'Bauleto 41L Universal',
        'category_id' => 'MLB3530'
    ];
    
    $result = $this->service->distribute($item, 'MLB3530');
    
    $this->assertArrayHasKey('title', $result);
    $this->assertArrayHasKey('model', $result);
    $this->assertArrayHasKey('attributes', $result);
    $this->assertArrayHasKey('description', $result);
    
    // Verificar limites
    $this->assertLessThanOrEqual(5, count($result['title']['keywords']));
    $this->assertLessThanOrEqual(7, count($result['model']['keywords']));
}

public function testValidateDensity(): void
{
    $text = "Este é um bauleto de 41 litros. O bauleto é universal.";
    $keywords = ['bauleto'];
    
    $result = $this->service->validateDensity($text, $keywords);
    
    $this->assertArrayHasKey('bauleto', $result);
    $this->assertEquals(2, $result['bauleto']['occurrences']);
    $this->assertArrayHasKey('status', $result['bauleto']);
}
```

## Checklist Fase 2

- [ ] Criar `KeywordDistributionService.php`
- [ ] Criar `KeywordSourceService.php` (arquitetura híbrida)
- [ ] Expandir `KeywordResearchService.php`
- [ ] Implementar validação de densidade
- [ ] Implementar classificação de keywords
- [ ] Configurar integração com AI Service (OpenAI/Claude)
- [ ] Criar endpoints da API
- [ ] Escrever testes unitários
- [ ] Testar fallback quando ML API indisponível
- [ ] Integrar com Fase 1
- [ ] Code review

## Métricas de Sucesso

| Métrica | Alvo |
|---------|------|
| Distribuição correta | 100% |
| Densidade ideal | 0.5-3% |
| Classificação precisa | > 90% |
| Cobertura de testes | > 80% |

---

# 🟡 FASE 3: Construtor de Descrição (Dias 15-21)

## Objetivo
Implementar sistema de geração de descrição otimizada com 4 blocos estruturados.

## Estratégias Implementadas
- ✅ **E6**: Contextos de Uso
- ✅ **E7**: Long Tail Automático
- ✅ **E11**: FAQ Otimizado

## Dependências
- ✅ Fase 1 (SynonymExpansionService)
- ✅ Fase 2 (KeywordDistributionService)

## Entregáveis

### 3.1 DescriptionBuilderService.php

**Localização:** `app/Services/SEO/DescriptionBuilderService.php`

**Estrutura:**

```php
<?php

namespace App\Services\SEO;

class DescriptionBuilderService
{
    private SynonymExpansionService $synonymService;
    private KeywordDistributionService $distributionService;
    private ContextInjectorService $contextService;
    
    // Estrutura de blocos
    private const BLOCKS = [
        'beneficios' => [
            'position' => 1,
            'word_count' => [100, 150],
            'keywords_priority' => 'alta',
            'format' => 'bullets'
        ],
        'especificacoes' => [
            'position' => 2,
            'word_count' => [80, 120],
            'keywords_priority' => 'media',
            'format' => 'list'
        ],
        'compatibilidade' => [
            'position' => 3,
            'word_count' => [60, 100],
            'keywords_priority' => 'baixa',
            'format' => 'list'
        ],
        'faq' => [
            'position' => 4,
            'word_count' => [100, 150],
            'keywords_priority' => 'media',
            'format' => 'qa'
        ]
    ];

    public function __construct(?int $accountId = null);
    
    /**
     * Constrói descrição completa otimizada
     */
    public function build(array $item, array $distribution): array;
    
    /**
     * Gera bloco específico
     */
    public function generateBlock(string $blockType, array $item, array $keywords): string;
    
    /**
     * Gera bloco de benefícios
     */
    private function generateBenefitsBlock(array $item, array $keywords): string;
    
    /**
     * Gera bloco de especificações
     */
    private function generateSpecsBlock(array $item, array $keywords): string;
    
    /**
     * Gera bloco de compatibilidade
     */
    private function generateCompatibilityBlock(array $item, array $keywords): string;
    
    /**
     * Gera bloco de FAQ
     */
    private function generateFAQBlock(array $item, array $keywords): string;
    
    /**
     * Injeta keywords naturalmente no texto
     */
    public function injectKeywords(string $text, array $keywords): string;
    
    /**
     * Valida descrição completa
     */
    public function validateDescription(string $description): array;
    
    /**
     * Calcula score da descrição
     */
    public function calculateDescriptionScore(string $description, array $keywords): int;
}
```

### 3.2 ContextInjectorService.php

**Localização:** `app/Services/SEO/ContextInjectorService.php`

```php
<?php

namespace App\Services\SEO;

class ContextInjectorService
{
    private const CONTEXTS = [
        'profissional' => [
            'keywords' => ['delivery', 'motoboy', 'entrega', 'trabalho'],
            'phrases' => [
                'Perfeito para motoboy e entrega delivery',
                'Ideal para profissionais que fazem entregas',
                'Resistente para uso profissional diário'
            ]
        ],
        'lazer' => [
            'keywords' => ['viagem', 'passeio', 'turismo'],
            'phrases' => [
                'Ideal para viagens e passeios',
                'Perfeito para turismo e aventuras'
            ]
        ],
        // ... mais contextos
    ];

    /**
     * Injeta contextos no texto
     */
    public function inject(string $text, array $contexts): string;
    
    /**
     * Detecta contextos aplicáveis
     */
    public function detectApplicableContexts(array $item): array;
    
    /**
     * Gera frases de contexto
     */
    public function generateContextPhrases(string $context, array $item): array;
}
```

### 3.3 LongTailGeneratorService.php

**Localização:** `app/Services/SEO/LongTailGeneratorService.php`

```php
<?php

namespace App\Services\SEO;

class LongTailGeneratorService
{
    /**
     * Gera keywords long tail automaticamente
     */
    public function generate(string $title, string $categoryId): array;
    
    /**
     * Gera combinações com capacidade
     */
    private function generateCapacityCombinations(string $type, string $capacity): array;
    
    /**
     * Gera combinações com compatibilidade
     */
    private function generateCompatibilityCombinations(string $type, array $compatibilities): array;
    
    /**
     * Gera combinações com contexto
     */
    private function generateContextCombinations(string $type, array $contexts): array;
}
```

### 3.4 Templates de FAQ

**Arquivo:** `config/seo_faq_templates.php`

```php
<?php

return [
    'MLB3530' => [ // Baús e Bagageiros
        [
            'question' => 'Esse {produto} cabe capacete?',
            'answer' => 'Sim! O {produto} comporta 1 capacete fechado tamanho 60.',
            'keywords' => ['capacete', 'cabe']
        ],
        [
            'question' => 'É resistente para delivery?',
            'answer' => 'Sim! Material ABS resistente, ideal para motoboy e entrega delivery.',
            'keywords' => ['resistente', 'delivery', 'motoboy']
        ],
        [
            'question' => 'Serve para viagem?',
            'answer' => 'Perfeito para viagens! {produto} espaçoso e seguro.',
            'keywords' => ['viagem', 'espaçoso', 'seguro']
        ],
        [
            'question' => 'É universal?',
            'answer' => 'Sim! Compatível com Honda CG, Yamaha Fazer e mais de 50 modelos.',
            'keywords' => ['universal', 'compatível', 'honda', 'yamaha']
        ],
        [
            'question' => 'Vem com base de fixação?',
            'answer' => 'Verifique nas especificações se inclui base. Disponível separadamente.',
            'keywords' => ['base', 'fixação', 'instalação']
        ]
    ]
];
```

### 3.5 Endpoints da API

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/api/seo/description/build` | Gera descrição completa |
| POST | `/api/seo/description/block` | Gera bloco específico |
| POST | `/api/seo/description/faq` | Gera FAQ otimizado |
| POST | `/api/seo/description/validate` | Valida descrição |
| GET | `/api/seo/contexts/{categoryId}` | Retorna contextos disponíveis |
| POST | `/api/seo/longtail/generate` | Gera keywords long tail |

## Checklist Fase 3

- [ ] Criar `DescriptionBuilderService.php`
- [ ] Criar `ContextInjectorService.php`
- [ ] Criar `LongTailGeneratorService.php`
- [ ] Criar templates de FAQ
- [ ] Implementar endpoints da API
- [ ] Integrar com Fases 1 e 2
- [ ] Escrever testes unitários
- [ ] Code review

## Métricas de Sucesso

| Métrica | Alvo |
|---------|------|
| Score descrição | > 85 |
| Palavras | 400-600 |
| Blocos gerados | 4 |
| FAQ perguntas | 5-8 |
| Cobertura testes | > 80% |

---

# 🟠 FASE 4: Campos Ocultos e Cobertura (Dias 22-26)

## Objetivo
Expandir detecção de campos ocultos e implementar análise de cobertura de buscas.

## Estratégias Implementadas
- ✅ **E2**: Campos Ocultos Indexados
- ✅ **E4**: Cobertura de Tipos de Busca
- ✅ **E10**: Compatibilidade Expandida

## Dependências
- ✅ Fases 1, 2, 3 completas

## Entregáveis

### 4.1 Expansão do HiddenAttributesDetector.php

**Novos métodos a adicionar:**

```php
/**
 * Detecta e sugere preenchimento de campos KEYWORDS, MPN, LINE
 */
public function detectKeywordFields(string $itemId): array;

/**
 * Gera valor para campo KEYWORDS
 */
public function generateKeywordsFieldValue(string $title, array $synonyms): string;

/**
 * Gera valor para campo MPN
 */
public function generateMPNValue(array $item): string;

/**
 * Gera valor para campo LINE
 */
public function generateLineValue(array $item): string;

/**
 * Aplica campos ocultos via API
 */
public function applyHiddenFields(string $itemId, array $fields): array;
```

### 4.2 SearchCoverageService.php

**Localização:** `app/Services/SEO/SearchCoverageService.php`

```php
<?php

namespace App\Services\SEO;

class SearchCoverageService
{
    private const SEARCH_TYPES = [
        'generica' => ['weight' => 30, 'field' => 'title'],
        'qualificada' => ['weight' => 25, 'field' => 'title_model'],
        'long_tail' => ['weight' => 20, 'field' => 'description'],
        'marca_modelo' => ['weight' => 15, 'field' => 'attributes_description'],
        'filtros' => ['weight' => 10, 'field' => 'attributes']
    ];

    /**
     * Analisa cobertura de tipos de busca
     */
    public function analyzeCoverage(array $item): array;
    
    /**
     * Calcula score de cobertura
     */
    public function calculateCoverageScore(array $coverage): int;
    
    /**
     * Identifica gaps de cobertura
     */
    public function identifyGaps(array $coverage): array;
    
    /**
     * Sugere melhorias para cobertura
     */
    public function suggestImprovements(array $gaps): array;
}
```

### 4.3 CompatibilityService.php

**Localização:** `app/Services/SEO/CompatibilityService.php`

```php
<?php

namespace App\Services\SEO;

class CompatibilityService
{
    private const MOTO_BRANDS = [
        'honda' => ['CG 160', 'Titan', 'Fan', 'Bros', 'CB 300', 'CB 500', 'XRE 300', 'Pop'],
        'yamaha' => ['Factor', 'Fazer', 'XTZ', 'Lander', 'MT-03', 'Crosser', 'Neo'],
        'suzuki' => ['Yes', 'Intruder', 'GSX-S', 'V-Strom', 'Burgman'],
        'dafra' => ['Apache', 'Riva', 'Next', 'Speed', 'Citycom'],
        'kawasaki' => ['Ninja', 'Z400', 'Versys', 'Vulcan']
    ];

    /**
     * Retorna lista de compatibilidade para categoria
     */
    public function getCompatibilityList(string $categoryId): array;
    
    /**
     * Gera texto de compatibilidade
     */
    public function generateCompatibilityText(array $compatibilities): string;
    
    /**
     * Detecta compatibilidade do título
     */
    public function detectFromTitle(string $title): array;
}
```

### 4.4 Endpoints da API

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/seo/hidden-fields/{itemId}` | Detecta campos ocultos |
| POST | `/api/seo/hidden-fields/generate` | Gera valores para campos |
| POST | `/api/seo/hidden-fields/apply` | Aplica campos ocultos |
| GET | `/api/seo/coverage/{itemId}` | Analisa cobertura de buscas |
| GET | `/api/seo/coverage/gaps/{itemId}` | Identifica gaps |
| GET | `/api/seo/compatibility/{categoryId}` | Lista compatibilidades |

## Checklist Fase 4

- [ ] Expandir `HiddenAttributesDetector.php`
- [ ] Criar `SearchCoverageService.php`
- [ ] Criar `CompatibilityService.php`
- [ ] Implementar endpoints da API
- [ ] Escrever testes unitários
- [ ] Integrar com fases anteriores
- [ ] Code review

## Métricas de Sucesso

| Métrica | Alvo |
|---------|------|
| Campos ocultos detectados | > 5 |
| Cobertura de buscas | > 80% |
| Compatibilidades listadas | > 20 |
| Cobertura testes | > 80% |

---

# 🔴 FASE 5: Integração e Dashboard (Dias 27-30)

## Objetivo
Integrar todas as fases, criar fluxo unificado e implementar monitoramento.

## Estratégias Implementadas
- ✅ **E12**: Atualização Contínua

## Dependências
- ✅ Todas as fases anteriores

## Entregáveis

### 5.1 SEOStrategiesEngine.php (Orquestrador)

**Localização:** `app/Services/SEO/SEOStrategiesEngine.php`

```php
<?php

namespace App\Services\SEO;

class SEOStrategiesEngine
{
    private SynonymExpansionService $synonymService;
    private KeywordDistributionService $distributionService;
    private DescriptionBuilderService $descriptionService;
    private HiddenAttributesDetector $hiddenFieldsService;
    private SearchCoverageService $coverageService;
    private SemanticScoreService $scoreService;
    
    /**
     * Executa otimização completa (12 estratégias)
     */
    public function optimizeFull(string $itemId): array;
    
    /**
     * Executa otimização parcial (estratégias selecionadas)
     */
    public function optimizePartial(string $itemId, array $strategies): array;
    
    /**
     * Gera preview de otimização (sem aplicar)
     */
    public function previewOptimization(string $itemId): array;
    
    /**
     * Aplica otimizações via API do ML
     */
    public function applyOptimization(string $itemId, array $optimizations): array;
    
    /**
     * Calcula score SEO geral
     */
    public function calculateOverallScore(array $analysis): int;
    
    /**
     * Gera relatório de otimização
     */
    public function generateReport(string $itemId, array $results): array;
}
```

### 5.2 SEOMonitoringService.php

**Localização:** `app/Services/SEO/SEOMonitoringService.php`

```php
<?php

namespace App\Services\SEO;

class SEOMonitoringService
{
    /**
     * Coleta métricas de performance
     */
    public function collectMetrics(string $itemId): array;
    
    /**
     * Compara com período anterior
     */
    public function compareWithPrevious(string $itemId, int $days = 7): array;
    
    /**
     * Identifica oportunidades de melhoria
     */
    public function identifyOpportunities(string $itemId): array;
    
    /**
     * Agenda verificação automática
     */
    public function scheduleCheck(string $itemId, int $intervalDays = 7): void;
    
    /**
     * Executa otimização automática
     */
    public function runAutoOptimization(string $itemId): array;
    
    /**
     * Gera alerta de queda de posição
     */
    public function generateAlert(string $itemId, string $alertType): void;
}
```

### 5.3 Controller Unificado

**Arquivo:** `app/Controllers/Api/SeoStrategiesController.php`

```php
<?php

namespace App\Controllers\Api;

use App\Services\SEO\SEOStrategiesEngine;
use App\Services\SEO\SEOMonitoringService;

class SeoStrategiesController
{
    private SEOStrategiesEngine $engine;
    private SEOMonitoringService $monitoring;
    
    // Otimização completa
    public function optimizeFull(string $itemId): void;
    
    // Otimização parcial
    public function optimizePartial(string $itemId): void;
    
    // Preview sem aplicar
    public function preview(string $itemId): void;
    
    // Aplicar otimizações
    public function apply(string $itemId): void;
    
    // Dashboard de métricas
    public function dashboard(string $itemId): void;
    
    // Histórico de otimizações
    public function history(string $itemId): void;
    
    // Agendar monitoramento
    public function scheduleMonitoring(string $itemId): void;
}
```

### 5.4 Interface do Dashboard

**View:** `app/Views/dashboard/seo/strategies.php`

**Componentes:**

1. **Painel de Score**
   - Score SEO geral (0-100)
   - Score por componente (título, modelo, descrição, etc.)
   - Comparação com período anterior

2. **Preview de Otimização**
   - Antes vs Depois lado a lado
   - Highlight das mudanças
   - Score estimado após otimização

3. **Seletor de Estratégias**
   - Checkboxes para selecionar estratégias
   - Descrição de cada estratégia
   - Impacto estimado

4. **Histórico**
   - Timeline de otimizações
   - Métricas de performance
   - Alertas e recomendações

### 5.5 Jobs de Background

**Arquivo:** `app/Jobs/SEOMonitoringJob.php`

```php
<?php

namespace App\Jobs;

class SEOMonitoringJob
{
    /**
     * Executa verificação semanal de todos os anúncios
     */
    public function runWeeklyCheck(): void;
    
    /**
     * Processa fila de otimizações automáticas
     */
    public function processAutoOptimizationQueue(): void;
    
    /**
     * Envia alertas de queda de posição
     */
    public function sendPositionAlerts(): void;
}
```

### 5.6 Endpoints Finais da API

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/api/seo/strategies/optimize/full/{itemId}` | Otimização completa |
| POST | `/api/seo/strategies/optimize/partial/{itemId}` | Otimização parcial |
| GET | `/api/seo/strategies/preview/{itemId}` | Preview de otimização |
| POST | `/api/seo/strategies/apply/{itemId}` | Aplicar otimizações |
| GET | `/api/seo/strategies/score/{itemId}` | Score SEO atual |
| GET | `/api/seo/strategies/history/{itemId}` | Histórico de otimizações |
| POST | `/api/seo/monitoring/schedule/{itemId}` | Agendar monitoramento |
| GET | `/api/seo/monitoring/metrics/{itemId}` | Métricas de performance |

## Checklist Fase 5

- [ ] Criar `SEOStrategiesEngine.php`
- [ ] Criar `SEOMonitoringService.php`
- [ ] Criar `SeoStrategiesController.php`
- [ ] Criar interface do dashboard
- [ ] Implementar jobs de background
- [ ] Criar rotas finais
- [ ] Testes de integração
- [ ] Documentação de API
- [ ] Code review final
- [ ] Deploy em staging
- [ ] Testes de aceitação

## Métricas de Sucesso

| Métrica | Alvo |
|---------|------|
| Todas estratégias integradas | 12/12 |
| Tempo de otimização completa | < 5s |
| Score médio após otimização | > 85 |
| Cobertura de testes | > 80% |
| Documentação completa | 100% |

---

# 📊 Resumo Executivo

## Timeline

```
Semana 1 (Dias 1-7):   FASE 1 - Fundação (Sinônimos + Score)
Semana 2 (Dias 8-14):  FASE 2 - Distribuição (Keywords + Densidade)
Semana 3 (Dias 15-21): FASE 3 - Descrição (Builder + FAQ)
Semana 4 (Dias 22-26): FASE 4 - Campos Ocultos + Cobertura
Semana 4 (Dias 27-30): FASE 5 - Integração + Dashboard
```

## Recursos Necessários

| Recurso | Quantidade |
|---------|------------|
| Desenvolvedor PHP Senior | 1 |
| Desenvolvedor Frontend | 0.5 |
| QA | 0.5 |
| DevOps (deploy) | 0.25 |

## Arquivos a Criar

| Fase | Arquivos Novos | Arquivos a Expandir |
|------|----------------|---------------------|
| 1 | 2 services + 1 migration | 0 |
| 2 | 2 services (Distribution + Source) | 1 service |
| 3 | 3 services + 1 config | 0 |
| 4 | 2 services | 1 service |
| 5 | 3 services + 1 controller + 1 view + 1 job | 0 |
| **Total** | **13 novos** | **2 expandidos** |

## Riscos e Mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|-------|---------------|---------|-----------|
| API ML indisponível | Baixa | Alto | Cache + retry |
| Performance lenta | Média | Médio | Otimização + cache |
| Dados de sinônimos insuficientes | Média | Médio | Crowdsourcing + AI |
| Integração complexa | Média | Médio | Testes E2E |

## Próximos Passos

1. ✅ Aprovação do plano
2. ⏳ Iniciar Fase 1
3. ⏳ Setup de ambiente de desenvolvimento
4. ⏳ Criar branch `feature/seo-strategies-v2`

---

**Documento criado em:** 22 de Janeiro de 2026  
**Última atualização:** 22 de Janeiro de 2026  
**Próxima revisão:** Após conclusão de cada fase
