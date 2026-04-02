# Plano de Implementação por Fases — Módulo AWA Sellers

## Objetivo

Implementar um módulo operacional para:

1. descobrir quais sellers/lojas estão anunciando produtos com marca **AWA** no Mercado Livre;
2. persistir histórico de detecção por seller e por anúncio;
3. exibir uma tela paginada com **todas as lojas encontradas no escopo da varredura**;
4. permitir enriquecimento de identificação da loja, inclusive **CNPJ**, com rastreabilidade da origem do dado;
5. suportar exportação, auditoria e acompanhamento recorrente.

## Premissas importantes

### O que é viável pela API do Mercado Livre

O sistema já consegue obter, a partir de anúncios e sellers públicos:

- `seller_id`
- `nickname`
- `permalink`
- `city/state`
- `seller_reputation`
- itens encontrados por seller
- categorias e evidências dos anúncios

### O que **não** deve ser assumido como público

Para **terceiros** no Mercado Livre, o CNPJ não deve ser tratado como dado disponível de forma pública e confiável via API padrão.

**Decisão de arquitetura:**

- a **descoberta de sellers AWA** será uma trilha;
- o **enriquecimento de CNPJ/razão social** será outra trilha, com origem controlada e status de verificação.

### Reaproveitamento do código atual

O projeto já possui base importante que deve ser reaproveitada:

- `app/Services/BrandAnalyzerService.php`
- `app/Controllers/BrandAnalyzerController.php`
- `app/Services/CompetitorService.php`
- `app/Controllers/CompetitorController.php`
- rotas existentes em `app/Routes/api/items.php`
- pesquisa histórica em `docs/AWA_LOJAS_REVENDEDORES_ML.md`

## Resultado esperado ao final

Ao concluir o plano, o sistema deverá oferecer:

- página `dashboard/awa-sellers`;
- aba **Lojas** com sellers detectados, filtros e paginação;
- aba **Anúncios** com evidências por item;
- detalhe por seller com histórico, reputação, localização e identificação;
- status de identificação:
  - `verified`
  - `pending`
  - `not_available`
  - `conflict`
- exportação CSV da base filtrada;
- rotina de varredura recorrente e trilha de auditoria.

---

## Arquitetura-alvo

### Camada 1 — Descoberta AWA

Responsável por localizar sellers e anúncios AWA a partir da API do ML.

**Proposta:**

- extrair a lógica de descoberta de seller do `BrandAnalyzerService` para um serviço mais explícito, por exemplo `AwaSellerDiscoveryService`;
- manter o `BrandAnalyzerService` para análises de marca e dashboards mais amplos;
- usar a descoberta para alimentar o registry local.

### Camada 2 — Registry local

Responsável por persistir os sellers detectados e permitir consulta rápida sem depender da API ao vivo em cada carregamento da tela.

**Proposta de service:**

- `app/Services/AwaSellerRegistryService.php`

### Camada 3 — Enriquecimento de identificação

Responsável por armazenar CNPJ/razão social/manual review/observações, sem misturar isso com os dados observados do ML.

**Proposta de service:**

- `app/Services/AwaSellerIdentificationService.php`

### Camada 4 — UI operacional

Responsável pela tela, filtros, paginação, detalhes e exportações.

**Proposta:**

- `app/Controllers/AwaSellerController.php`
- `app/Views/dashboard/awa-sellers/index.php`

### Camada 5 — Varredura assíncrona

Responsável por snapshots periódicos.

**Proposta:**

- `bin/awa-sellers-scan-worker.php`

---

## Modelo de dados sugerido

> Observação: as migrations devem ser criadas via gerador do projeto (`php bin/make-migration.php`) no momento da implementação.

### Tabela 1 — `awa_seller_registry`

Armazena a entidade seller consolidada.

**Campos sugeridos:**

