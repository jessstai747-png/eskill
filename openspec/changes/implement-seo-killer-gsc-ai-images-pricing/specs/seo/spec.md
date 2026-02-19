## ADDED Requirements
### Requirement: SEO Killer GSC Connection Management
The system SHALL allow Mercado Livre accounts to connect a Google Search Console property to SEO Killer using OAuth2, persist access/refresh tokens, and expose connection status via API.

#### Scenario: Connect account to GSC
- **WHEN** a user clicks "Conectar Search Console" no dashboard SEO Killer
- **THEN** the system redirects to Google OAuth2, exchanges the authorization code for tokens, and stores them in `seo_gsc_auth`.

#### Scenario: Check GSC connection status
- **WHEN** the dashboard calls the GSC status endpoint
- **THEN** the system returns whether the account is connected and basic metadata (e.g. `expires_at`, `property_id`).

### Requirement: SEO Killer GSC Performance Dashboard
The system SHALL expose aggregated Search Console metrics (clicks, impressions, CTR, average position, top queries) for use in the SEO Killer dashboard.

#### Scenario: Load GSC dashboard when connected
- **WHEN** the account is connected to GSC and the dashboard loads data for a recent period
- **THEN** the system returns KPIs and a time-series dataset suitable for charts plus a list of top queries.

#### Scenario: Handle disconnected GSC account
- **WHEN** the dashboard requests data and the account is not connected to GSC
- **THEN** the system returns an empty dataset and a flag indicating disconnection, without throwing errors.

### Requirement: AI Image Optimization with Real ML Images
The system SHALL analyze images of a Mercado Livre listing using real picture data from the Mercado Livre API, identify quality issues, and support reordering, removal, and upload operations that persist back to Mercado Livre.

#### Scenario: Analyze images for an item
- **WHEN** the AI Images endpoint is called for a given item id
- **THEN** the system fetches the item pictures from Mercado Livre, evaluates quality and composition, and returns a structured analysis (scores, issues, recommendations, suggested order, duplicates).

#### Scenario: Apply image reorder/remove/upload
- **WHEN** the client submits a reorder/remove/upload request for listing images
- **THEN** the system updates the item pictures on Mercado Livre accordingly and returns the updated state.

### Requirement: AI Pricing Using Real Data
The system SHALL compute pricing suggestions based on the current price from Mercado Livre or local `items` table, historical price/performance data, competitor prices, and demand indicators.

#### Scenario: Suggest price with full data
- **WHEN** pricing suggestion is requested for an item with available current price, history, competitor and demand data
- **THEN** the system returns a recommended price and explanation derived from these real data sources.

#### Scenario: Handle missing historical data gracefully
- **WHEN** pricing suggestion is requested for an item with no historical data
- **THEN** the system falls back to available signals (current price, competitors, demand proxies) and still returns a safe recommendation with degraded confidence.
