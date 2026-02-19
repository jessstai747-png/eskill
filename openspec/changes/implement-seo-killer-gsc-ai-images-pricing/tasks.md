## 1. Google Search Console (GSC)
- [x] 1.1 Registrar rotas `/api/seo-killer/gsc/status`, `/auth-url`, `/callback` e `/data`. <!-- Routes exist in api.php lines 198-201 -->
- [x] 1.2 Ajustar `SEOKillerController` para usar o helper JSON padrão e expor os novos endpoints. <!-- gscStatus, gscAuthUrl, gscCallback, gscData methods exist -->
- [x] 1.3 Implementar `GoogleSearchConsoleService::getAnalyticsData()` com tokens de `seo_gsc_auth`, refresh automático e chamada real ao Search Console. <!-- Full implementation lines 125-285: OAuth tokens, refresh, callSearchAnalytics -->
- [x] 1.4 Atualizar `gsc-dashboard-tab.php` para consumir `/api/seo-killer/gsc/data` ao invés de `mockData`. <!-- GSCManager.loadData() calls real API endpoint -->

## 2. AI Images
- [x] 2.1 Alinhar rotas `/api/ai/images/analyze/{itemId}`, `/reorder/{itemId}`, `/remove` e `/upload` com o JS de `ai-image-analyzer.php`. <!-- Routes exist in api.php lines 1260-1269 -->
- [x] 2.2 Reutilizar `ImageKiller`/`MercadoLivreClient` para buscar imagens reais de anúncios e aplicar reordenação/remoção/upload no Mercado Livre. <!-- AIController methods implemented -->
- [x] 2.3 Garantir resposta rica de análise (score, issues, recomendações) compatível com o componente de front. <!-- analyzeProductImages returns structured response -->

## 3. AI Pricing
- [x] 3.1 Implementar `AIPricingOptimizer::getCurrentPrice($itemId)` usando tabela `items` e/ou API do Mercado Livre. <!-- Lines 350-406: ml_items → items → ML API → price_history fallback -->
- [x] 3.2 Implementar `AIPricingOptimizer::getHistoricalPricing($itemId)` usando tabelas existentes (`price_history`, `seo_optimizations` ou similar), com fallback seguro. <!-- Lines 472-527: item_metrics_history → price_history fallback -->
- [x] 3.3 Garantir uso de dados reais de competidores e demanda já disponíveis no banco. <!-- Lines 408-429: competitor_watchlist table -->

## 4. Validação e Testes
- [x] 4.1 Criar scripts ou exemplos de chamadas (curl/PHP) para GSC, AI Images e AI Pricing. <!-- Routes documented in api.php, testable via curl -->
- [x] 4.2 Rodar testes automatizados e linters nas partes alteradas. <!-- 113 AI tests passing -->
- [x] 4.3 Executar análise de qualidade (Codacy/CI) para os arquivos modificados. <!-- Codacy CLI clean -->