- `id`
- `seller_id` (ML user id)
- `nickname`
- `permalink`
- `city`
- `state`
- `user_type`
- `reputation_level`
- `items_count`
- `categories_json`
- `first_seen_at`
- `last_seen_at`
- `last_scan_id`
- `is_active`
- `created_at`
- `updated_at`

### Tabela 2 — `awa_seller_items`

Armazena os anúncios observados por seller.

**Campos sugeridos:**

- `id`
- `seller_registry_id`
- `ml_item_id`
- `title`
- `category_id`
- `price`
- `status`
- `brand_match_type`
- `has_brand_attribute`
- `evidence_json`
- `first_seen_at`
- `last_seen_at`
- `created_at`
- `updated_at`

### Tabela 3 — `awa_seller_identification`

Armazena o enriquecimento jurídico/comercial.

**Campos sugeridos:**

- `id`
- `seller_registry_id`
- `cnpj`
- `razao_social`
- `source_type`
- `source_reference`
- `confidence_score`
- `verification_status`
- `verified_at`
- `notes`
- `created_at`
- `updated_at`

### Tabela 4 — `awa_scan_runs`

Armazena execuções de varredura.

**Campos sugeridos:**

- `id`
- `scope_json`
- `status`
- `sellers_found`
- `items_found`
- `started_at`
- `finished_at`
- `error_message`
- `created_at`
- `updated_at`

---

## Implementação por fases

## Fase 0 — Alinhamento, escopo e modelagem

**Objetivo:** preparar base técnica e funcional antes da UI.

### Entregas

- consolidar regras de negócio do módulo;
- definir escopo do que conta como “seller AWA”;
- definir a política de identificação/CNPJ;
- modelar tabelas do registry, itens, identificação e runs.

### Regras funcionais a fechar

- seller entra no registry quando:
  - anuncia item com `BRAND = AWA`; ou
  - anuncia item com AWA no título e evidência consistente;
- seller aparece na tela mesmo sem CNPJ;
- CNPJ não é obrigatório para aparecer, apenas para enriquecimento;
- a tela principal consulta o banco, não a API ao vivo.

### Arquivos-alvo

- `docs/guides/AWA_SELLERS_IMPLEMENTATION_PLAN.md`
- futuras migrations geradas via `bin/make-migration.php`

### Critério de saída

- modelo de dados aprovado;
- regras de inclusão/exclusão de sellers definidas;
- status de identificação definidos.

---

## Fase 1 — Descoberta e persistência dos sellers AWA

**Objetivo:** transformar a descoberta atual em pipeline persistente.

### Entregas

- criar `AwaSellerDiscoveryService` ou equivalente;
- reutilizar a busca já existente no `BrandAnalyzerService`;
- persistir sellers detectados em `awa_seller_registry`;
- persistir anúncios em `awa_seller_items`;
- registrar snapshots em `awa_scan_runs`.

### Estratégia recomendada

1. Buscar anúncios AWA por categoria e variações conhecidas.
2. Extrair `seller_id` e detalhes públicos do seller.
3. Fazer deduplicação por `seller_id`.
4. Atualizar `first_seen_at` e `last_seen_at`.
5. Salvar itens observados como evidência.

### Arquivos-alvo

- `app/Services/BrandAnalyzerService.php` (reuso/refatoração controlada)
- `app/Services/AwaSellerDiscoveryService.php`
- `app/Services/AwaSellerRegistryService.php`
- `database/migrations/*create_awa_seller_registry*`
- `database/migrations/*create_awa_seller_items*`
- `database/migrations/*create_awa_scan_runs*`

### API mínima desta fase

- `POST /api/brand/awa/sellers/scan`
- `GET /api/brand/awa/sellers/scan/{id}`
- `GET /api/brand/awa/sellers/metrics`

### Testes

- `tests/Unit/Services/AwaSellerDiscoveryServiceTest.php`
- `tests/Unit/Services/AwaSellerRegistryServiceTest.php`
- `tests/Integration/AwaSellerScanFlowTest.php`

### Critério de saída

