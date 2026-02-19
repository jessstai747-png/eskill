# Change: Brevo Lists Management (Marketing Segmentation)

## Purpose
Add first-class list management to the existing Brevo integration, enabling:
- CRUD of Brevo contact lists
- Adding/removing contacts to/from lists
- Cache + invalidation for read endpoints
- Consistent error handling, validation, and monitoring

## Expected Behavior
- Authenticated internal endpoints expose list management without leaking API keys.
- Read operations are cached (TTL configurable) and invalidated after writes.
- Errors returned by Brevo are mapped to consistent internal responses.

## Dependencies / Integration Points
- Uses existing `BrevoClient` (HTTP/auth/retry/logging).
- Uses `CacheService` for read-through caching and invalidation.
- Uses `ValidationService` for input validation.
- Exposed via `BrevoIntegrationController` and API routes.

