# Fase 4 — Predictive Analytics & Intelligence: Implementação de Stubs Remanescentes

## Contexto do Projeto

Sistema PHP 8.0+ de gestão multi-conta no Mercado Livre com arquitetura MVC customizada. Fases 1–3 concluíram ~345 stubs em 13 services. Esta Fase 4 finaliza os **últimos 64 stubs** do projeto, distribuídos em 3 services de analytics/AI.

## Objetivo

Implementar todos os métodos stub restantes com lógica real, sem alterar assinaturas públicas existentes. Cada método deve conter: queries SQL com prepared statements, tratamento de erros via try/catch, cálculos baseados em dados reais do banco, e retornos consistentes com a estrutura esperada pelos chamadores.

---

## Sprint 1 — `app/Services/AI/SEO/PredictiveAnalyticsService.php` (24 stubs)

**Arquivo**: 506 linhas | **Namespace**: `App\Services\AI\SEO` | **Dependências**: PDO (`$this->db`), CacheService (`$this->cache`), `$this->accountId`

### Padrão dos stubs (linhas 482–505)
Todos são one-liners `private function xxx(...) { return []; }` ou `{ return 0.8; }` que precisam de implementação real.

### Bloco 1 — SEO & Pricing Helpers (5 métodos)
| Método | Assinatura | Chamado por | Implementação esperada |
|--------|-----------|-------------|----------------------|
| `identifyMarketWarnings` | `(array $analysis): array` | `analyzeMarketTrends()` L103 | Analisar `$analysis['trend']`, `$analysis['volatility']` e gerar alertas (ex: queda de demanda >15%, aumento concorrência) |
| `getPricingHistory` | `(string $itemId, int $days): array` | `predictOptimalPricing()` L103 | Query `price_history` ou `competitor_price_history` filtrando por item e período |
| `predictImprovementTimeline` | `(array $improvements): array` | `predictSEOImprovement()` L152 | Gerar timeline estimada (dias) por tipo de melhoria (título=7d, descrição=14d, imagens=21d) |
| `predictSEOImpact` | `(array $improvements, array $current): array` | `predictSEOImprovement()` L152 | Estimar impacto em score SEO, posição ranking, e conversão por tipo de otimização |
| `prioritizeActions` | `(array $improvements): array` | `predictSEOImprovement()` L152 | Ordenar melhorias por ROI estimado (impacto / esforço) |

### Bloco 2 — Scalars Hardcoded → Cálculos Reais (5 métodos)
| Método | Return atual | Implementação esperada |
|--------|-------------|----------------------|
| `calculateTrendConfidence(array $analysis): float` | `0.8` | Calcular baseado em volume de dados, consistência do trend, desvio padrão |
| `predictCategoryGrowth(array $analysis): float` | `5.2` | Calcular % crescimento baseado em trend slope, comparação período anterior |
| `calculatePriority(array $improvements, array $impact): string` | `'medium'` | Retornar `critical/high/medium/low` baseado em impacto × urgência |
| `calculateSEOConfidence(array $current, array $improvements): float` | `0.75` | Calcular baseado em qualidade dos dados, histórico de acerto, completude |
| `calculateSeasonalConfidence(array $patterns): float` | `0.7` | Calcular baseado em amplitude, R² do pattern, volume de dados históricos |

### Bloco 3 — Sazonalidade (7 métodos)
| Método | Assinatura | Implementação esperada |
|--------|-----------|----------------------|
| `getSeasonalData` | `(string $categoryId, int $months): array` | Query `seo_performance_metrics` + `ml_orders`/`order_items` agrupados por mês/semana |
| `identifySeasonalPatterns` | `(array $data): array` | Detectar picos (>1.5σ da média), vales, e periodicidade (semanal/mensal/anual) |
| `predictUpcomingSeasonal` | `(array $patterns): array` | Projetar próximos picos/vales baseado nos patterns detectados |
| `generateSeasonalRecommendations` | `(array $opportunities): array` | Gerar ações preparatórias (estoque, preço, anúncios) por oportunidade sazonal |
| `findNextPeak` | `(array $patterns): array` | Identificar próximo pico sazonal com data estimada e magnitude |
| `generatePreparationTimeline` | `(array $opportunities): array` | Timeline de preparação: 30d antes = estoque, 14d = preço, 7d = anúncios |