- uma varredura completa consegue salvar sellers e anúncios no banco;
- sellers reincidentes não geram duplicidade;
- histórico de `first_seen_at`/`last_seen_at` funciona.

---

## Fase 2 — Tela operacional do módulo

**Objetivo:** entregar a UI de consulta que o usuário realmente vai usar.

### Entregas

- criar página `dashboard/awa-sellers`;
- listar sellers detectados com paginação;
- adicionar filtros por categoria, UF, cidade, reputação, status de identificação e volume de anúncios;
- criar drawer/modal de detalhes do seller;
- criar aba de anúncios detectados.

### Estrutura sugerida da UI

#### Aba `Lojas`

- 1 linha por seller;
- paginação 25/50 por página;
- total geral no topo;
- badges de status.

#### Aba `Anúncios`

- 1 linha por item observado;
- seller associado;
- evidência de marca;
- link do anúncio.

#### Detalhe do seller

- seller_id
- nickname
- permalink
- cidade/UF
- reputação
- quantidade de anúncios AWA
- categorias encontradas
- linha do tempo
- identificação/CNPJ
- observações internas

### Arquivos-alvo

- `app/Controllers/AwaSellerController.php`
- `app/Views/dashboard/awa-sellers/index.php`
- `app/Routes/web.php`
- `app/Routes/api/items.php`

### Rotas sugeridas

#### Web

- `GET /dashboard/awa-sellers`

#### API

- `GET /api/brand/awa/sellers`
- `GET /api/brand/awa/sellers/{sellerId}`
- `GET /api/brand/awa/sellers/{sellerId}/items`
- `GET /api/brand/awa/sellers/filters/options`

### Testes

- `tests/Unit/Controllers/AwaSellerControllerTest.php`
- `tests/Unit/Views/AwaSellerViewSmokeTest.php`

### Critério de saída

- a tela mostra sellers do banco com filtros, paginação e detalhe por seller;
- a experiência não depende de chamada ao vivo à API do ML a cada carregamento.

---

## Fase 3 — Enriquecimento de identificação e CNPJ

**Objetivo:** permitir que a base de sellers tenha identificação rastreável.

### Entregas

- criar tabela `awa_seller_identification`;
- permitir edição/manual review;
- suportar sellers autorizados via OAuth como fonte confiável quando aplicável;
- registrar origem e confiança do dado.

### Status de identificação

- `verified`
- `pending`
- `not_available`
- `conflict`

### Regras importantes

- CNPJ não é requisito para existir no registry;
- dado jurídico não deve sobrescrever automaticamente dados de descoberta;
- toda alteração manual deve guardar observação e origem.

### Fontes aceitas no MVP

- `manual`
- `authorized_ml_account`
- `internal_registry`

### Fontes opcionais para depois

- `external_registry`
- `website_review`
- `legal_team_validation`

### Arquivos-alvo

- `app/Services/AwaSellerIdentificationService.php`
- `app/Controllers/AwaSellerController.php`
- `database/migrations/*create_awa_seller_identification*`
- `app/Views/dashboard/awa-sellers/index.php`

### API sugerida

- `POST /api/brand/awa/sellers/{sellerId}/identification`
- `PUT /api/brand/awa/sellers/{sellerId}/identification`
- `GET /api/brand/awa/sellers/{sellerId}/identification`

### Testes

- `tests/Unit/Services/AwaSellerIdentificationServiceTest.php`
- `tests/Feature/AwaSellerIdentificationWorkflowTest.php`

### Critério de saída

- é possível cadastrar/editar CNPJ com rastreabilidade;
- sellers sem CNPJ continuam aparecendo normalmente;
- conflitos ficam sinalizados para revisão.

---

## Fase 4 — Histórico, alertas e recorrência

**Objetivo:** transformar o módulo em ferramenta contínua de monitoramento.

### Entregas

- worker de varredura recorrente;
- histórico de sellers novos;
- alertas de seller novo;
- alertas de seller com aumento súbito de volume;
- alertas de seller sem identificação após X dias.

