# Change: Add Competitor Seller Clone Wizard (por fases)

## Why
Hoje o módulo de clonagem já suporta clonagem por item, por lista de IDs e possui endpoints para listar anúncios por `sellerId` público. Porém falta um fluxo end-to-end “seleciona loja concorrente → organiza por categoria → filtra por marca → simula ajuste de preço → clona em massa”, com controle de escala, idempotência e guardrails para minimizar riscos de compliance.

## What Changes
- Adicionar um fluxo guiado (wizard) no dashboard de clonagem para origem por `sellerId` público.
- Expor dados organizados e filtráveis para seleção em massa:
  - Agrupamento por categoria (`category_id`).
  - Facets/contagens de marca (`BRAND`).
  - Busca por palavra-chave.
- Adicionar “prévia de preço” e aplicação de estratégia antes do disparo do clone em massa.
- Criar job em massa (“job pai”) para clonagem, com itens filhos e acompanhamento de status.
- Adicionar **guardrails** (modo seguro por padrão):
  - Clonar apenas dados públicos/estruturais necessários para criar anúncios.
  - Conteúdos potencialmente autorais (ex.: descrição e imagens) NÃO são copiados automaticamente por padrão; requerem confirmação explícita/flag e ficam visivelmente marcados.

## Impact
- **Capability**: Catalog Clone (origem por seller público + seleção em massa)
- **API**: `CatalogCloneController` (novos endpoints/filtros e melhorias de payload)
- **Services**: `CatalogCloneService` (facets por categoria/marca, paginação e snapshot opcional), `CloneTemplateService` (regras), `PricingStrategyService` (simulação)
- **Workers**: `catalog-clone-worker` e `clone-post-actions-worker` (volume maior, melhorias de rate-limit/backoff)
- **DB**: reuso de `catalog_clone_jobs` e `catalog_clone_job_items`; fases avançadas podem incluir tabela de snapshot/cache do seller.

## Non-Goals
- “Copiar tudo 1:1” (incluindo conteúdos autorais) como comportamento padrão.
- Bypassar restrições do Mercado Livre (atributos obrigatórios, regras de catálogo, validações da API).

