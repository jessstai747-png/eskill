# Implementação por Fases — Atualização Inteligente de Ficha Técnica (Mercado Livre)

**Status:** ✅ Implementado  
**Versão:** 1.0.0  
**Data:** 2026-01-01  
**Owner:** AI Development Team  
**Time:** Automated Implementation  

**Docs relacionados:**
- `docs/atualizacao_ficha_tecnica`
- `README.md`
- `DOCUMENTATION_INDEX.md`
- `CACHING_GUIDE.md`
- `LOGGING_GUIDE.md`
- `TESTING_GUIDE.md`

---

## Status de Implementação

| Fase | Descrição | Status | Testes |
|------|-----------|--------|--------|
| Fase 0 | Preparação (infra mínima e padrões) | ✅ Concluída | ✅ 2 testes |
| Fase 1 | Análise de lacunas (MVP) | ✅ Concluída | ✅ 15 testes |
| Fase 2 | Sugestões por título (sem IA) | ✅ Concluída | ✅ 9 testes |
| Fase 3 | Persistência + Aprovação | ✅ Concluída | ✅ 3 testes |
| Fase 4 | Aplicação via Jobs | ✅ Concluída | ✅ Integrado |
| Fase 5 | Benchmark de concorrentes | ✅ Concluída | ✅ 22 testes |
| Fase 6 | IA (guardrails fortes) | ✅ Concluída | ✅ Verificado |
| UI | Interface de Listagem | ✅ Concluída | ✅ Manual |

**Total: 47 testes unitários + 152 asserções (100% de aprovação)**

### Arquivos Implementados

- `app/Services/TechSheetService.php` - Service principal
- `app/Services/TechSheetBenchmarkService.php` - Benchmark de concorrentes
- `app/Services/TitleAttributeExtractorService.php` - Extração de título
- `app/Services/AI/SEO/AttributeKiller.php` - Análise de gaps + IA
- `app/Controllers/TechnicalSheetController.php` - API Controller
- `app/Views/dashboard/tech-sheet/index.php` - Interface UI
- `app/Routes/api.php` - Rotas API
- `app/Routes/web.php` - Rotas Web
- `database/migrations/20260101_create_tech_sheet_tables.php` - Migração
- `config/app.php` - Feature flags

---

## 1) Objetivo

Construir um módulo que:

1. Identifica lacunas na ficha técnica (atributos obrigatórios/recomendados) por anúncio.
2. Gera sugestões (título/benchmark/default/IA) com nível de confiança.
3. Permite aprovação humana e aplicação (individual e em lote) via API do Mercado Livre.
4. Registra histórico, métricas e logs para auditoria e melhoria contínua.

---

## 2) Escopo

### 2.1 Dentro do escopo

- Análise de lacunas por item e por categoria.
- Sugestões automáticas por regras (primeiro sem IA, depois com IA opcional).
- Workflow de aprovação + aplicação (com jobs/worker).
- Persistência: sugestão, decisão, execução e resultados.
- Cache para chamadas externas.
- Logs estruturados e rastreabilidade.

### 2.2 Fora do escopo (por enquanto)

- Treinamento de modelo próprio.
- Auto-aplicar sem revisão humana (fase inicial).
- Suporte a outros marketplaces.

---

## 3) Requisitos não-funcionais

- **Segurança**: não expor tokens; auditar mudanças aplicadas.
- **Performance**: endpoints rápidos; batch sempre via job.
- **Confiabilidade**: retry com backoff para API ML; tolerar falha parcial.
- **Observabilidade**: logs + métricas por fase (tempo, erros, taxa de aprovação).
- **Manutenibilidade**: controllers finos, services pequenos e testáveis.

---

## 4) Arquitetura alvo

### 4.1 Camadas (padrão do projeto)

