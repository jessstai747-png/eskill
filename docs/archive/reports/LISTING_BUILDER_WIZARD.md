# 📝 Listing Builder Wizard - Construtor Inteligente de Anúncios

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Componentes do Sistema](#componentes-do-sistema)
3. [Fluxo do Wizard](#fluxo-do-wizard)
4. [API Reference](#api-reference)
5. [Templates de Descrição](#templates-de-descrição)
6. [Guia de Uso](#guia-de-uso)
7. [Exemplos Práticos](#exemplos-práticos)
8. [Boas Práticas](#boas-práticas)
9. [Referências ML](#referências-ml)

---

## 🎯 Visão Geral

O **Listing Builder Wizard** é um sistema completo para criação, otimização e publicação de anúncios no Mercado Livre. Integra validação em tempo real, análise SEO, otimização de frete, templates profissionais e predição de Quality Score.

### ✨ Características Principais

- **Wizard Step-by-Step**: 8 etapas com validação em tempo real
- **Integração Total**: Quality Check + SEO + Shipping Optimizer
- **Templates Profissionais**: 5 templates HTML prontos para uso
- **Market Insights**: Dados de mercado e competidores
- **Auto-Optimization**: Otimizações automáticas de título, frete e descrição
- **Draft Management**: Salve e continue depois
- **Clone Inteligente**: Clone anúncios com melhorias automáticas
- **Predição de Score**: Veja o Quality Score antes de publicar

### 💪 Benefícios

✅ **Redução de erros** - Validação em cada step do wizard  
✅ **Maior conversão** - Otimizações baseadas em ML guidelines  
✅ **Economia de tempo** - Templates e auto-completions  
✅ **Melhor ranking** - SEO e shipping otimizados automaticamente  
✅ **Visibilidade total** - Score e sugestões em tempo real  

---

## 🧩 Componentes do Sistema

### 1. ListingBuilderService

**Responsabilidade**: Orquestra todo o fluxo de criação de anúncios.

**Funcionalidades**:
- Inicia wizard com dados de categoria e mercado
- Valida cada step com errors/warnings/suggestions
- Constrói anúncio completo com otimizações
- Publica no ML API com verificação de qualidade
- Gerencia rascunhos (save/load)
- Clona anúncios com melhorias

**Integrações**:
```
ListingBuilderService
├── MercadoLivreClient (API calls)
├── CategoryService (dados de categoria)
├── SeoAnalyzerService (análise de SEO)
├── QualityScoreService (predição de score)
├── ValidationService (validação de dados)
├── ShippingOptimizerService (estratégia de frete)
├── DimensionCalculatorService (validação de dimensões)
└── TemplateManagerService (templates HTML)
```

**Métodos Principais**:

```php
// Iniciar wizard
public function startListing(array $data = []): array

// Validar step específico
public function validateStep(array $data, string $step): array

// Construir anúncio completo
public function buildListing(array $data): array

// Publicar no ML
public function publishListing(array $listingData, array $options = []): array

// Gerenciar rascunhos
public function saveDraft(array $data, string $name = ''): array
public function loadDraft(string $draftId): array

// Clonar com melhorias
public function cloneListing(string $itemId, array $improvements = []): array
```

---

### 2. TemplateManagerService

**Responsabilidade**: Gerencia templates HTML para descrições.

**Funcionalidades**:
- 5 templates profissionais prontos
- Sistema de variáveis `{{variable}}`
- Blocos reutilizáveis (specs, features, warranty)
- Criação de templates personalizados
- Renderização com dados reais
- Filtragem por categoria

**Templates Disponíveis**:

| ID | Nome | Estilo | Melhor Para |
|----|------|--------|-------------|
| `modern` | Modern | Clean, minimalista | Eletrônicos, tech |
| `classic` | Classic | Profissional, estruturado | Todos produtos |
| `minimal` | Minimal | Simples, direto | Produtos básicos |
| `professional` | Professional | Formal, empresarial | B2B, equipamentos |
| `ecommerce` | E-commerce | Recursos destacados | Varejo, moda |

**Sistema de Variáveis**:
```html
<h1>{{product_name}}</h1>
<p>{{description}}</p>

<!-- Arrays automáticos -->
{{features}}  → Lista de bullets
{{specs}}     → Tabela de especificações
{{includes}}  → Lista do que está incluso
```

---

## 🚀 Fluxo do Wizard

### Etapas do Wizard

```
1. BASIC_INFO    → Categoria, produto, marca, modelo
   ↓
2. TITLE         → Título otimizado (SEO)
   ↓
3. DESCRIPTION   → Descrição com template
   ↓
4. ATTRIBUTES    → Atributos obrigatórios + GTIN
   ↓
5. IMAGES        → Mínimo 6 imagens (1200x1200)
   ↓
6. PRICING       → Preço com análise de mercado
   ↓
7. SHIPPING      → Estratégia otimizada (ME2/Flex/Full)
   ↓
8. REVIEW        → Revisão final + Quality Score
   ↓
   PUBLISH!
```

### Estrutura de Validação (Por Step)

Cada step retorna:

```json
{
  "step": "title",
  "valid": true,
  "score": 85,
  "errors": [],
  "warnings": ["Título poderia ser mais específico"],
  "suggestions": [
    "Adicione o modelo do produto",
    "Use 45-58 caracteres para melhor SEO"
  ],
  "next_step": "description",
  "data": {...}
}
```

**Scores por Step**:
- **0-49**: ❌ Bloqueante - não pode avançar
- **50-69**: ⚠️ Atenção - pode avançar com avisos
- **70-84**: ✅ Bom - pode avançar
- **85-100**: 🌟 Excelente - otimizado

---

## 📡 API Reference

### Base URL
```
/api/listing-builder
```

---

### 1. Iniciar Wizard

**Endpoint**: `POST /api/listing-builder/start`

**Request Body**:
```json
{
  "category_id": "MLB1234",
  "product_name": "iPhone 15 Pro"
}
```

**Response**:
```json
{
  "wizard_id": "wizard_abc123",
  "current_step": "basic_info",
  "category_info": {
    "id": "MLB1234",
    "name": "Celulares e Smartphones",
    "required_attributes": ["BRAND", "MODEL", "GTIN"],
    "listing_type_id": "gold_premium"
  },
  "market_insights": {
    "avg_price": 7299.90,
    "top_keywords": ["iphone 15 pro", "256gb", "titanio"],
    "free_shipping_percent": 95,
    "full_ads_percent": 78
  },
  "next_step": "basic_info"
}
```

---

### 2. Validar Step

**Endpoint**: `POST /api/listing-builder/validate/{step}`

**Steps válidos**: `basic_info`, `title`, `description`, `attributes`, `images`, `pricing`, `shipping`, `review`

**Request Body** (exemplo para `title`):
```json
{
  "title": "iPhone 15 Pro Max 256GB Titânio Natural Apple"
}
```

**Response**:
```json
{
  "step": "title",
  "valid": true,
  "score": 92,
  "errors": [],
  "warnings": [],
  "suggestions": [
    "Excelente! Título otimizado para SEO",
    "Comprimento ideal: 48 caracteres"
  ],
  "next_step": "description",
  "optimizations": {
    "seo_keywords": ["iPhone 15 Pro Max", "256GB", "Titanio"],
    "keyword_position": "beginning",
    "length_optimal": true
  }
}
```

**Response com Erros**:
```json
{
  "step": "images",
  "valid": false,
  "score": 35,
  "errors": [
    "Mínimo 6 imagens obrigatório (atual: 3)",
    "Imagem 1 com resolução baixa (800x800, mínimo 1200x1200)"
  ],
  "warnings": [
    "Adicione mais ângulos do produto"
  ],
  "suggestions": [
    "Use fundo branco para melhor destaque",
    "Inclua foto do produto embalado"
  ],
  "next_step": null
}
```

---

### 3. Construir Anúncio

**Endpoint**: `POST /api/listing-builder/build`

**Request Body**:
```json
{
  "title": "iPhone 15 Pro Max 256GB Titanio Natural Apple",
  "category_id": "MLB1234",
  "price": 7299.90,
  "condition": "new",
  "available_quantity": 10,
  "description": "iPhone 15 Pro Max com chip A17 Pro...",
  "pictures": [
    {"source": "https://..."},
    {"source": "https://..."}
  ],
  "attributes": [
    {"id": "BRAND", "value_name": "Apple"},
    {"id": "MODEL", "value_name": "iPhone 15 Pro Max"},
    {"id": "GTIN", "value_name": "0195949038266"}
  ],
  "shipping": {
    "mode": "me2",
    "free_shipping": true,
    "dimensions": "15x7x1",
    "weight": 221
  }
}
```

**Response**:
```json
{
  "success": true,
  "listing_data": {
    "title": "iPhone 15 Pro Max 256GB Titanio Natural Apple",
    "category_id": "MLB1234",
    "price": 7299.90,
    "listing_type_id": "gold_premium",
    "condition": "new",
    "available_quantity": 10,
    "buying_mode": "buy_it_now",
    "description": {
      "plain_text": "...",
      "html": "..."
    },
    "pictures": [...],
    "attributes": [...],
    "shipping": {
      "mode": "me2",
      "free_shipping": true,
      "dimensions": "15x7x1",
      "weight": 221
    }
  },
  "quality_prediction": {
    "score": 88,
    "title_score": 92,
    "description_score": 85,
    "attributes_score": 95,
    "images_score": 90,
    "shipping_score": 85,
    "estimated_ranking": "top_20_percent"
  },
  "optimizations_applied": [
    "Título otimizado para SEO (keywords no início)",
    "Estratégia de frete: ME2 com frete grátis",
    "Template 'modern' aplicado na descrição",
    "Atributos obrigatórios completos + GTIN"
  ],
  "ready_to_publish": true
}
```

---

### 4. Publicar Anúncio

**Endpoint**: `POST /api/listing-builder/publish`

**Request Body**:
```json
{
  "listing_data": {
    "title": "...",
    "category_id": "...",
    ...
  },
  "options": {
    "validate_before_publish": true,
    "auto_activate": true
  }
}
```

**Response (Sucesso)**:
```json
{
  "success": true,
  "item_id": "MLB1234567890",
  "permalink": "https://produto.mercadolivre.com.br/...",
  "quality_score": 88,
  "shipping_strategy": "me2_free",
  "estimated_views": "high",
  "status": "active"
}
```

**Response (Erro)**:
```json
{
  "success": false,
  "error": "Validation failed",
  "validation_errors": [
    "GTIN inválido",
    "Imagem 3 não atende requisitos mínimos"
  ],
  "quality_score": 62,
  "can_publish": false
}
```

---

### 5. Salvar Rascunho

**Endpoint**: `POST /api/listing-builder/draft/save`

**Request Body**:
```json
{
  "data": {
    "title": "...",
    "category_id": "...",
    ...
  },
  "draft_name": "iPhone 15 Pro Rascunho 1"
}
```

**Response**:
```json
{
  "success": true,
  "draft_id": "draft_xyz789",
  "saved_at": "2024-01-15T10:30:00Z"
}
```

---

### 6. Carregar Rascunho

**Endpoint**: `GET /api/listing-builder/draft/{draftId}`

**Response**:
```json
{
  "success": true,
  "draft_id": "draft_xyz789",
  "data": {...},
  "saved_at": "2024-01-15T10:30:00Z",
  "last_step": "pricing"
}
```

---

### 7. Clonar Anúncio

**Endpoint**: `POST /api/listing-builder/clone`

**Request Body**:
```json
{
  "item_id": "MLB1234567890",
  "improvements": [
    "optimize_title",
    "optimize_shipping",
    "apply_template",
    "enhance_seo"
  ]
}
```

**Response**:
```json
{
  "success": true,
  "original_item": {
    "id": "MLB1234567890",
    "title": "iPhone 15 Pro Max",
    "quality_score": 72
  },
  "cloned_data": {
    "title": "iPhone 15 Pro Max 256GB Titanio Natural Apple",
    "description": "...",
    ...
  },
  "improvements_applied": [
    "Título otimizado: +15 caracteres de SEO",
    "Frete: ME2 → Full (ranking +8%)",
    "Template Modern aplicado na descrição",
    "Keywords de alta conversão adicionadas"
  ],
  "quality_prediction": 89,
  "improvement": "+17 pontos"
}
```

---

### 8. Listar Templates

**Endpoint**: `GET /api/listing-builder/templates?category_id=MLB1234`

**Response**:
```json
{
  "success": true,
  "templates": [
    {
      "id": "modern",
      "name": "Modern",
      "description": "Design limpo e minimalista",
      "preview_url": "...",
      "variables": ["product_name", "features", "specs"],
      "categories": ["MLB1234", "MLB5678"]
    },
    ...
  ]
}
```

---

### 9. Renderizar Template

**Endpoint**: `POST /api/listing-builder/templates/{templateId}/render`

**Request Body**:
```json
{
  "product_name": "iPhone 15 Pro Max",
  "features": [
    "Chip A17 Pro",
    "Câmera 48MP",
    "Titanium Design"
  ],
  "specs": {
    "Marca": "Apple",
    "Modelo": "iPhone 15 Pro Max",
    "Memória": "256GB"
  },
  "warranty": "12 meses de garantia oficial Apple"
}
```

**Response**:
```json
{
  "success": true,
  "rendered_html": "<div class='product-description'>...</div>"
}
```

---

### 10. Criar Template Personalizado

**Endpoint**: `POST /api/listing-builder/templates/custom`

**Request Body**:
```json
{
  "name": "Meu Template Tech",
  "description": "Template para produtos de tecnologia",
  "content": "<div>{{product_name}}</div>...",
  "categories": ["MLB1234", "MLB5678"]
}
```

**Response**:
```json
{
  "success": true,
  "template_id": "custom_abc123",
  "created_at": "2024-01-15T10:30:00Z"
}
```

---

### 11. Listar Blocos Reutilizáveis

**Endpoint**: `GET /api/listing-builder/blocks`

**Response**:
```json
{
  "success": true,
  "blocks": {
    "specs_table": "<table>...</table>",
    "features_list": "<ul>...</ul>",
    "warranty": "<div class='warranty'>...</div>",
    "shipping_info": "<div class='shipping'>...</div>",
    "contact": "<div class='contact'>...</div>"
  }
}
```

---

## 🎨 Templates de Descrição

### Template Modern

**Características**:
- Design limpo e minimalista
- Tipografia moderna
- Espaçamento generoso
- Ideal para: Eletrônicos, tech, produtos premium

**Estrutura**:
```html
<div class="modern-template">
  <h1>{{product_name}}</h1>
  
  <section class="highlights">
    <h2>Destaques</h2>
    {{features}}
  </section>
  
  <section class="specs">
    <h2>Especificações</h2>
    {{specs}}
  </section>
  
  <section class="includes">
    <h2>O que está incluído</h2>
    {{includes}}
  </section>
</div>
```

---

### Template Classic

**Características**:
- Profissional e estruturado
- Seções bem definidas
- Fácil leitura
- Ideal para: Todos produtos, uso geral

---

### Template Minimal

**Características**:
- Simples e direto
- Menos elementos visuais
- Informação objetiva
- Ideal para: Produtos básicos, descrições curtas

---

### Template Professional

**Características**:
- Formal e empresarial
- Credibilidade
- Detalhamento técnico
- Ideal para: B2B, equipamentos profissionais

---

### Template E-commerce

**Características**:
- Destaque visual para features
- Call-to-actions
- Badges e selos
- Ideal para: Varejo, moda, produtos populares

---

## 📚 Guia de Uso

### Fluxo Completo: Do Zero à Publicação

#### 1. Iniciar Wizard

```bash
curl -X POST http://localhost:8000/api/listing-builder/start \
  -H "Content-Type: application/json" \
  -d '{
    "category_id": "MLB1234",
    "product_name": "iPhone 15 Pro"
  }'
```

#### 2. Preencher Basic Info e Validar

```bash
curl -X POST http://localhost:8000/api/listing-builder/validate/basic_info \
  -H "Content-Type: application/json" \
  -d '{
    "category_id": "MLB1234",
    "product_name": "iPhone 15 Pro Max",
    "brand": "Apple",
    "model": "iPhone 15 Pro Max",
    "condition": "new"
  }'
```

#### 3. Otimizar Título

```bash
curl -X POST http://localhost:8000/api/listing-builder/validate/title \
  -H "Content-Type: application/json" \
  -d '{
    "title": "iPhone 15 Pro Max 256GB Titanio Natural Apple"
  }'
```

#### 4. Gerar Descrição com Template

```bash
# Listar templates
curl http://localhost:8000/api/listing-builder/templates?category_id=MLB1234

# Renderizar template
curl -X POST http://localhost:8000/api/listing-builder/templates/modern/render \
  -H "Content-Type: application/json" \
  -d '{
    "product_name": "iPhone 15 Pro Max",
    "features": ["Chip A17 Pro", "Câmera 48MP", "Titanium Design"],
    "specs": {
      "Marca": "Apple",
      "Modelo": "iPhone 15 Pro Max",
      "Memória": "256GB",
      "Cor": "Titanio Natural"
    }
  }'
```

#### 5. Validar Descrição

```bash
curl -X POST http://localhost:8000/api/listing-builder/validate/description \
  -H "Content-Type: application/json" \
  -d '{
    "description": "<div>iPhone 15 Pro Max com Chip A17 Pro...</div>"
  }'
```

#### 6. Adicionar Atributos

```bash
curl -X POST http://localhost:8000/api/listing-builder/validate/attributes \
  -H "Content-Type: application/json" \
  -d '{
    "attributes": [
      {"id": "BRAND", "value_name": "Apple"},
      {"id": "MODEL", "value_name": "iPhone 15 Pro Max"},
      {"id": "GTIN", "value_name": "0195949038266"},
      {"id": "INTERNAL_MEMORY", "value_name": "256 GB"}
    ]
  }'
```

#### 7. Validar Imagens

```bash
curl -X POST http://localhost:8000/api/listing-builder/validate/images \
  -H "Content-Type: application/json" \
  -d '{
    "pictures": [
      {"source": "https://exemplo.com/img1.jpg"},
      {"source": "https://exemplo.com/img2.jpg"},
      {"source": "https://exemplo.com/img3.jpg"},
      {"source": "https://exemplo.com/img4.jpg"},
      {"source": "https://exemplo.com/img5.jpg"},
      {"source": "https://exemplo.com/img6.jpg"}
    ]
  }'
```

#### 8. Definir Preço

```bash
curl -X POST http://localhost:8000/api/listing-builder/validate/pricing \
  -H "Content-Type: application/json" \
  -d '{
    "price": 7299.90,
    "available_quantity": 10
  }'
```

#### 9. Otimizar Estratégia de Frete

```bash
curl -X POST http://localhost:8000/api/listing-builder/validate/shipping \
  -H "Content-Type: application/json" \
  -d '{
    "shipping": {
      "dimensions": "15x7x1",
      "weight": 221,
      "zip_code": "01310-100"
    }
  }'
```

#### 10. Revisar e Construir

```bash
curl -X POST http://localhost:8000/api/listing-builder/build \
  -H "Content-Type: application/json" \
  -d '{
    "title": "iPhone 15 Pro Max 256GB Titanio Natural Apple",
    "category_id": "MLB1234",
    "price": 7299.90,
    "condition": "new",
    "available_quantity": 10,
    "description": "<div>...</div>",
    "pictures": [...],
    "attributes": [...],
    "shipping": {...}
  }'
```

#### 11. Publicar

```bash
curl -X POST http://localhost:8000/api/listing-builder/publish \
  -H "Content-Type: application/json" \
  -d '{
    "listing_data": {...},
    "options": {
      "validate_before_publish": true,
      "auto_activate": true
    }
  }'
```

---

### Fluxo Alternativo: Clone com Melhorias

```bash
# Clone anúncio existente com otimizações automáticas
curl -X POST http://localhost:8000/api/listing-builder/clone \
  -H "Content-Type: application/json" \
  -d '{
    "item_id": "MLB1234567890",
    "improvements": [
      "optimize_title",
      "optimize_shipping",
      "apply_template",
      "enhance_seo"
    ]
  }'

# Response inclui dados clonados + melhorias
# Use o listing_data para publicar
curl -X POST http://localhost:8000/api/listing-builder/publish \
  -H "Content-Type: application/json" \
  -d '{
    "listing_data": {...}  # do clone response
  }'
```

---

### Gerenciar Rascunhos

```bash
# Salvar rascunho a qualquer momento
curl -X POST http://localhost:8000/api/listing-builder/draft/save \
  -H "Content-Type: application/json" \
  -d '{
    "data": {
      "title": "...",
      "category_id": "...",
      ...
    },
    "draft_name": "iPhone 15 Rascunho 1"
  }'

# Carregar rascunho depois
curl http://localhost:8000/api/listing-builder/draft/{draft_id}
```

---

## 💡 Boas Práticas

### 1. Validação Step-by-Step

✅ **FAÇA**: Valide cada step antes de avançar
❌ **NÃO FAÇA**: Pule etapas de validação

```php
// BOM: Valida cada step
$basicInfo = $builder->validateStep($data, 'basic_info');
if (!$basicInfo['valid']) {
    // Corrigir erros antes de avançar
}

$title = $builder->validateStep($titleData, 'title');
// ...
```

### 2. Use Market Insights

✅ **FAÇA**: Considere dados de mercado no `startListing()`
❌ **NÃO FAÇA**: Ignore insights de preço e keywords

```php
$wizard = $builder->startListing(['category_id' => 'MLB1234']);
$avgPrice = $wizard['market_insights']['avg_price'];
$topKeywords = $wizard['market_insights']['top_keywords'];

// Use para precificar e otimizar título
```

### 3. Templates Personalizados

✅ **FAÇA**: Crie templates para categorias específicas
❌ **NÃO FAÇA**: Use sempre o mesmo template

```php
// Template específico para eletrônicos
$template = $templateManager->createCustomTemplate([
    'name' => 'Electronics Pro',
    'content' => '...',  // HTML otimizado
    'categories' => ['MLB1234', 'MLB5678']
]);
```

### 4. Otimizações Automáticas

✅ **FAÇA**: Confie nas otimizações do `buildListing()`
❌ **NÃO FAÇA**: Desabilite otimizações sem motivo

```php
$result = $builder->buildListing($data);
// Sistema já aplicou:
// - SEO title optimization
// - Best shipping strategy
// - Template rendering
// - Quality score prediction
```

### 5. Clone Inteligente

✅ **FAÇA**: Use clone para criar variações
❌ **NÃO FAÇA**: Copie manualmente anúncios

```php
// Clone com melhorias automáticas
$cloned = $builder->cloneListing('MLB123', [
    'optimize_title',
    'optimize_shipping',
    'apply_template'
]);

// Anúncio melhorado pronto para publicar
```

---

## 📖 Referências ML

### Guias Oficiais do Mercado Livre

1. **Quality Guidelines**
   - https://developers.mercadolivre.com.br/pt_br/quality-guidelines
   - Critérios de qualidade de anúncios

2. **Items API**
   - https://developers.mercadolivre.com.br/pt_br/itens-e-buscas
   - Publicação e gestão de anúncios

3. **Categories API**
   - https://developers.mercadolivre.com.br/pt_br/categorias-e-atributos
   - Categorias e atributos obrigatórios

4. **Shipping Preferences**
   - https://developers.mercadolivre.com.br/pt_br/preferencias-de-envio
   - Configuração de modos de envio

5. **Picture Requirements**
   - https://developers.mercadolivre.com.br/pt_br/imagens
   - Especificações de imagens

### Boas Práticas de SEO ML

- **Título**: 45-58 caracteres, keywords no início
- **Atributos**: Todos obrigatórios + BRAND, MODEL, GTIN
- **Imagens**: Mínimo 6, resolução 1200x1200+
- **Descrição**: Mínimo 500 caracteres, estruturada
- **Frete**: Grátis + Full = ranking superior

---

## 🎯 Checklist de Publicação

Antes de publicar, verifique:

- [ ] Título otimizado (45-58 caracteres)
- [ ] Categoria correta
- [ ] Todos atributos obrigatórios preenchidos
- [ ] GTIN válido (EAN/UPC)
- [ ] Mínimo 6 imagens (1200x1200+)
- [ ] Descrição com 500+ caracteres
- [ ] Descrição com template HTML
- [ ] Preço competitivo (veja market insights)
- [ ] Estratégia de frete otimizada
- [ ] Frete grátis habilitado (se viável)
- [ ] Quality Score > 80
- [ ] Revisão final sem erros

---

## 📞 Suporte

**Documentação**: `/docs/LISTING_BUILDER_WIZARD.md`  
**Exemplos**: `/examples/listing_builder_example.php`  
**Testes**: `/examples/listing_builder_test.sh`  

---

**Desenvolvido com ❤️ para otimização de vendas no Mercado Livre**
