## ADDED Requirements

### Requirement: Brevo Lists CRUD
The system SHALL support creating, reading, updating, listing, and deleting Brevo contact lists.

#### Scenario: List lists
- **WHEN** an authenticated user calls the internal lists endpoint
- **THEN** the system SHALL return the list collection from Brevo
- **AND** the result SHOULD be cached for a configurable TTL

#### Scenario: Create list
- **WHEN** an authenticated user creates a list providing a non-empty name
- **THEN** the system SHALL create the list in Brevo
- **AND** the system SHALL invalidate list caches

#### Scenario: Update list
- **WHEN** an authenticated user updates a list name
- **THEN** the system SHALL update the list in Brevo
- **AND** the system SHALL invalidate list caches

#### Scenario: Delete list
- **WHEN** an authenticated user deletes a list
- **THEN** the system SHALL delete the list in Brevo
- **AND** the system SHALL invalidate list caches

### Requirement: Membership Management
The system SHALL support adding/removing contacts to/from a list.

#### Scenario: Add contacts to list
- **WHEN** an authenticated user submits a list id and an array of emails
- **THEN** the system SHALL add those contacts to the Brevo list
- **AND** the system SHALL invalidate related caches

#### Scenario: Remove contacts from list
- **WHEN** an authenticated user submits a list id and an array of emails
- **THEN** the system SHALL remove those contacts from the Brevo list
- **AND** the system SHALL invalidate related caches

### Requirement: Validation and Error Handling
The system SHALL validate inputs and SHALL not leak secrets.

#### Scenario: Invalid input
- **WHEN** list id is invalid or emails are invalid
- **THEN** the system SHALL return a controlled 400 response

#### Scenario: Rate limiting
- **WHEN** Brevo responds with HTTP 429
- **THEN** the system SHALL apply retries with backoff and return a consistent error if retries are exhausted