### Worker sugerido

- `bin/awa-sellers-scan-worker.php`

### Frequência sugerida

- scan leve: `2x` ao dia
- scan completo: `1x` ao dia ou sob demanda

### Arquivos-alvo

- `bin/awa-sellers-scan-worker.php`
- `app/Services/AwaSellerRegistryService.php`
- `app/Services/AlertService.php` ou serviço dedicado
- `current_crontab`

### API sugerida

- `GET /api/brand/awa/sellers/history`
- `GET /api/brand/awa/sellers/alerts`

### Testes

- `tests/Unit/Services/AwaSellerAlertServiceTest.php`
- `tests/Integration/AwaSellerRecurringScanTest.php`

### Critério de saída

- sellers novos e recorrentes ficam visíveis;
- execução recorrente não duplica base;
- alertas principais estão operacionais.

---

## Fase 5 — Exportações, auditoria e hardening

**Objetivo:** fechar o módulo com usabilidade operacional e governança.

### Entregas

- export CSV de sellers filtrados;
- export CSV de anúncios filtrados;
- trilha de auditoria de enriquecimento manual;
- controle de permissões por perfil;
- melhorias de performance e observabilidade.

### Arquivos-alvo

- `app/Controllers/AwaSellerController.php`
- `app/Services/AwaSellerExportService.php`
- `app/Services/AuditLogService.php`
- `tests/Feature/AwaSellerExportTest.php`

### Critério de saída

- base exportável;
- alterações de identificação auditadas;
- tela permanece rápida com volume alto de sellers/anúncios.

---

## Ordem recomendada de execução

### Sprint 1

- Fase 0
- Fase 1

### Sprint 2

- Fase 2

### Sprint 3

- Fase 3

### Sprint 4

- Fase 4
- Fase 5

---

## MVP recomendado

Se a meta for colocar algo útil no ar rápido, o MVP deve incluir apenas:

- registry persistente de sellers;
- tela `dashboard/awa-sellers`;
- filtros básicos;
- paginação;
- detalhe por seller;
- exportação CSV;
- status de identificação manual.

### O MVP **não precisa** incluir

- integração automática externa para CNPJ;
- scraping de websites;
- score avançado de risco;
- automação jurídica;
- gráficos muito complexos.

---

## Riscos e decisões de projeto

### Risco 1 — confundir seller com loja oficial

**Mitigação:** tratar a entidade principal como `seller` e usar campos adicionais para identificar tipo/qualificação.

### Risco 2 — usar a API do ML ao vivo em toda navegação

**Mitigação:** a UI deve ler do banco local, não fazer discovery full ao abrir a página.

### Risco 3 — tratar CNPJ como dado público garantido

**Mitigação:** manter CNPJ em trilha separada de enriquecimento, com origem e confiança.

### Risco 4 — duplicidade entre módulo AWA e módulo de concorrentes

**Mitigação:** reutilizar a base do `CompetitorService` somente quando fizer sentido e evitar duas tabelas com a mesma finalidade.

---

## Definition of Done transversal

Um item deste plano só pode ser considerado concluído quando:

1. o fluxo principal estiver implementado;
2. houver teste automatizado compatível com o risco;
3. a tela estiver funcional com dados reais do banco local;
4. logs e erros estiverem claros;
5. o comportamento estiver consistente com a limitação do CNPJ descrita neste plano.

---

## Resumo executivo

A melhor abordagem para o módulo é:

1. **descobrir sellers AWA primeiro**;
2. **persistir e operar em cima do registry local**;
3. **tratar CNPJ como enriquecimento rastreável, não como pré-requisito**;
4. **entregar uma tela operacional com lojas, anúncios, filtros, histórico e exportação**.

Essa abordagem reduz risco técnico, evita dependência excessiva da API do ML em tempo real e entrega valor rápido para operação, compliance e inteligência comercial.
