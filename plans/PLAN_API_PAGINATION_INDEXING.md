# Plan: API Pagination & Database Indexing

## Overview
Add `limit`/`offset` query parameter support to all API endpoints and add database indexes on frequently queried columns. This prevents memory exhaustion when datasets grow large and speeds up aggregation queries used on the dashboard and report pages.

## Current State
- API endpoints (`/api/static`, `/api/performance`, `/api/activity`) return ALL rows with no limit
- No database indexes beyond primary keys
- Dashboard aggregation queries (COUNT, AVG, GROUP BY) scan full tables
- Web report pages have pagination (25/page) but the API does not

## Implementation

### Step 1: Add Database Indexes
**File:** `database/indexes.sql` (reference SQL, run manually)

```sql
-- Speed up joins and lookups by session
ALTER TABLE static_data ADD INDEX idx_session_id (session_id);
ALTER TABLE performance_data ADD INDEX idx_session_id (session_id);
ALTER TABLE activity_data ADD INDEX idx_session_id (session_id);

-- Speed up GROUP BY queries on dashboard
ALTER TABLE static_data ADD INDEX idx_network_type (network_type);
ALTER TABLE activity_data ADD INDEX idx_type (type);

-- Speed up date-based filtering (if timestamp columns exist)
ALTER TABLE activity_data ADD INDEX idx_event_timestamp (event_timestamp);
```

### Step 2: Add Pagination to API Handlers
**Files:** `api/static.php`, `api/performance.php`, `api/activity.php`

For the GET (list all) handler in each file:

1. Parse query parameters:
   ```php
   $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 25;
   $offset = isset($_GET['offset']) ? max((int)$_GET['offset'], 0) : 0;
   ```
   - Default: 25 per page
   - Maximum: 100 per page (cap to prevent abuse)
   - Offset minimum: 0

2. Modify the SQL query to append `LIMIT ? OFFSET ?` with bound parameters

3. Add a total count query: `SELECT COUNT(*) FROM table_name`

4. Return paginated response format:
   ```json
   {
     "data": [...],
     "pagination": {
       "total": 1542,
       "limit": 25,
       "offset": 0,
       "has_more": true
     }
   }
   ```

### Step 3: Update Individual Record Endpoints
The GET by ID (`/api/static/{id}`), PUT, and DELETE endpoints don't need pagination — they already operate on single records. No changes needed.

### Step 4: Add Sorting Support (Optional Enhancement)
In each list handler, accept `sort` and `order` parameters:
```php
$allowed_sorts = ['id', 'session_id', 'url'];
$sort = in_array($_GET['sort'] ?? '', $allowed_sorts) ? $_GET['sort'] : 'id';
$order = ($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
```

Use a whitelist approach to prevent SQL injection through sort columns.

## Files Created/Modified
| File | Action |
|------|--------|
| `database/indexes.sql` | **Create** — Index creation SQL |
| `api/static.php` | **Modify** — Add pagination to GET list |
| `api/performance.php` | **Modify** — Add pagination to GET list |
| `api/activity.php` | **Modify** — Add pagination to GET list |

## Manual Testing

### Test 1: Default Pagination
1. `curl https://reporting.angelo-j.xyz/api/static`
2. Confirm response contains a `data` array with at most 25 items and a `pagination` object
3. Confirm `pagination.total` matches the total row count in the database

### Test 2: Custom Limit and Offset
1. `curl "https://reporting.angelo-j.xyz/api/static?limit=5&offset=0"`
2. Confirm exactly 5 records returned
3. `curl "https://reporting.angelo-j.xyz/api/static?limit=5&offset=5"`
4. Confirm the next 5 records (no overlap with previous request)

### Test 3: Maximum Limit Enforced
1. `curl "https://reporting.angelo-j.xyz/api/static?limit=500"`
2. Confirm only 100 records returned (capped at max)

### Test 4: Invalid Parameters Handled
1. `curl "https://reporting.angelo-j.xyz/api/static?limit=-1&offset=-5"`
2. Confirm it doesn't error — falls back to defaults (limit=25, offset=0)

### Test 5: has_more Flag Accuracy
1. Check total count: `curl https://reporting.angelo-j.xyz/api/static` → note `pagination.total`
2. Request with offset near the end: `curl "https://reporting.angelo-j.xyz/api/static?offset={total-2}&limit=25"`
3. Confirm `has_more` is `false` when there are no more records

### Test 6: Index Performance
1. Before adding indexes, run: `EXPLAIN SELECT COUNT(*) FROM activity_data WHERE type = 'click';` — note if it says "full table scan"
2. Add the indexes from `indexes.sql`
3. Run the same EXPLAIN — confirm it now uses the index (`idx_type`)

### Test 7: Single Record Endpoints Unaffected
1. `curl https://reporting.angelo-j.xyz/api/static/1` — confirm single record still returns normally (no pagination wrapper)
2. POST, PUT, DELETE endpoints — confirm they work as before