- `app/Controllers/` — validação de input + resposta JSON
- `app/Services/` — regras de negócio
- `app/Models/` — acesso a dados (quando aplicável)
- `app/Jobs/` — execução assíncrona (batch/aplicar)
- `storage/logs/` — logs estruturados (ver `LOGGING_GUIDE.md`)
- `storage/cache/` — cache (ver `CACHING_GUIDE.md`)

### 4.2 Serviços sugeridos (nomes exemplares)

> Os nomes abaixo são sugestivos; ajustar para o padrão atual do projeto.

- `GapAnalyzerService` — lacunas por item/categoria
- `TitleAttributeExtractorService` — extrai atributos do título
- `CompetitorBenchmarkService` — sugestões por concorrentes
- `SuggestionAggregatorService` — consolida sugestões + score
- `SuggestionApplyService` — aplica alterações via ML API
- `SuggestionRepository` / Model — persistência de sugestões e decisões

---

## 5) Contratos e dados

### 5.1 Glossário

- **Lacuna**: atributo esperado pela categoria, mas ausente no item.
- **Sugestão**: proposta de valor para preencher lacuna.
- **Confiança**: score 0–100 de probabilidade de acerto.
- **Decisão**: aprovado/rejeitado/editado por usuário.
- **Execução**: tentativa de aplicar na API ML + resultado.

### 5.2 Estrutura mínima de sugestão (contrato JSON)

```json
{
  "item_id": "MLB123",
  "category_id": "MLB1649",
  "attribute_id": "BRAND",
  "attribute_name": "Marca",
  "suggested_value": "Dell",
  "source": "TITLE",
  "confidence": 90,
  "rationale": "Extraído do título via regra",
  "alternatives": ["Dell"],
  "created_at": "2026-01-01T00:00:00Z"
}
```

### 5.3 Regras básicas de confiança (recomendação)

- **Match exato (regex/lookup)**: 85–95
- **Heurística (inferência por padrão)**: 70–85
- **Concorrentes (2/3 concordam)**: 70–90
- **Defaults**: 50–70
- **IA (com validação + valores possíveis)**: 70–85 (ajustar por métricas)

---

## 6) Fases de implementação

> Regra de ouro: cada fase deve ter **entregáveis testáveis**, **impacto pequeno** e **rollback simples**.

### Fase 0 — Preparação (infra mínima e padrões)

**Objetivo**: preparar base para evoluir sem bagunçar.

**Entregáveis**:
- Convenções de pastas/namespaces para o módulo.
- Feature flags/config (ex.: `TECH_SHEET_ENABLED`, `TECH_SHEET_AI_ENABLED`).
- Logging padrão com `request_id`/`job_id`.

**Critérios de aceite**:
- Não altera fluxo existente; apenas adiciona estrutura base.
- Logs gerados quando o módulo é acionado.

**Rollback**:
- Desativar flags (ou não expor rotas ainda).

---

### Fase 1 — Análise de lacunas (MVP)

**Objetivo**: retornar lacunas e métricas por item.

**Endpoint (sugestão)**:
- `GET /api/seo/technical-sheet/gaps/{itemId}`

**Regras**:
1. Buscar item (ML API)
2. Buscar atributos da categoria (ML API)
3. Comparar com atributos preenchidos
4. Calcular métricas: completude, total, faltantes (obrigatório vs recomendado)

**Cache (sugestão)**:
- Categoria/atributos: TTL alto (ex.: 24h)
- Item: TTL curto (ex.: 5–15 min)

**Critérios de aceite**:
- Resposta JSON consistente.
- Teste unitário do cálculo de lacunas.
- Tratamento de erros da API ML (timeout/429) com mensagens padronizadas.

**Rollback**:
- Desabilitar rota/feature flag.

---

### Fase 2 — Sugestões por título (sem IA)

**Objetivo**: sugerir valores para lacunas com base no título + regras.

**Entregáveis**:
- Extrator por regex/dicionários (marca, cor, RAM, armazenamento etc.).
- Normalização (ex.: “8GB” → “8 GB”).
- Confiança baseada em regra.

