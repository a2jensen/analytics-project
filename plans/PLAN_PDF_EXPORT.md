# Plan: PDF Report Export

## Overview
Allow users to export any report page or saved report as a downloadable PDF using the already-installed dompdf library. This enables offline sharing and printing of analytics reports.

## Current State
- dompdf 3.1 is already installed via Composer (`vendor/dompdf/dompdf`)
- No PDF generation functionality is implemented
- Reports are view-only in the browser
- Saved reports display data tables and charts but cannot be downloaded

## Implementation

### Step 1: Create PDF Service
**File:** `app/core/PdfExporter.php`

Create a class that wraps dompdf:
- `__construct()` — Instantiate `Dompdf\Dompdf` with options (paper size A4, landscape orientation for wide tables)
- `generateFromHtml(string $html, string $filename): void` — Load HTML, render, and stream as a download with the given filename
- Options to set: `isRemoteEnabled` = false (no external resources needed), `defaultFont` = 'Helvetica'

### Step 2: Create PDF-Specific View Templates
**File:** `app/views/pdf/report.php`

Create a standalone HTML template for PDFs (no nav bar, no scripts, no Chart.js — charts don't render in dompdf). Include:
- Inline CSS (dompdf doesn't load external stylesheets well)
- Report title and metadata (section, date range, creator)
- Data table with the same columns as the web view
- Page header/footer with generation timestamp
- Clean, print-friendly styling (no dark backgrounds, readable fonts)

### Step 3: Add Export Route for Live Reports
**File:** `app/controllers/ReportsController.php`

Add a new method `exportPdf($section)`:
1. Verify the user has access to the requested section
2. Fetch all data from the corresponding model (same as the view method but without pagination — or with a reasonable cap like 500 rows)
3. Render the PDF view template with the data using `ob_start()` / `ob_get_clean()` to capture HTML
4. Pass the HTML to `PdfExporter::generateFromHtml()`
5. Stream the PDF to the browser with `Content-Type: application/pdf` and `Content-Disposition: attachment`

### Step 4: Add Export Route for Saved Reports
**File:** `app/controllers/ReportController.php`

Add a method `exportPdf($id)`:
1. Fetch the saved report by ID
2. Decode the `snapshot_data` JSON
3. Render the PDF view template with the snapshot data + report metadata
4. Stream as PDF download

### Step 5: Add Export Buttons to Views
**Files:**
- `app/views/static.php` — Add "Export PDF" button/link
- `app/views/performance.php` — Add "Export PDF" button/link
- `app/views/activity.php` — Add "Export PDF" button/link
- `app/views/reports/show.php` — Add "Export PDF" button for saved reports

Each button links to the corresponding export route:
```html
<a href="/reports/static/export-pdf" class="btn btn-export">Export PDF</a>
```

### Step 6: Register Routes
**File:** `app/core/Router.php`

Add new routes:
- `GET /reports/static/export-pdf` → `ReportsController::exportPdf('static')`
- `GET /reports/performance/export-pdf` → `ReportsController::exportPdf('performance')`
- `GET /reports/activity/export-pdf` → `ReportsController::exportPdf('activity')`
- `GET /reports/saved/{id}/export-pdf` → `ReportController::exportPdf($id)`

## Files Created/Modified
| File | Action |
|------|--------|
| `app/core/PdfExporter.php` | **Create** — dompdf wrapper service |
| `app/views/pdf/report.php` | **Create** — Print-friendly PDF HTML template |
| `app/controllers/ReportsController.php` | **Modify** — Add `exportPdf()` method |
| `app/controllers/ReportController.php` | **Modify** — Add `exportPdf()` method |
| `app/views/static.php` | **Modify** — Add export button |
| `app/views/performance.php` | **Modify** — Add export button |
| `app/views/activity.php` | **Modify** — Add export button |
| `app/views/reports/show.php` | **Modify** — Add export button |
| `app/core/Router.php` | **Modify** — Register export routes |

## Manual Testing

### Test 1: PDF Downloads from Live Report
1. Log in as `admin`
2. Navigate to `/reports/static`
3. Click "Export PDF"
4. Confirm a PDF file downloads (not an error page)
5. Open the PDF — confirm it contains the data table with correct columns and data

### Test 2: PDF Downloads from Saved Report
1. Navigate to `/reports/saved`
2. Open any saved report
3. Click "Export PDF"
4. Confirm the PDF contains the report title, commentary, and data table

### Test 3: PDF Content Accuracy
1. Export `/reports/performance` as PDF
2. Compare the first few rows in the PDF against the web view
3. Confirm data matches exactly (same values, same column order)

### Test 4: Access Control Enforced
1. Log in as a `viewer` (no section access)
2. Try to directly access `/reports/static/export-pdf`
3. Confirm you get a 403 Forbidden (not a PDF)

### Test 5: Large Dataset Handling
1. Ensure the database has 500+ rows in a table
2. Export that section as PDF
3. Confirm the PDF generates without timeout or memory error
4. Confirm the PDF has a reasonable file size (under 5MB)

### Test 6: PDF Formatting
1. Open an exported PDF
2. Confirm: no broken tables, readable font size, landscape orientation for wide tables
3. Confirm: no nav bar, no JavaScript artifacts, clean print-friendly layout
4. Confirm: header shows report title and footer shows generation timestamp
