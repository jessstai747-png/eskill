## ADDED Requirements
### Requirement: SEO Strategies Orchestration
The system SHALL provide an orchestration engine that runs multiple SEO strategies, produces a unified output, and calculates an overall score.

#### Scenario: Full optimization
- **WHEN** an item id is provided for full optimization
- **THEN** the system returns results for all strategies and an overall score

#### Scenario: Partial optimization
- **WHEN** a subset of strategies is provided
- **THEN** the system runs only the selected strategies and returns a partial report

### Requirement: Monitoring & Metrics
The system SHALL collect SEO metrics, compare with previous periods, and emit alerts for significant drops.

#### Scenario: Collect metrics
- **WHEN** an item id is provided
- **THEN** the system returns current performance metrics

#### Scenario: Compare metrics
- **WHEN** a comparison period is requested
- **THEN** the system returns deltas versus the previous period

### Requirement: Dashboard View
The system SHALL expose a dashboard view with score, preview, and history data.

#### Scenario: Dashboard access
- **WHEN** a user opens the SEO dashboard
- **THEN** the system renders scores, preview, and history summaries
