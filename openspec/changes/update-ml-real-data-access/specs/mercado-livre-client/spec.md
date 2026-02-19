## ADDED Requirements

### Requirement: API-first real data access
The system SHALL prefer real Mercado Livre API calls over any synthetic fallback data.

#### Scenario: Public data without token
- **WHEN** the system requests public resources (e.g. search, autosuggest, item details, category attributes)
- **THEN** the request SHALL be executed against Mercado Livre without requiring an access token
- **AND** the response SHALL contain real data from Mercado Livre (or a clear error)

#### Scenario: Authenticated operations require linked account
- **WHEN** the system requests user-scoped resources or write operations
- **AND** there is no linked account/token available
- **THEN** the system SHALL return a clear error indicating missing authentication
- **AND** it SHALL NOT return synthetic placeholder content

### Requirement: Deterministic behavior in test environments
In testing environments, the system SHALL avoid real network calls by default.

#### Scenario: Network disabled by default in testing
- **WHEN** APP_ENV is set to `testing`
- **AND** network opt-in is not enabled
- **THEN** Mercado Livre network calls SHALL be blocked
- **AND** the system SHALL return a deterministic error indicating network is disabled

#### Scenario: Network opt-in for integration tests
- **WHEN** APP_ENV is set to `testing`
- **AND** network opt-in is enabled via environment configuration
- **THEN** integration tests MAY perform real Mercado Livre calls
