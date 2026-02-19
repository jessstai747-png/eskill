# Change: Implement SEO Keyword Distribution (Phase 2)

## Why
Phase 2 of the SEO roadmap introduces intelligent keyword distribution, field weighting, and density validation. This unlocks the ability to place keywords correctly across title/model/attributes/description while preserving search quality and performance.

## What Changes
- Add Keyword Distribution logic (field weighting, limits, density validation).
- Introduce hybrid keyword sourcing orchestration (database + ML API + AI) for reliable keyword pools.
- Expand keyword classification and competition scoring usage where needed.
- Add API endpoints to distribute, classify, validate density, and invalidate keyword cache by category.

## Impact
- **Capability**: SEO (keyword distribution and density validation)
- **Services**: `KeywordDistributionService`, `KeywordSourceService`, updates in `KeywordResearchService`
- **API**: new/updated endpoints for distribution, classification, and density checks
