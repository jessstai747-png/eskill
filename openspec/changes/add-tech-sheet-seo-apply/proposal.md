# Change: add-tech-sheet-seo-apply

**Status: ✅ IMPLEMENTED (2026-01-29)**

## Why
Hoje o módulo de Ficha Técnica já **gera** otimizações de SEO (título/descrição), mas não existe um fluxo robusto e rastreável para **aplicar** essas mudanças no Mercado Livre com **snapshot + rollback**.

Isso impede fechar o ciclo "gerar → revisar → aplicar → medir → desfazer", essencial para operar SEO em produção com segurança.

## What Changes
- **Bulk SEO Seguro**: Workflow dry-run → revisar → aplicar → rollback
- **BulkSEOService**: Novo serviço para operações em lote
- **AttributeSuggestionService**: Gerenciar sugestões de atributos aplicáveis via ML API
- Registrar **versionamento** (snapshot + diff) via `App\Services\SEO\VersioningService`
- Garantir que descrição seja aplicada no endpoint correto do ML (`/items/{id}/description` com `plain_text`)
- Rate limiting (200ms entre chamadas) para respeitar limites da API ML
- Hardening com mensagens de erro amigáveis e detecção de erros recuperáveis

## Impact
- Affected code:
  - `app/Controllers/TechnicalSheetController.php` (+8 endpoints)
  - `app/Services/BulkSEOService.php` (novo, ~1100 linhas)
  - `app/Services/AttributeSuggestionService.php` (novo, ~560 linhas)
  - `app/Services/SEO/VersioningService.php` (reusado)
  - `app/Routes/api.php` (+9 rotas)
  - `app/Views/dashboard/tech-sheet/index.php` (modal + JS)
- Affected API:
  - `POST /api/tech-sheet/bulk/dry-run`
  - `POST /api/tech-sheet/bulk/apply`
  - `POST /api/tech-sheet/bulk/apply-async`
  - `POST /api/tech-sheet/bulk/rollback`
  - `GET /api/tech-sheet/bulk/history`
  - `GET /api/tech-sheet/bulk/job/{jobId}/status`
  - `GET /api/tech-sheet/items/{itemId}/attribute-suggestions/preview`
  - `POST /api/tech-sheet/items/{itemId}/attribute-suggestions/apply`
  - `GET /api/tech-sheet/items/{itemId}/applicable-attributes`
- Database:
  - Reusa `seo_optimization_history`, `seo_snapshots`
  - Nova tabela `bulk_seo_jobs` para jobs assíncronos

## Notes
- Implementação expandida para suportar operações em lote (até 50 itens)
- Jobs assíncronos para lotes maiores (>20 itens)
- Testes: 19 novos testes unitários, suite completa (774 testes) passando
