## ADDED Requirements
### Requirement: Hidden Fields Generation
The system SHALL suggest and generate values for hidden fields (KEYWORDS, MPN, LINE) based on item data and available synonyms.

#### Scenario: Generate hidden fields
- **WHEN** an item title and attributes are provided
- **THEN** the system returns values for KEYWORDS, MPN, and LINE

#### Scenario: Apply hidden fields
- **WHEN** generated hidden fields are provided with an item id
- **THEN** the system applies the fields via the ML API and returns the response

### Requirement: Search Coverage Analysis
The system SHALL analyze coverage across search types and calculate a coverage score with gaps and suggestions.

#### Scenario: Analyze coverage
- **WHEN** an item payload is provided
- **THEN** the system returns coverage by type, a score, and detected gaps

#### Scenario: Suggest improvements
- **WHEN** coverage gaps are detected
- **THEN** the system returns actionable improvement suggestions

### Requirement: Compatibility Listing
The system SHALL provide compatibility lists per category and generate compatibility text.

#### Scenario: List compatibility
- **WHEN** a category id is provided
- **THEN** the system returns compatibility entries for that category

#### Scenario: Generate compatibility text
- **WHEN** a list of compatibilities is provided
- **THEN** the system returns a readable compatibility text