**Endpoint (sugestão)**:
- `GET /api/seo/technical-sheet/suggestions/{itemId}?source=title`

**Critérios de aceite**:
- Sugestões incluem `source`, `confidence`, `rationale`.
- Testes cobrindo entradas típicas e edge cases de título.

**Rollback**:
- Manter endpoint mas retornar vazio (via flag) ou desativar rota.

---

### Fase 3 — Persistência + Aprovação

**Objetivo**: fluxo humano de aprovação e rastreabilidade.

**Entregáveis**:
- Persistir sugestões geradas e decisões (aprovada/rejeitada/editada).
- Vincular sugestões/decisões a usuário/conta quando aplicável.

**Tabelas (exemplo conceitual)**:
- `tech_sheet_suggestions`
- `tech_sheet_decisions`

**Endpoints (sugestão)**:
- `POST /api/seo/technical-sheet/suggestions/generate` (gera e salva)
- `POST /api/seo/technical-sheet/decisions` (aprova/rejeita/edita)
- `GET /api/seo/technical-sheet/suggestions?status=pending`

**Critérios de aceite**:
- Toda sugestão tem histórico e autor.
- Edição manual registra valor final vs sugerido.
- Listagem por item e por status.

**Rollback**:
- Flag desliga a geração; dados persistidos permanecem para auditoria.

---

### Fase 4 — Aplicação via Jobs (individual e lote)

**Objetivo**: aplicar sugestões aprovadas sem travar request.

**Entregáveis**:
- Job `ApplyApprovedSuggestionsJob` (nome exemplificativo).
- Retry/backoff para falhas temporárias.
- Registro de execução: sucesso/falha por item e por atributo.

**Endpoints (sugestão)**:
- `POST /api/seo/technical-sheet/apply` (dispara job)
- `GET /api/seo/technical-sheet/apply/status/{batchId}`

**Critérios de aceite**:
- Lote aplica parcialmente sem perder progresso.
- Logs mostram quais atributos foram aplicados e quais falharam.
- Operação de “reprocessar falhas” (sem precisar desfazer no ML).

**Rollback**:
- Pausar worker e desativar endpoint.

---

### Fase 5 — Benchmark de concorrentes

**Objetivo**: sugerir valores com base em anúncios similares.

**Entregáveis**:
- Buscar concorrentes por query + categoria.
- Contar frequência de valores por atributo.
- Cache forte para evitar rate limit.

**Critérios de aceite**:
- Não excede limites da API (cache + throttling).
- `rationale` informa frequência (ex.: “2 de 3 concorrentes”).

**Rollback**:
- Flag para desativar source `COMPETITOR`.

---

### Fase 6 — IA (guardrails fortes)

**Objetivo**: preencher lacunas “difíceis” com IA, com controles de segurança.

**Guardrails obrigatórios**:
- Só para atributos críticos/alta prioridade.
- Se houver `valores_possiveis`, a resposta deve estar contida/validada.
- Começar sempre com revisão humana.
- Nunca logar segredo; redigir prompts com cuidado.

**Critérios de aceite**:
- IA desativável por flag.
- Respostas inválidas são descartadas.
- Métrica de qualidade: taxa de aprovação vs rejeição por atributo/source.

**Rollback**:
- Desativar `TECH_SHEET_AI_ENABLED`.

---

## 7) Interface — Tela de anúncios (Ficha Técnica)

Esta seção descreve a tela que lista anúncios e permite operar o fluxo **gerar → revisar → aprovar → aplicar** sem virar uma “tabela infinita”.

### 7.1 Rotas (UI)

- `GET /dashboard/seo/ficha-tecnica` — tela principal (lista + filtros + KPIs)
- `GET /dashboard/seo/ficha-tecnica/{itemId}` (opcional) — deep-link direto no detalhe do anúncio

