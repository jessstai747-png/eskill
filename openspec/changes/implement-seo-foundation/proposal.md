# Change: Implement SEO Foundation (Phase 1)

## Why
This change implements Phase 1 of the Advanced SEO System roadmap. It establishes the foundational layer for synonym expansion and semantic scoring, which are prerequisites for subsequent phases (Keyword Distribution, Description Builder, etc.).

## What Changes
- Adds database schema for synonym hierarchies and use contexts.
- Implements `SynonymExpansionService` for managing 4-level synonym hierarchies.
- Implements `SemanticScoreService` for calculating keyword relevance.
- Adds API endpoints for synonym management and score calculation.

## Impact
- **New Capability**: `seo` (Search Engine Optimization)
- **Database**: New tables `seo_synonym_hierarchy` and `seo_use_contexts`.
- **Services**: New services in `App\Services\SEO namespace`.
