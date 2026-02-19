# Change: Implement SEO Description Builder (Phase 3)

## Why
Phase 3 completes the description generation layer of the SEO roadmap. It structures descriptions into blocks, injects contextual phrases, and generates FAQ and long-tail keywords so listings are more complete and searchable.

## What Changes
- Implement structured description builder with 4 blocks (benefits, specs, compatibility, FAQ).
- Add context injection for category-aware phrases.
- Add long-tail keyword generation from title/category signals.
- Wire API endpoints for description build, blocks, FAQ, validation, and long-tail generation.
- Add/enable unit tests for the description builder.

## Impact
- **Capability**: SEO (description builder, context injection, long-tail generation)
- **Services**: `DescriptionBuilderService`, `ContextInjectorService`, `LongTailGeneratorService`
- **Config**: `config/seo_faq_templates.php`
- **API**: `SeoDescriptionController` endpoints
