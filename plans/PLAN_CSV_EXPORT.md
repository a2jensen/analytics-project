# Plan: CSV Data Export

## Overview
Add a "Download CSV" button to each data table page so analysts can export raw analytics data for offline analysis in Excel, Google Sheets, or other tools.

## Current State
- Data is only viewable in paginated HTML tables in the browser
- No download/export functionality exists
- Analysts who need raw data must query the database directly

## Implementation

### Step 1: Create CSV Export Helper
**File:** `app/core/CsvExporter.php`

Create a utility class:
- `static function export(array $headers, array $rows, string $filename): void`
  1. Set headers: `Content-Type: text/csv`, `Content-Disposition: attachment; filename="$filename"`
  2. Open `php://output` as a file handle
  3. Write the header row using `fputcsv()`
  4. Loop through `$rows` and write each with `fputcsv()`
  5. Exit after streaming (no further output)

Using `fputcsv()` ensures proper escaping of commas, quotes, and special characters.

### Step 2: Add Export Methods to ReportsController
**File:** `app/controllers/ReportsController.php`

Add a method `exportCsv($section)`:
1. Verify user has access to the section (same auth check as view)
2. Fetch ALL rows from the model (no pagination limit — but cap at 10,000 rows for safety)
3. Define column headers per section:
   - Static: `['ID', 'Session ID', 'URL', 'User Agent', 'Language', ...]`
   - Performance: `['ID', 'Session ID', 'URL', 'TTFB', 'LCP', 'DOM Complete', ...]`
   - Activity: `['ID', 'Session ID', 'URL', 'Type', 'X', 'Y', ...]`
4. Call `CsvExporter::export()` with headers, rows, and filename like `static_data_2026-03-16.csv`

### Step 3: Add Export Buttons to Views
**Files:**
- `app/views/static.php`
- `app/views/performance.php`
- `app/views/activity.php`

Add a download button near the top of the data table section:
```html
<a href="/reports/static/export-csv" class="btn btn-export">Download CSV</a>
```

Style it consistently with other buttons on the page.

### Step 4: Register Routes
**File:** `app/core/Router.php`

Add routes:
- `GET /reports/static/export-csv` → `ReportsController::exportCsv('static')`
- `GET /reports/performance/export-csv` → `ReportsController::exportCsv('performance')`
- `GET /reports/activity/export-csv` → `ReportsController::exportCsv('activity')`

## Files Created/Modified
| File | Action |
|------|--------|
| `app/core/CsvExporter.php` | **Create** — CSV generation utility |
| `app/controllers/ReportsController.php` | **Modify** — Add `exportCsv()` method |
| `app/views/static.php` | **Modify** — Add download button |
| `app/views/performance.php` | **Modify** — Add download button |
| `app/views/activity.php` | **Modify** — Add download button |
| `app/core/Router.php` | **Modify** — Register CSV export routes |

## Manual Testing

### Test 1: CSV Downloads Successfully
1. Log in as `admin`
2. Navigate to `/reports/static`
3. Click "Download CSV"
4. Confirm a `.csv` file downloads with the correct filename (e.g., `static_data_2026-03-16.csv`)

### Test 2: CSV Content Matches Database
1. Download the static CSV
2. Open in Excel or Google Sheets
3. Compare the first 5 rows against what's shown on the web page
4. Confirm column headers match and data values are identical

### Test 3: CSV Handles Special Characters
1. Ensure the database contains records with commas, quotes, or special characters in fields (e.g., user agent strings)
2. Download the CSV
3. Open it — confirm special characters are properly escaped (no broken columns)

### Test 4: All Three Sections Work
1. Download CSV from `/reports/static/export-csv` — confirm it has static data columns
2. Download CSV from `/reports/performance/export-csv` — confirm it has performance columns
3. Download CSV from `/reports/activity/export-csv` — confirm it has activity columns

### Test 5: Access Control Enforced
1. Log in as a `viewer` (no section access)
2. Try to directly visit `/reports/static/export-csv`
3. Confirm you get a 403 error, not a CSV download

### Test 6: Large Dataset Performance
1. Ensure a table has 1,000+ rows
2. Click "Download CSV"
3. Confirm the download completes without timeout (under 10 seconds)
4. Confirm the CSV contains all rows
