# Plan: Date Range Filters

## Overview
Add date range picker inputs to report pages so users can filter analytics data by time window instead of always seeing all records. This enables focused analysis of specific time periods.

## Current State
- Report pages show all records with only pagination (no filtering)
- No date-based filtering on any page
- Dashboard aggregation also covers all-time data
- The `activity_data` table has `event_timestamp`, and `performance_data` has timing columns, but they aren't used for filtering

## Implementation

### Step 1: Identify Timestamp Columns
Determine which column to filter on for each section:
- **static_data** — Needs a `created_at` column if one doesn't exist (check schema). If none, this section may not support date filtering without a schema change.
- **performance_data** — Use `page_load_start` or add a `created_at` column
- **activity_data** — Use `event_timestamp`

If `created_at` columns are missing, add them:
```sql
ALTER TABLE static_data ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE performance_data ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
```

### Step 2: Add Date Filter UI to Report Views
**Files:** `app/views/static.php`, `app/views/performance.php`, `app/views/activity.php`

Add a filter form above the data table:
```html
<form method="GET" class="date-filter-form">
    <label for="start_date">From:</label>
    <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate ?? '') ?>">
    <label for="end_date">To:</label>
    <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate ?? '') ?>">
    <button type="submit" class="btn">Apply Filter</button>
    <a href="?" class="btn btn-secondary">Clear</a>
</form>
```

Style the form inline (flexbox row) so it sits neatly above the table.

### Step 3: Update Models to Accept Date Parameters
**Files:** `app/models/StaticModel.php`, `app/models/PerformanceModel.php`, `app/models/ActivityModel.php`

Modify the `getAll()` (or equivalent list method) to accept optional `$startDate` and `$endDate` parameters:

```php
public function getAll($limit, $offset, $startDate = null, $endDate = null) {
    $sql = "SELECT * FROM static_data WHERE 1=1";
    $params = [];

    if ($startDate) {
        $sql .= " AND created_at >= ?";
        $params[] = $startDate . ' 00:00:00';
    }
    if ($endDate) {
        $sql .= " AND created_at <= ?";
        $params[] = $endDate . ' 23:59:59';
    }

    $sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    // ...
}
```

Also update the `getCount()` method to accept the same date parameters so pagination reflects the filtered total.

### Step 4: Update Controllers to Pass Date Parameters
**File:** `app/controllers/ReportsController.php`

In the view method for each section:
1. Read `$_GET['start_date']` and `$_GET['end_date']`
2. Validate they are proper date format (regex or `strtotime()`)
3. Pass to the model's `getAll()` and `getCount()` methods
4. Pass back to the view for form value persistence

### Step 5: Preserve Filters in Pagination Links
**File:** `app/views/layout/pagination.php`

Update pagination links to include the current date filter parameters:
```php
$queryParams = [];
if (!empty($_GET['start_date'])) $queryParams['start_date'] = $_GET['start_date'];
if (!empty($_GET['end_date'])) $queryParams['end_date'] = $_GET['end_date'];
$queryString = http_build_query($queryParams);
```

Append `$queryString` to each pagination link so filters persist when navigating pages.

### Step 6: Add CSS for Filter Form
**File:** `public/css/style.css` (or equivalent)

Add styles for `.date-filter-form`:
- `display: flex; align-items: center; gap: 10px; margin-bottom: 20px;`
- Style date inputs to match existing form elements
- Style the Clear button as a secondary/outline button

## Files Created/Modified
| File | Action |
|------|--------|
| `database/add_timestamps.sql` | **Create** — SQL to add `created_at` columns if missing |
| `app/views/static.php` | **Modify** — Add date filter form |
| `app/views/performance.php` | **Modify** — Add date filter form |
| `app/views/activity.php` | **Modify** — Add date filter form |
| `app/models/StaticModel.php` | **Modify** — Accept date params in queries |
| `app/models/PerformanceModel.php` | **Modify** — Accept date params in queries |
| `app/models/ActivityModel.php` | **Modify** — Accept date params in queries |
| `app/controllers/ReportsController.php` | **Modify** — Parse and pass date params |
| `app/views/layout/pagination.php` | **Modify** — Preserve date params in links |
| `style.css` | **Modify** — Style the filter form |

## Manual Testing

### Test 1: Filter Form Renders
1. Log in and navigate to `/reports/static`
2. Confirm date inputs ("From" and "To") appear above the data table
3. Confirm "Apply Filter" and "Clear" buttons are visible and styled

### Test 2: Filtering by Date Range Works
1. Navigate to `/reports/activity`
2. Set "From" to a date you know has data (check the database)
3. Set "To" to the same date or a narrow range
4. Click "Apply Filter"
5. Confirm only records within that date range appear
6. Confirm the record count and pagination update to reflect filtered results

### Test 3: Filter Values Persist
1. Apply a date filter on any report page
2. Confirm the date inputs still show your selected dates after the page reloads
3. Navigate to page 2 of results
4. Confirm the date filter is still applied (dates still in inputs, filtered data shown)

### Test 4: Clear Button Works
1. Apply a date filter
2. Click "Clear"
3. Confirm the date inputs are empty and all records are shown again

### Test 5: Invalid Dates Handled
1. Manually type an invalid date in the URL: `?start_date=notadate&end_date=alsonotadate`
2. Confirm the page loads without error (invalid dates are ignored, all data shown)

### Test 6: Empty Results
1. Set a date range where no data exists (e.g., far in the future)
2. Confirm the page shows an empty table or "No records found" message (no error)

### Test 7: All Three Sections Work
1. Apply date filters on `/reports/static` — confirm filtering works
2. Apply date filters on `/reports/performance` — confirm filtering works
3. Apply date filters on `/reports/activity` — confirm filtering works
