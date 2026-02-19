## Context
A fase 5 integra todas as estratégias SEO em um único motor e adiciona monitoramento contínuo. A implementação deve reutilizar serviços existentes e expor endpoints estáveis para otimização, preview e acompanhamento.

## Goals / Non-Goals
- Goals: orquestração completa das estratégias, métricas de performance, histórico e alertas, dashboard simples.
- Non-Goals: refatoração de serviços anteriores ou UI avançada fora do escopo do dashboard SEO.

## Decisions
- O `SEOStrategiesEngine` concentra execução e score geral.
- `SEOMonitoringService` foca em métricas e comparações temporais.
- Jobs executam monitoramento periódico sem bloqueio de requisições.

## Risks / Trade-offs
- Integração de serviços heterogêneos pode aumentar acoplamento → mitigar com interfaces simples.
- Coleta de métricas pode ser incompleta sem dados históricos → mitigar com fallback e campos opcionais.

## Migration Plan
- Criar serviços e controller novos sem quebrar endpoints existentes.
- Adicionar view de dashboard e job de monitoramento.
- Cobrir com testes básicos antes de uso em produção.

## Open Questions
- Persistência de relatórios de otimização em storage ou DB?
