# Market Data API - Documentação

APIs de dados reais de mercado do Mercado Livre implementadas no sistema.

## Endpoints Disponíveis

### Análise de Mercado
| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `/api/market/analyze/{categoryId}` | GET | Análise completa de mercado para uma categoria |
| `/api/market/category/{categoryId}` | GET | Detalhes da categoria (nome, path, atributos) |
| `/api/market/pricing/{categoryId}` | GET | Análise de preços (min, max, média, mediana) |
| `/api/market/competitors/{categoryId}` | GET | Análise de concorrentes |
| `/api/market/trends/{categoryId}` | GET | Tendências e keywords relacionadas |
| `/api/market/filters/{categoryId}` | GET | Filtros disponíveis na categoria |

### Pesquisa e Discovery
| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `/api/market/discover?q=keyword` | GET | Descobrir categorias relacionadas |
| `/api/market/autocomplete?q=text` | GET | Autocomplete de busca |
| `/api/market/search?q=keyword&category_id=MLB123` | GET | Buscar produtos |

### Análise de Item
| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `/api/market/quality/{itemId}` | GET | Análise de qualidade do anúncio (score 0-100) |
| `/api/market/item/{itemId}` | GET | Detalhes de um item específico |
| `/api/market/similar` | POST | Encontrar produtos similares (body: title, category_id) |

### Preços e Sugestões
| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `/api/market/suggest-price` | POST | Sugestão de preço baseado no mercado |

### Informações de Categoria
| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `/api/market/attributes/{categoryId}` | GET | Atributos da categoria |
| `/api/market/children/{categoryId}` | GET | Subcategorias |
| `/api/market/requirements/{categoryId}` | GET | Requisitos de atributos (obrigatórios/opcionais) |

### Estatísticas
| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `/api/market/stats` | GET | Estatísticas gerais do inventário |

## Fallbacks Implementados

Quando a API do ML está bloqueada (403), o sistema usa:
1. **Pricing**: Dados locais da tabela `items`
2. **Quality**: Dados locais + análise offline
3. **Trends**: Domain discovery + atributos da categoria

## Exemplo de Uso

```javascript
// Análise de mercado
fetch('/api/market/analyze/MLB22687')
  .then(r => r.json())
  .then(data => console.log(data));

// Análise de qualidade
fetch('/api/market/quality/MLB3851912577')
  .then(r => r.json())
  .then(data => {
    console.log('Score:', data.overall_score);
    console.log('Issues:', data.issues);
  });

// Sugestão de preço
fetch('/api/market/suggest-price', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    category_id: 'MLB22687',
    title: 'Retrovisor Moto Universal',
    keyword: 'retrovisor'
  })
}).then(r => r.json()).then(console.log);
```

## Scores de Qualidade

O score de qualidade analisa:
- **Título** (30 pts): comprimento, keywords, caracteres especiais
- **Descrição** (20 pts): tamanho, estrutura, parágrafos
- **Imagens** (20 pts): quantidade, qualidade
- **Atributos** (15 pts): obrigatórios preenchidos
- **Frete** (10 pts): frete grátis, Full
- **Preço** (5 pts): promoção ativa

---
*Atualizado em: $(date +%Y-%m-%d)*
