## 1. Implementation
- [x] 1.1 Criar métodos no controller para aplicar título/descrição
  - `POST /api/tech-sheet/bulk/dry-run` - Preview de otimizações em lote
  - `POST /api/tech-sheet/bulk/apply` - Aplicar otimizações síncronas
  - `POST /api/tech-sheet/bulk/apply-async` - Aplicar otimizações assíncronas
  - `POST /api/tech-sheet/bulk/rollback` - Rollback em lote
  - `GET /api/tech-sheet/bulk/history` - Histórico de operações
- [x] 1.2 Implementar BulkSEOService com métodos de apply
  - `dryRunBatch()` - Preview com diff de cada item
  - `applyBatch()` - Aplicar título/descrição em lote com snapshots
  - `rollbackBatch()` - Rollback em lote via VersioningService
  - `startBatchJob()` - Jobs assíncronos para lotes grandes (>20 itens)
  - Rate limiting: 200ms entre chamadas ML
  - Snapshots automáticos antes de cada alteração
- [x] 1.3 Implementar AttributeSuggestionService
  - `previewSuggestions()` - Preview de atributos aplicáveis
  - `applySuggestion()` - Aplicar atributo via ML API
  - Separação: APPLICABLE_ATTRIBUTES vs INTERNAL_ONLY_ATTRIBUTES
- [x] 1.4 Validar payloads e respostas (erros do ML, timeouts, etc.)
  - `interpretMLError()` - Mensagens amigáveis para erros ML
  - `isRetryableError()` - Identificar erros recuperáveis (429, 5xx)
- [x] 1.5 Adicionar testes unitários
  - `tests/Unit/Services/BulkSEOServiceTest.php` - 12 testes
  - `tests/Unit/Services/AttributeSuggestionServiceTest.php` - 7 testes
- [x] 1.6 Implementar UI Modal no frontend
  - Modal com 5 passos: opções → loading → revisão → aplicando → resultados
  - JavaScript BulkSEO object com workflow completo
  - CSS para visual consistente

## 2. Verification
- [x] Rodar testes: 774 testes, 1890 assertions, 0 falhas
- [x] Rodar análise Codacy por arquivo alterado: todos sem issues

## Arquivos criados/modificados

### Novos
- `app/Services/BulkSEOService.php` (~1100 linhas)
- `app/Services/AttributeSuggestionService.php` (~560 linhas)
- `tests/Unit/Services/BulkSEOServiceTest.php`
- `tests/Unit/Services/AttributeSuggestionServiceTest.php`

### Modificados
- `app/Controllers/TechnicalSheetController.php` - +8 endpoints
- `app/Routes/api.php` - +9 rotas bulk SEO
- `app/Views/dashboard/tech-sheet/index.php` - Modal + JS + CSS
