## ADDED Requirements

### Requirement: Synonym Hierarchy Management
The system SHALL support a 4-level synonym hierarchy for SEO optimization.

#### Scenario: Identify Level 1 (Generic)
- **WHEN** analyzing a title like "Bauleto 41 Litros"
- **THEN** it identifies "bauleto" as level 1 (generic) term
- **AND** designates destination field as 'title'

#### Scenario: Identify Level 2 (Qualified)
- **WHEN** analyzing "bau traseiro"
- **THEN** it identifies as level 2
- **AND** designates destination field as 'model'

### Requirement: Semantic Scoring
The system SHALL calculate a semantic relevance score for keywords based on hierarchy and usage context.

#### Scenario: High Relevance Score
- **WHEN** a keyword is in the direct synonym hierarchy
- **THEN** the score is calculated based on the hierarchy weight (e.g., 1.0 for level 1)

#### Scenario: Contextual Relevance
- **WHEN** a keyword matches a specific use context (e.g., "delivery")
- **THEN** the score is boosted by the context weight
