## 1. Services
- [x] 1.1 Implement `KeywordDistributionService` distribution, classification, and density validation <!-- app/Services/SEO/KeywordDistributionService.php exists -->
- [x] 1.2 Implement/confirm `KeywordSourceService` hybrid sourcing + cache invalidation <!-- app/Services/SEO/KeywordSourceService.php exists -->
- [x] 1.3 Update `KeywordResearchService` integrations as needed <!-- Integrated in KeywordSourceService -->

## 2. API
- [x] 2.1 Register routes for distribution, classification, density validate/calc, cache invalidation <!-- Routes in app/Routes/api.php -->
- [x] 2.2 Implement controller handlers for Phase 2 endpoints <!-- SEOToolsController handlers -->

## 3. Tests
- [x] 3.1 Add unit tests for distribution and density validation <!-- tests/Unit/Services/SEO/KeywordDistributionServiceTest.php - 4 tests passing -->
- [x] 3.2 Validate fallback behavior when ML API/AI fails <!-- Covered in unit tests -->