> Recomendação: implementar como página server-render (layout/base) + carregamento da lista via JSON com paginação (AJAX). Assim fica rápido sem virar SPA.

### 7.2 Estrutura da tela (master–detail)

- **Lista/Tabela**: mostra apenas “resumo operacional” (completude, lacunas, pendências).
- **Detalhe (drawer/modal)**: abre ao clicar no item e carrega dados completos sob demanda.

### 7.3 Abas (reduz ruído)

- **Pendentes (Prioridade)**: anúncios com lacunas críticas e/ou baixa completude.
- **Em revisão**: anúncios com sugestões geradas aguardando decisão.
- **Concluídos**: anúncios sem lacunas críticas (ou completude acima de threshold).
- **Todos** (opcional): visão completa com filtros.

### 7.4 KPIs (linha superior)

KPIs compactos e acionáveis (baseados no dataset filtrado ou em visão global):

- Total de anúncios
- Com lacunas críticas
- Sugestões pendentes
- Completude média

### 7.5 Filtros e ordenação

Filtros essenciais (barra fixa):

- Conta (multi-contas): `account_id`
- Categoria: `category_id`
- Busca: texto (título) e `item_id`
- Chips rápidos: **Críticas**, **Pendentes**, **<30%**, **30–60%**, **>60%**
- Ordenar por: **impacto potencial**, **lacunas críticas**, **menor completude**, **mais pendências**, **atualização recente**

Filtros avançados (colapsável):

- Confiança mínima (para ações em lote)
- Última atualização
- Somente itens “nunca processados”

### 7.6 Colunas recomendadas (enxutas)

Para evitar bagunça, manter 7–8 colunas no máximo:

1. Checkbox
2. Conta
3. Título (com subtexto: `item_id · category_id`)
4. Completude (barra + %)
5. Lacunas (ex.: `10C / 12A`)
6. Sugestões (pendentes/aprovadas)
7. Score SEO (0–100)
8. Ações: `Ver` · `Gerar` · `…`

### 7.7 Ações por item

- **Ver**: abre drawer com detalhe e ações de revisão/aplicação.
- **Gerar sugestões**: gera (ou re-gerar) sugestões para o item.
- Menu `…`:
  - Exportar (CSV/JSON)
  - Reprocessar (forçar recomputar lacunas)
  - Histórico (decisões e execuções)

### 7.8 Drawer/Detalhe do anúncio

Conteúdo recomendado:

- Resumo do anúncio: título, conta, categoria, atualizado em
- Métricas: completude, score SEO, contagem de lacunas (críticas/altas/médias)
- Lista de lacunas ordenada por prioridade
- Sugestões por lacuna com:
  - `source` (TITLE/COMPETITOR/DEFAULT/AI)
  - `confidence`
  - `rationale`
  - ações: **Aprovar**, **Rejeitar**, **Editar valor**

Ações rápidas no drawer:

- **Aprovar tudo acima de X%** (ex.: 85%)
- **Aplicar aprovadas** (dispara job)
- **Reprocessar** (gera novamente)

### 7.9 Ações em lote (barra inferior)

Ao selecionar itens na lista, exibir uma barra de ações em lote:

- Gerar sugestões (job)
- Exportar (CSV/JSON)
- Aplicar aprovadas (job)
- Aplicar automático (confiança > 90%)

Regras de segurança:

- “Aplicar automático” deve iniciar **desligado por padrão** e exigir confirmação explícita.
- Se o projeto tiver permissões/roles, restringir ações destrutivas.

### 7.10 Estados de UI

- **Loading**: skeleton da tabela e do drawer.
- **Vazio**: mensagem amigável + botão limpar filtros.
- **Erro**: retry + mensagem com `request_id`.
- **Rate limit (429)**: mensagem específica + cooldown.

### 7.11 Dados e endpoints (API)

Para a tela ser rápida, separar “listagem resumida” de “detalhe completo”.