### Bloco 4 — Account Health & Strategy (7 métodos)
| Método | Assinatura | Implementação esperada |
|--------|-----------|----------------------|
| `calculateAccountHealth` | `(array $items): array` | Score 0–100 baseado em: % ativos, score SEO médio, taxa conversão, reputação |
| `forecastAccountPerformance` | `(array $items): array` | Projeção 30/60/90 dias de vendas, receita, crescimento baseado em trend |
| `identifyTopOpportunities` | `(array $items): array` | Top-10 itens com maior potencial de melhoria (SEO gap + volume busca) |
| `generateMarketPredictions` | `(array $items): array` | Previsões de mercado por categoria atuante (crescimento, concorrência, preço) |
| `generateStrategicRecommendations` | `(array $items): array` | Recomendações estratégicas priorizadas (pricing, SEO, expansão, estoque) |
| `assessAccountRisks` | `(array $items): array` | Riscos: estoque baixo, preço acima mercado, SEO fraco, categorias saturadas |
| `calculateGrowthPotential` | `(array $items): array` | Potencial de crescimento por produto/categoria com score e ações sugeridas |
| `generateActionPlan` | `(array $items): array` | Plano semanal priorizado: imediato/curto prazo/longo prazo |

---

## Sprint 2 — `app/Services/MercadoLivre/MLAnalyticsIntelligenceService.php` (18 stubs)

**Arquivo**: ~1313 linhas | **Namespace**: `App\Services\MercadoLivre` | **Dependências**: PDO (`$this->db`), MercadoLivreClient (`$this->mlClient`), CacheService (`$this->cache`), `$this->accountId`  
**Testes existentes**: `tests/Unit/Services/MLAnalyticsIntelligenceServiceTest.php` (atualizar se necessário)

### Bloco 1 — Search Analytics (7 stubs, linhas 1052–1075)
| Método | Assinatura | Implementação esperada |
|--------|-----------|----------------------|
| `getSearchSuccessRates` | `(array $searchData): array` | Calcular taxa de busca→clique, busca→venda por termo. Usar dados de `$searchData` |
| `getSearchByDevice` | `(array $searchData): array` | Classificar buscas por device (mobile/desktop/tablet) de `$searchData` ou `ml_items` |
| `getSearchByLocation` | `(array $searchData): array` | Agregar buscas por região/estado usando dados do array |
| `getSearchByTime` | `(array $searchData): array` | Distribuição horária/diária de buscas |
| `getAbandonedSearches` | `(array $searchData): array` | Buscas sem conversão — identificar termos com alto volume e baixa venda |
| `segmentSearchUsers` | `(array $searchData): array` | Segmentar por comportamento: exploradores, compradores diretos, comparadores |
| `analyzeSearchFunnel` | `(array $searchData): array` | Funil: busca → visualização → pergunta → compra |

### Bloco 2 — Funnel & Attribution (7 stubs, linhas 877–977)
| Método | Assinatura | Implementação esperada |
|--------|-----------|----------------------|
| `getSegmentJourneys` | `(array $filters): array` | Jornadas por segmento de cliente (novo/recorrente/VIP) via `ml_orders` |
| `getSegmentFunnels` | `(array $config): array` | Funis separados por segmento com taxas de conversão |
| `getProductFunnels` | `(array $config): array` | Funil por produto individual via `ml_items` + `ml_orders` |
| `performFunnelAttribution` | `(array $config): array` | Atribuição de receita por etapa do funil |
| `calculateOptimizationImpact` | `(array $config): array` | Estimar impacto de otimizações no funil (ex: +10% CTR = +X vendas) |
| `performMultiTouchAttribution` | `(array $config): array` | Modelo de atribuição multi-touch (primeiro/último/linear/time-decay) |
| `getChannelPerformance` | `(array $config): array` | Performance por canal (orgânico, ads, compartilhamento) |

