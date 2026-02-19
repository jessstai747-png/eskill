## 1. Database
- [x] 1.1 Create migration `database/migrations/2026_01_22_create_seo_synonyms_tables.sql` <!-- Table exists with 22 records, created via bin/apply-migrations.php -->
- [x] 1.2 Run migration to create `seo_synonym_hierarchy` and `seo_use_contexts` <!-- Verified in docs/SEO_STRATEGIES_STATUS_FINAL.md -->

## 2. Services
- [x] 2.1 Create `App\Services\SEO\SynonymExpansionService` class <!-- app/Services/SEO/SynonymExpansionService.php exists -->
- [x] 2.2 Create `App\Services\SEO\SemanticScoreService` class <!-- app/Services/SEO/SemanticScoreService.php exists -->

## 3. API
- [x] 3.1 Register routes in `routes.php` or `app/Routes/api.php` <!-- Verified SEO routes exist -->
- [x] 3.2 Implement `App\Controllers\Api\SeoSynonymsController` (or methods in existing controller) <!-- Methods exist in SEOToolsController -->

## 4. Testing
- [x] 4.1 Create unit test `tests/Unit/Services/SEO/SynonymExpansionServiceTest.php` <!-- File exists -->
- [x] 4.2 Run tests and verify coverage <!-- All 774 unit tests passing -->
