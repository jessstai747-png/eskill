## Context
O módulo de Ficha Técnica já gera otimizações de SEO (título/descrição), mas não possui um fluxo de aplicação versionado.
O projeto já possui `VersioningService` e endpoints de histórico/rollback no módulo `SEOController`.

## Goals / Non-Goals
- Goals:
  - Aplicar título otimizado com snapshot/diff.
  - Aplicar descrição otimizada com snapshot/diff usando endpoint correto do ML.
  - Manter capacidade de rollback via histórico existente.
- Non-Goals:
  - Auto-apply em massa (batch) por confiança.
  - Alterações de UI (pode ser feito depois).

## Decisions
- **Aplicação manual via endpoints específicos**: reduz risco e simplifica auditoria.
- **Reusar `seo_optimization_history`**: evita novo schema e aproveita rollback.
- **Descrição**: aplicar via `PUT /items/{id}/description` com `plain_text`.

## Risks / Trade-offs
- Snapshot do item não inclui descrição no GET `/items/{id}`.
  - Mitigação: buscar descrição separadamente e incluir no snapshot para change_type `description`.

## Migration Plan
1. Deploy endpoints de apply.
2. Operar manualmente (usuário aplica e valida).
3. Se necessário, evoluir para batch/auto-apply com feature flag.

## Open Questions
- Expor histórico/rollback diretamente na tela da Ficha Técnica (UI) ou manter no módulo SEO Intelligence?
- Políticas de validação: limitar tamanho da descrição; sanitização; bloqueio por palavras proibidas.