### Bloco 3 — Budget & ROI (4 stubs, linhas 951–991)
| Método | Assinatura | Implementação esperada |
|--------|-----------|----------------------|
| `compareAttributionModels` | `(array $config): array` | Comparar resultados entre modelos (first-touch vs last-touch vs linear) |
| `generateBudgetOptimization` | `(array $config): array` | Otimizar distribuição de budget por canal baseado em ROI |
| `extractAttributionInsights` | `(array $attributionData): array` | Extrair insights acionáveis dos dados de atribuição |
| `generateBudgetRecommendations` | `(array $attributionData): array` | Gerar recomendações de realocação de budget |

---

## Sprint 3 — `app/Services/AIPredictiveAnalyticsService.php` (22 stubs mock)

**Arquivo**: 1564 linhas | **Namespace**: `App\Services` | **Dependências**: PDO (`$this->db`), LLMService, CacheService, vários modelos ML internos  
**Nota**: Stubs retornam dados mock plausíveis (não vazios). Substituir por cálculos reais.

### Bloco 1 — Product Lifecycle (6 stubs, linhas 1345–1350)
| Método | Return mock atual | Implementação real |
|--------|------------------|-------------------|
| `identifyCurrentStage($product, $historical): string` | `'growth'` | Classificar por volume de vendas trend: launch (1ºs 30d), growth (vendas ↑), maturity (estável), decline (↓) |
| `predictStageTransitions($stage, $historical): array` | `['next_stage' => 'maturity']` | Prever transição baseado em velocidade de mudança, comparação com produtos similares |
| `getLifecycleStrategies($stage, $transitions): array` | `['focus' => 'market_expansion']` | Estratégias por estágio: launch=visibilidade, growth=escala, maturity=defesa, decline=liquidação |
| `calculateStageConfidence($stage, $historical): float` | `0.82` | Confiança do estágio baseada em volume dados e clareza do trend |
| `generateLifecycleTimeline($transitions): array` | `['phases' => [...]]` | Timeline com datas estimadas para cada transição futura |
| `identifyLifecycleRisks($stage, $transitions): array` | `['risk' => 'market_saturation']` | Riscos por estágio: concorrência, saturação, obsolescência, sazonalidade |

### Bloco 2 — Risk Analysis (10 stubs, linhas 1353–1362)
| Método | Return mock atual | Implementação real |
|--------|------------------|-------------------|
| `analyzeMarketRisks($product, $context): array` | `score: 0.3` | Risco de mercado: tendência categoria, volume busca, novos entrantes |
| `analyzeCompetitiveRisks($product, $context): array` | `score: 0.4` | Risco competitivo: gap preço, quantidade concorrentes, posição ranking |
| `analyzeSupplyChainRisks($product, $historical): array` | `score: 0.2` | Risco supply chain: variância estoque, frequência ruptura, lead time |
| `analyzeRegulatoryRisks($product): array` | `score: 0.1` | Risco regulatório: categoria restrita, requisitos INMETRO, compliance |
| `analyzeEconomicRisks($context): array` | `score: 0.3` | Risco econômico: inflação, câmbio, poder de compra (heurísticas fixas) |
| `analyzeSeasonalRisks($product, $historical): array` | `score: 0.25` | Risco sazonal: dependência de datas, amplitude sazonal, previsibilidade |
| `calculateOverallRisk($risks): float` | Não listado (verificar) | Weighted average dos scores de risco individuais |
| `generateMitigationPlan($risks): array` | `['actions' => [...]]` | Plano de mitigação priorizado por score de risco |
| `getRiskLevel($score): string` | Não listado (verificar) | `critical/high/medium/low` baseado em thresholds do score |
| `generateRiskAlerts($risks): array` | `['alert' => '...']` | Alertas acionáveis para riscos acima de threshold |

