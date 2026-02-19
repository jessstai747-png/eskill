## ADDED Requirements
### Requirement: Hybrid Keyword Sourcing
The system SHALL retrieve keywords using a hybrid strategy that prefers cached database data, then ML API data, and finally AI-generated expansion.

#### Scenario: Cache hit
- **WHEN** cached keywords exist for a category
- **THEN** the system returns cached keywords without calling ML API or AI

#### Scenario: Cache miss with ML API
- **WHEN** no cached keywords exist and ML API is available
- **THEN** the system returns ML API keywords and stores them in cache

#### Scenario: ML API unavailable
- **WHEN** cached keywords are missing and ML API fails
- **THEN** the system uses AI expansion and stores results in cache

### Requirement: Field-Aware Keyword Distribution
The system SHALL distribute keywords into title, model, attributes, and description fields following field limits and weights.

#### Scenario: Field limits applied
- **WHEN** more keywords are available than allowed for a field
- **THEN** only the top-ranked keywords up to the field limit are returned

#### Scenario: Weighted prioritization
- **WHEN** keywords are distributed across fields
- **THEN** higher-weight fields (e.g., title) receive higher-priority keywords

### Requirement: Keyword Density Validation
The system SHALL calculate keyword density in a text and return status against target thresholds.

#### Scenario: Density within range
- **WHEN** keyword density is between ideal limits
- **THEN** status is reported as acceptable

#### Scenario: Density too high
- **WHEN** keyword density exceeds maximum threshold
- **THEN** status indicates over-optimization risk
