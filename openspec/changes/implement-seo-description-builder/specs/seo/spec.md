## ADDED Requirements
### Requirement: Structured Description Builder
The system SHALL generate a structured description composed of four blocks: benefits, specifications, compatibility, and FAQ.

#### Scenario: Build full description
- **WHEN** a product item and keyword distribution are provided
- **THEN** the system returns all four blocks and a combined description

#### Scenario: Block-specific generation
- **WHEN** a block type is requested
- **THEN** the system returns only that block content

### Requirement: Context Injection
The system SHALL detect applicable contexts from item data and inject context phrases into generated text.

#### Scenario: Context detected
- **WHEN** item title/description contains context indicators
- **THEN** the relevant context phrases are injected

#### Scenario: No context detected
- **WHEN** no indicators are present
- **THEN** default category contexts are used

### Requirement: Long-Tail Keyword Generation
The system SHALL generate long-tail keyword variations based on title and category context.

#### Scenario: Title-based generation
- **WHEN** a title is provided
- **THEN** the system returns unique long-tail variations
