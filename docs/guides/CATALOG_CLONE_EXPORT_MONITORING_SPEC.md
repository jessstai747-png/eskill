# Catalog Clone Export and Monitoring Specification

## Roadmap Analysis

The current product roadmap shows the catalog clone area as the most operationally valuable next slice because it already supports seller-based cloning, batch jobs, monitoring, and exports, but still has clear operator-facing gaps in reliability and usability. The highest-priority backlog items are:

1. Export pipeline reliability for clone operators
2. Monitoring compatibility hardening for batch and legacy queues
3. Operator-facing export history and download flows

These changes maximize business value by reducing failed operational workflows, improving observability for clone jobs, and closing documentation gaps on a revenue-adjacent module without introducing risky architectural churn.

## Feature Set

### Feature 1: Clone Export Reliability Pack

#### Goal

Make clone exports consistently downloadable, auditable, and backward compatible across CSV, JSON, metrics, and full-report outputs.

#### Data Model

Add a persistent export log table:

| Field | Type | Notes |
| --- | --- | --- |
| id | BIGINT PK | Surrogate key |
| account_id | INT | Target account that generated the export |
| export_scope | VARCHAR(50) | `items`, `jobs`, `metrics`, `report` |
| export_format | VARCHAR(20) | `csv`, `json`, `html` |
| filename | VARCHAR(255) | File basename only |
| item_count | INT | Exported record count |
| size_bytes | BIGINT | File size at generation time |
| filters_json | JSON nullable | Serialized filters/options |
| created_at | TIMESTAMP | Audit timestamp |

#### API Endpoints

Existing endpoints remain in place:

- `POST /api/clone/export/items/csv`
- `POST /api/clone/export/items/json`
- `POST /api/clone/export/jobs`
- `POST /api/clone/export/metrics`
- `POST /api/clone/export/report`
- `GET /api/clone/export/list`
- `GET /api/clone/export/download/{filename}`

#### Response Contract

All export creation endpoints return:

```json
{
  "success": true,
  "filename": "clone_items_2026-03-29_120000.csv",
  "file": "clone_items_2026-03-29_120000.csv",
  "download_url": "/api/clone/export/download/clone_items_2026-03-29_120000.csv",
  "scope": "items",
  "format": "csv",
  "total_items": 42,
  "size_bytes": 15360,
  "created_at": "2026-03-29T12:00:00+00:00"
}
```

Backward compatibility is preserved through the legacy `filename` field while adding `file` and `download_url` for the current UI.

#### Integration Points

- `CloneDataExportService` generates, normalizes, and logs exports
- `CloneAdvancedController` delegates file resolution to the service
- filesystem storage remains under `storage/exports/clone`
- database persistence records export metadata for history and audit

### Feature 2: Export History UX

#### Goal

Provide a usable export history workflow from the clone operations dashboard without relying on alerts or console output.

#### UI Components

- Inline export status panel for success and failure states
- Export history modal with accessible table markup
- Download action per row
- Empty state and loading state messaging

#### Interaction Flow

1. Operator clicks an export button
2. UI calls the corresponding export endpoint
3. UI renders a success message and direct download action
4. History modal lists prior exports sorted by newest first
5. Operator can download directly from the history modal

#### Accessibility Requirements

- Modal uses semantic headings and table headers
- Status messages use `role="status"` and `aria-live="polite"`
- Buttons include descriptive text, not icon-only controls
- Empty and error states remain keyboard reachable and screen-reader visible

### Feature 3: Monitoring Compatibility Hardening

#### Goal

Align monitoring responses with the existing monitoring dashboard, include batch queue visibility, and preserve compatibility for current consumers.

#### API Endpoints

Existing endpoints remain in place:

- `GET /api/catalog/clone/monitoring/health`
- `GET /api/catalog/clone/monitoring/alerts`
- `POST /api/catalog/clone/monitoring/alerts/{id}/acknowledge`
- `GET /api/catalog/clone/monitoring/flags`
- `PUT /api/catalog/clone/monitoring/flags/{name}`
- `GET /api/catalog/clone/monitoring/report`

#### Response Contract

The health endpoint returns both flattened data and nested compatibility payload:

```json
{
  "success": true,
  "status": "degraded",
  "legacy_status": "warning",
  "health": {
    "status": "degraded",
    "legacy_status": "warning"
  },
  "pending_jobs": 12,
  "queue_breakdown": {
    "legacy_pending": 2,
    "batch_pending": 5,
    "batch_processing": 5
  }
}
```

The alerts endpoint accepts either `unacknowledged=1` or `acknowledged=all|false` so both old and current dashboards remain supported.

#### Integration Points

- `CloneMonitoringService` aggregates metrics from `jobs` and `catalog_clone_jobs`
- `CatalogCloneController` exposes compatibility-safe response shapes
- clone monitoring dashboard consumes flattened health fields without extra adapters

## Testing Strategy

### Test-Driven Development

Implementation follows red-green-refactor for:

- export response normalization
- export file resolution and history listing
- monitoring health compatibility payload
- queue aggregation logic for legacy and batch jobs
- dashboard export UX source-level regression coverage

### Required Coverage

- Unit tests for service-layer contracts and edge cases
- Controller/source regression tests for compatibility-sensitive endpoints
- View regression tests for export status and history behavior
- Critical path validation through PHPUnit in the current environment

## Performance and SLA Targets

- Export history listing must remain bounded and ordered newest-first
- Download resolution must avoid directory traversal and use basename sanitization
- Monitoring health checks must aggregate queue counters without blocking clone execution
- File generation remains synchronous for operator-triggered exports and must return within the current dashboard interaction budget

## Deployment and Rollback

### Deployment Artifacts

- application code changes
- new migration for `clone_export_logs`
- updated dashboard UI
- updated operator and API documentation

### Rollback Plan

1. Revert application code for export and monitoring endpoints
2. Revert dashboard view changes
3. Keep `clone_export_logs` table in place because it is additive and non-breaking, or archive it if strict rollback is required
4. Clear generated exports if an invalid release produced corrupted files

## Environment Validation Plan

- Development: run focused PHPUnit suites and PHP syntax checks
- Staging-like local environment: validate export creation, list, and download flows
- Production-like constraints: ensure graceful behavior when batch tables or optional log tables are empty