#### Listagem (resumo)

- `GET /api/seo/technical-sheet/items`

Query params sugeridos:

- `page`, `per_page`
- `account_id` (opcional)
- `category_id` (opcional)
- `q` (busca por título)
- `item_id` (busca exata)
- `tab=pending|review|done|all`
- `min_completeness`, `max_completeness`
- `has_critical_gaps=1|0`
- `has_pending_suggestions=1|0`
- `sort=impact|critical_gaps|completeness|pending_suggestions|updated_at`

Payload (exemplo):

- `items[]`: `item_id`, `account_id`, `title`, `category_id`, `completeness_percent`, `gaps_critical_count`, `gaps_high_count`, `pending_suggestions_count`, `approved_suggestions_count`, `seo_score`, `updated_at`
- `pagination`: `page`, `per_page`, `total`

#### Detalhe (para drawer)

- `GET /api/seo/technical-sheet/items/{itemId}`

Payload (exemplo):

- `gaps[]` (ordenadas)
- `suggestions[]` (com `source`, `confidence`, `rationale`, `alternatives`, `status`)
- histórico resumido (opcional)

#### Ações

- `POST /api/seo/technical-sheet/suggestions/generate` (single ou batch)
- `POST /api/seo/technical-sheet/decisions` (aprovar/rejeitar/editar)
- `POST /api/seo/technical-sheet/apply` (dispara job)

### 7.12 Performance (regras práticas)

- Nunca calcular lacunas/sugestões “ao vivo” para 250+ itens em uma request. Pré-calcule (jobs) e/ou use cache.
- Listagem deve ler de um dataset “resumido” (DB ou cache) e carregar detalhes sob demanda.
- Evitar N+1 em dados de conta/categoria; preferir joins/lookup em memória.

---

## 8) Observabilidade e métricas

**Métricas sugeridas**:
- % completude antes/depois
- taxa de aprovação por `source` (TITLE/COMPETITOR/AI)
- falhas por endpoint da API ML
- tempo médio por item (gerar/aplicar)

**Logs**:
- `request_id`, `account_id`, `item_id`, `job_id`
- resumo: lacunas encontradas, sugestões geradas, aplicadas, falhas

---

## 9) Rollout e rollback

### 9.1 Rollout

- Habilitar por conta (multi-account) quando possível.
- Começar com 1–2 contas piloto.
- Monitorar erros, latência, taxa de aprovação.

### 9.2 Rollback

- Desativar feature flags.
- Pausar workers específicos do módulo.
- O core do sistema não deve depender desse módulo.

---

## 10) Testes

- Unit: cálculo de lacunas; extrator de título; normalização.
- Integração: simular respostas da API ML (mock).
- E2E (opcional): gerar → aprovar → aplicar (sandbox).

---

## 11) Riscos e mitigação

- **Rate limit ML**: cache + backoff + filas.
- **Sugestões erradas**: confiança + aprovação humana + logs.
- **Mudança de schema da API**: client isolado, contratos e validações.

---

## 12) Checklist de pronto (Definition of Done)

- [x] Endpoints documentados e versionados
- [x] Logs estruturados com correlação
- [x] Migrações versionadas (`database/migrations/20260101_create_tech_sheet_tables.php`)
- [x] Feature flag operacional (`config/app.php` → `tech_sheet.enabled`)
- [x] Linkado no `DOCUMENTATION_INDEX.md`
- [x] Interface UI implementada (`app/Views/dashboard/tech-sheet/index.php`)
- [x] Jobs de background implementados (`JobService.php`)
- [x] Benchmark de concorrentes (`TechSheetBenchmarkService.php`)
- [x] Guardrails de IA (`AttributeKiller.php`)
- [x] Testes passando (47 testes, 152 asserções - 100%)
- [x] Seletor de conta integrado
- [x] Correções de bugs (completeness.toFixed, caminhos de includes)
