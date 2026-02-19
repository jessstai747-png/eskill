## Context
A fase 4 adiciona campos ocultos indexáveis e métricas de cobertura de busca. A implementação deve ser determinística, sem dependências externas novas, e reutilizar dados já existentes (título, atributos, sinônimos e distribuição).

## Goals / Non-Goals
- Goals: gerar valores coerentes para KEYWORDS/MPN/LINE, medir cobertura por tipo de busca, sugerir melhorias práticas, listar compatibilidades por categoria.
- Non-Goals: chamadas externas novas, UI ou dashboard (isso é fase 5).

## Decisions
- Usar heurísticas simples e explicáveis para gerar campos ocultos.
- Score de cobertura baseado em presença de termos por tipo de busca.
- Compatibilidades determinadas por dicionários locais por categoria.

## Risks / Trade-offs
- Heurísticas podem gerar valores genéricos → mitigação: priorizar atributos e sinônimos existentes.
- Cobertura pode superestimar presença → mitigação: exigir termos mínimos por tipo.

## Migration Plan
- Adicionar serviços e métodos novos sem alterar contratos existentes.
- Expor endpoints via controller já existente de SEO.
- Cobrir com testes unitários antes de usar em produção.

## Open Questions
- Devemos persistir os campos ocultos gerados em cache para reuso?
