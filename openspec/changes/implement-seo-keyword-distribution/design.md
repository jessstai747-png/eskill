## Context
Phase 2 introduces keyword distribution across fields with differing index weights and density constraints. It must operate reliably with hybrid keyword sources (DB cache, ML API, AI) and degrade gracefully.

## Goals / Non-Goals
- Goals: Field-aware keyword distribution, density validation, hybrid sourcing with cache invalidation.
- Non-Goals: Full SEO dashboard integration, long-term monitoring, or UI work.

## Decisions
- Use a hybrid source priority: DB cache → ML API → AI expansion.
- Apply field limits and weighting rules from the roadmap (title/model/attributes/description).
- Density validation MUST return per-keyword metrics and status bands.

## Risks / Trade-offs
- AI output variance → mitigated by caching and fallback to ML/DB.
- Field overstuffing → mitigated by density limits and per-field caps.

## Migration Plan
- Add/finish services and endpoints without breaking existing Phase 1 APIs.
- Deploy behind existing routes and keep fallbacks for missing data.

## Open Questions
- Should density thresholds be configurable per category?
