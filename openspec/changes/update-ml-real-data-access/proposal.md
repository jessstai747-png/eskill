# Change: Update Mercado Livre real data access (API-first, no fake fallback)

## Why
Hoje, alguns módulos não “puxam anúncios reais” porque o cliente do Mercado Livre bloqueia chamadas quando não há token e/ou devolve dados de fallback (ex.: “Produto de Exemplo”). Isso cria resultados não confiáveis e contraria o objetivo de operação **real** em produção.

## What Changes
- O sistema SHALL operar em modo **API-first** para Mercado Livre.
- Endpoints **públicos** (ex.: busca, autosuggest, detalhes de item, atributos de categoria) SHALL funcionar sem token, usando chamadas reais do ML.
- Endpoints **autenticados** (ex.: dados de conta, escrita/alterações) SHALL exigir conta vinculada e token válido.
- O sistema SHALL **não** retornar dados fictícios (“fallback fake”) em produção.
- Em ambientes de teste (APP_ENV=testing), chamadas de rede SHALL ser desabilitadas por padrão para manter a suíte determinística; quando necessário, o teste SHALL ser explicitamente opt-in.

## Impact
- Affected code: `app/Services/MercadoLivreClient.php` e serviços consumidores (SEO, keyword, competitor, item listing).
- Potential breaking behavior: chamadas que antes retornavam dados fictícios podem passar a retornar erro quando a rede não estiver habilitada (principalmente em testes) ou quando o token não estiver configurado (endpoints autenticados).
