# Catalog Clone Export and Monitoring API Guide

## Export Endpoints

### POST `/api/clone/export/items/csv`

Generates a CSV export for cloned items.

Request body:

```json
{
  "filters": {
    "days": 30,
    "status": "active"
  }
}
```

### POST `/api/clone/export/items/json`

Generates a JSON export for cloned items.

Request body:

```json
{
  "filters": {
    "days": 30,
    "status": "active"
  }
}
```

### POST `/api/clone/export/jobs`

Generates a CSV export for clone jobs.

Request body:

```json
{
  "filters": {
    "days": 30
  }
}
```

### POST `/api/clone/export/metrics`

Generates a JSON metrics export.

Request body:

```json
{
  "period": "30d",
  "filters": {
    "days": 30
  }
}
```

### POST `/api/clone/export/report`

Generates a full HTML report.

Request body:

```json
{
  "period": "30d",
  "filters": {
    "days": 30
  }
}
```

### Shared Success Contract

All export creation endpoints now return:

```json
{
  "success": true,
  "filename": "clone_items_2026-03-29_120000.csv",
  "file": "clone_items_2026-03-29_120000.csv",
  "download_url": "/api/clone/export/download/clone_items_2026-03-29_120000.csv",
  "scope": "items",
  "format": "csv",
  "size_bytes": 15360,
  "created_at": "2026-03-29T12:00:00+00:00"
}
```

Compatibility notes:

- `filename` remains available for older consumers
- `file` is provided for current dashboard integrations
- `download_url` is the preferred field for new clients

### GET `/api/clone/export/list`

Lists available exports for the authenticated account.

Response:

```json
{
  "exports": [
    {
      "filename": "clone_report_2026-03-29_120000.html",
      "file": "clone_report_2026-03-29_120000.html",
      "download_url": "/api/clone/export/download/clone_report_2026-03-29_120000.html",
      "scope": "report",
      "format": "html",
      "item_count": 12,
      "size_bytes": 15088,
      "created_at": "2026-03-29T12:00:00+00:00"
    }
  ],
  "count": 1
}
```

### GET `/api/clone/export/download/{filename}`

Downloads a previously generated export. The endpoint resolves files only from the clone export directory and sanitizes the filename with basename rules.

## Monitoring Endpoints

### GET `/api/catalog/clone/monitoring/health`

Returns flattened health fields for the current monitoring dashboard and also includes the nested `health` payload for compatibility.

Response:

```json
{
  "success": true,
  "status": "degraded",
  "legacy_status": "warning",
  "issues": [
    "Taxa de erro alta: 18.0%"
  ],
  "pending_jobs": 12,
  "queue_breakdown": {
    "legacy_pending": 2,
    "batch_pending": 5,
    "batch_processing": 5
  },
  "health": {
    "status": "degraded",
    "legacy_status": "warning"
  }
}
```

### GET `/api/catalog/clone/monitoring/alerts`

Supports both compatibility query styles:

- `?unacknowledged=1`
- `?acknowledged=false`
- `?acknowledged=all`

## Operational Notes

- Export files are stored under `storage/exports/clone`
- Export metadata is persisted in `clone_export_logs`
- The operations dashboard now exposes inline feedback and an accessible export history modal
- Queue health now considers both legacy `jobs` and `catalog_clone_jobs`

## Rollback Notes

- Revert application code for export and monitoring handlers
- Revert the dashboard view if the new history modal causes regressions
- Keep `clone_export_logs` as an additive table unless a full schema rollback is required