### Bloco 3 — Prediction Helpers (6 stubs, linhas 595–758)
| Método | Return mock atual | Implementação real |
|--------|------------------|-------------------|
| `getMarketContext($product): array` | `['trend' => 'positive']` | Query competitor_items + ml_orders para contexto real de mercado |
| `calculatePredictionConfidence($predictions, $historical): array` | `['overall' => 0.82]` | Calcular baseado em concordância entre modelos, volume dados, variância |
| `generatePredictiveInsights($consolidated, $confidence): array` | `['key_insight' => '...']` | Gerar insights categorizados e priorizados dos dados consolidados |
| `generatePredictiveRecommendations($consolidated, $insights): array` | `['action' => '...']` | Recomendações acionáveis com impacto estimado |
| `getExternalFactors($categoryId): array` | `['seasonality' => 0.1]` | Índice sazonal real do mês + trend da categoria |
| `identifyInfluenceFactors($categoryId, $historical): array` | `['price' => 0.8]` | Correlação real entre fatores (preço, estoque, SEO) e vendas |
| `generateScenarios($forecast, $factors): array` | `['optimistic' => 120]` | 3 cenários (otimista/base/pessimista) com ±1σ e ±2σ |
| `evaluateModelPerformance($models, $historical): array` | `['mape' => 5.2]` | Calcular MAPE, RMSE, MAE reais entre predições e dados reais |

---

## Regras de Implementação

### Obrigatórias
1. **Não alterar assinaturas** de métodos existentes (public ou private)
2. **Prepared statements** com `:param` para todas as queries SQL
3. **try/catch** em todo método que acessa DB ou API externa
4. **`$this->accountId`** em toda query que filtra por conta
5. **Retorno consistente**: arrays vazios `[]` em catch blocks, nunca `null` onde array esperado
6. **Nenhum `return []` stub** deve permanecer — implementar lógica real
7. Schema correto: `ml_items.id` (PK), `ml_items.available_quantity` (não `stock`), sem coluna `cost` (usar `price * 0.6`)

### Schema das Tabelas Relevantes
```sql
-- ml_items: id, account_id, title, sku, category_id, price, currency_id, 
--           available_quantity, sold_quantity, status, permalink, thumbnail, 
--           raw_data JSON, last_synced_at, created_at, updated_at
-- ml_orders: ml_order_id, ml_account_id, account_id, buyer_id, total_amount, 
--            status, date_created, category_id
-- order_items: order_id, item_id, title, quantity, unit_price, category_id, sku
-- competitor_items: id, account_id, ml_item_id, seller_id, title, price, 
--                   original_price, status, available_quantity, sold_quantity, 
--                   category_id, my_item_id, created_at, updated_at
-- competitor_price_history: id, competitor_item_id, price, min_price, max_price, 
--                          last_price, stock, snapshot_count, recorded_at
-- price_history: id, category_id, brand, avg_price, min_price, max_price, 
--               total_items, recorded_at
-- seo_performance_metrics: (verificar schema antes de implementar)
-- ml_ad_performance: id, campaign_id, date, impressions, clicks, cost, 
--                    revenue, conversions, roas, created_at
```

### Validação por Sprint
Para cada service implementado:
1. `get_errors()` no arquivo — 0 erros novos
2. Grep por stubs remanescentes — 0 matches
3. Codacy CLI analyze — 0 issues
4. Atualizar/criar testes unitários em `tests/Unit/Services/`

---

## Entregáveis

- [ ] 24 stubs implementados em `AI/SEO/PredictiveAnalyticsService.php`
- [ ] 18 stubs implementados em `MercadoLivre/MLAnalyticsIntelligenceService.php`
- [ ] 22 stubs implementados em `AIPredictiveAnalyticsService.php`
- [ ] Testes unitários para os 3 services (verificar/atualizar existentes)
- [ ] 0 erros de compilação, 0 stubs remanescentes
- [ ] Codacy CLI clean em todos os arquivos editados

**Total: 64 stubs → 0 | Execute até 100%**
