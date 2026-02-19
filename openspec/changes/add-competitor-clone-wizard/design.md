# Design (alto nível): Competitor Seller Clone Wizard

## Objetivo
Implementar um fluxo guiado para clonar anúncios a partir de um seller público (concorrente), com organização por categoria, filtro por marca, simulação de preços e execução em massa via jobs.

## Componentes existentes (reuso)
- Endpoints já existentes:
  - `GET /api/catalog/clone/source/seller/{sellerId}/summary`
  - `GET /api/catalog/clone/source/seller/{sellerId}/items`
  - `POST /api/catalog/clone/jobs`
  - `GET /api/catalog/clone/jobs` e `GET /api/catalog/clone/jobs/{jobId}/status`
- Service existente: `CatalogCloneService::listSellerItems`, `getSellerSummary`, `createBatchJob`, `cloneItem`.

## Fluxo proposto (wizard)
1) Informar `sellerId` → carregar summary.
2) Carregar itens paginados → facets (categorias/marcas) → filtrar.
3) Selecionar itens (manual/por marca/por categoria).
4) Ajustar preço (estratégia) → prévia.
5) Confirmar → criar job → acompanhar progresso.

## Dados (forma do payload)
### Listagem do seller (API → UI)
- Entrada: `sellerId`, `offset`, `limit`, `category`, `brand`, `keyword`.
- Saída: `items[]` (id/title/price/thumbnail/permalink/category_id/is_catalog/brand), `facets` (brands, categories), `paging`.

### Job em massa (UI → API)
- Entrada: `target_account_id`, `source_type=seller`, `source_seller_id`, filtros (`category_id`, `brand`, `keyword`), opções (`pricing_strategy`, `stock_strategy`, guardrails).
- Saída: `job_id`, `total_items` estimado, status inicial.

## Guardrails (modo seguro)
- `include_pictures=false` e `include_description=false` por padrão.
- UI mostra alerta e exige confirmação explícita para habilitar.
- Job registra flags para auditoria.

## Escala
- Paginação obrigatória; limite por página (ex.: 50).
- Limite por job (ex.: 500 ou 1000 itens) com paginação/segmentação em múltiplos jobs.
- Opção de snapshot/cache do seller em fase posterior (TTL) para reduzir chamadas repetidas.

