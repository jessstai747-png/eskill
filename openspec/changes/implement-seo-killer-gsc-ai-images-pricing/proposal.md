# Change: Implement SEO Killer GSC, AI Images & AI Pricing with real data

## Why
SEO Killer hoje depende de mocks/parciais para Google Search Console, análise de imagens e pricing. Isso impede validação com dados reais e quebra a experiência do dashboard.

## What Changes
- Conectar o SEO Killer ao Google Search Console com OAuth2 e status de conexão por conta.
- Expor métricas reais de Search Console (cliques, impressões, CTR, posição média, queries) para o dashboard SEO Killer.
- Alinhar rotas de AI Images com o front-end e usar dados reais do Mercado Livre para analisar, reordenar, remover e subir imagens.
- Atualizar AI Pricing para usar preço atual e histórico reais, competidores e demanda, removendo dados mockados.
- Manter compatibilidade com os endpoints já existentes de SEO/AI sempre que possível.

## Impact
- **Capability**: SEO (SEO Killer – GSC, imagens e pricing)
- **Services**: `GoogleSearchConsoleService`, `AIImageAnalyzer`, `ImageKiller`, `AIPricingOptimizer`, `MercadoLivreClient`
- **Controllers**: `SEOKillerController`, `AIController`
- **Views**: `gsc-dashboard-tab.php`, `ai-image-analyzer.php`
- **API**: rotas `/api/seo-killer/gsc/*` e `/api/ai/images/*`, `/api/ai/pricing/*` com dados reais
