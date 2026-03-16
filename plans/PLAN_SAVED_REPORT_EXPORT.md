# Plan: Saved Report PDF Export

## Overview
Add an "Export" button to the saved report view page (`/reports/saved/view?id=X`) that generates a PDF of the saved report's snapshot data using dompdf — the same library already used by `api/export.php` for live report exports.

## Current State
- Live report pages (static, performance, activity) have an Export button that calls `api/export.php?type={section}`, which queries the full live table and renders it as a PDF via dompdf
- Saved reports at `/reports/saved/view?id=X` have no export capability
- Saved reports store their data as a JSON snapshot in the `snapshot_data` column — no DB query needed, the data is already embedded in the report record

## Key Difference from Live Exports
The existing `api/export.php` queries the live database tables. For saved reports, the data is already frozen in the `snapshot_data` JSON column. So the export handler needs to:
1. Look up the saved report by ID
2. Decode the `snapshot_data` JSON
3. Render that snapshot (not a live query) into the same HTML table + dompdf pipeline

## Implementation

### Step 1: Add Saved Report Export Handler
**File:** `api/export.php`

Add a new case for `type=saved` that accepts an `id` parameter:

```
/api/export.php?type=saved&id=2
```

Logic:
1. Read `$_GET['id']`, cast to int, reject if missing/zero
2. Query the `reports` table by ID (join with `users` for the creator username)
3. Decode `snapshot_data` JSON to get the rows array
4. If no report found or empty snapshot, return 404
5. Use the report's `title` as the PDF title
6. Include commentary and metadata (section, creator, created_at) above the table
7. Render the same HTML table structure as the existing cases
8. Generate and stream the PDF via dompdf

### Step 2: Add Export Button to Saved Report View
**File:** `app/views/reports/view.php`

Add an Export button next to the report title (matching the style of the live report export buttons):

```html
<button class="btn" onclick="window.open('/api/export.php?type=saved&id=<?= (int)$report['id'] ?>', '_blank')">Export</button>
```

Place it after the `<h1>` title, same position as on live report pages.

## Files Modified
| File | Action |
|------|--------|
| `api/export.php` | **Modify** — Add `saved` case with ID-based snapshot lookup |
| `app/views/reports/view.php` | **Modify** — Add Export button |

## Manual Testing

### Test 1: Export Button Visible
1. Log in and navigate to `/reports/saved`
2. Click into any saved report
3. Confirm an "Export" button appears on the page

### Test 2: PDF Generates Successfully
1. Click the Export button on a saved report
2. Confirm a PDF opens in a new tab (or downloads, depending on browser)
3. Confirm the PDF contains the report title, metadata, commentary, and data table

### Test 3: PDF Content Matches View
1. Compare the data table in the PDF against the web view
2. Confirm same columns, same rows, same values

### Test 4: Missing Report Returns Error
1. Visit `/api/export.php?type=saved&id=99999` directly
2. Confirm a 404 response, not a crash

### Test 5: Missing ID Returns Error
1. Visit `/api/export.php?type=saved` (no id param)
2. Confirm a 400 response
