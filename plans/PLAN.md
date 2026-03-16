# Implementation Plan — Reporting Dashboard (PHP MVC)

---

## Rules

### Rendering Strategy

**Static/read-only pages** (current dashboard, reports) → server-side rendered.
Controller fetches data, passes it to a view via `require __DIR__ . '/../views/...'`, browser receives complete HTML.

**Interactive/dynamic components** → client-side rendered.
If a feature lets the user click and directly mutate data (e.g. deleting a row, submitting a form without a full page reload, live filtering), that component must:
1. Have the controller return JSON instead of HTML when the request is an AJAX call or `Accept: application/json`
2. Use a client-side `fetch()` to call the endpoint and update the DOM without a full page reload

The adaptive pattern for a controller method to support both:

```php
$data = $model->getAll();

$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
$accept = $_SERVER['HTTP_ACCEPT'] ?? 'text/html';

if ($isAjax || str_contains($accept, 'application/json')) {
    header('Content-Type: application/json');
    echo json_encode($data);
} else {
    require __DIR__ . '/../views/page.php';
}
```

**Decision rule:** ask "does the user interact with this and expect the page to update without a full reload?" — if yes, it is a client-side rendered component and needs the adaptive pattern above.

---

## Goal
Build a PHP MVC-style analytics reporting app with:
1. Authentication (login / logout, session-based, no forceful browsing)
2. A data table connected to the existing API datastore
3. Charts connected to the existing API datastore

---

## What Already Exists
- `api/` — REST API with router, db connection, and handlers for `static`, `performance`, and `activity`
- `.htaccess` — rewrites `/api/*` to `api/router.php`
- `index.html` — placeholder, will be replaced with `index.php`
- MySQL `analytics` DB with three tables: `static_data`, `performance_data`, `activity_data`

---

## Clarification: Two Routers

| File | Role |
|------|------|
| `api/router.php` | **Existing.** Handles `/api/*` requests, dispatches to API handlers, returns JSON. Not touched. |
| `app/core/Router.php` | **New.** Handles web page routes (`/login`, `/dashboard`, `/reports/*`), dispatches to PHP controllers that render HTML. |

They are completely independent — the `.htaccess` rules keep them separated by path prefix.

---

## Directory Structure (to be created)

```
public_html/
├── index.php                  ← Front controller (replaces index.html)
├── .htaccess                  ← Updated to route non-api, non-asset paths to index.php
├── api/                       ← Existing (unchanged)
├── app/
│   ├── core/
│   │   ├── Router.php         ← Maps URL paths to controllers
│   │   └── Auth.php           ← Session check helpers
│   ├── controllers/
│   │   ├── AuthController.php      ← login / logout logic
│   │   ├── DashboardController.php ← main dashboard
│   │   └── ReportsController.php   ← table + chart pages
│   └── views/
│       ├── layout/
│       │   ├── header.php     ← <html>, <head>, nav bar
│       │   └── footer.php     ← close tags, shared JS
│       ├── login.php
│       ├── dashboard.php
│       ├── static.php         ← table view of static_data
│       ├── performance.php    ← table + chart view of performance_data
│       └── activity.php       ← table + chart view of activity_data
└── public/
    ├── css/
    │   └── style.css
    └── js/
        └── (CDN-loaded: Chart.js)
```

---

## Useful Commands

```bash
# Reload Apache config (no downtime) — use after .htaccess or config changes
sudo systemctl reload apache2

# Full restart — use if reload doesn't pick up changes
sudo systemctl restart apache2

# Test Apache config for syntax errors before reloading
sudo apache2ctl configtest

# Watch live Apache error log (open in a separate terminal while testing)
sudo tail -f /var/log/apache2/error.log

# Watch live PHP errors if error_log is separate
sudo tail -f /var/log/apache2/error.log | grep -i php
```

---

## Implementation Steps + Checkpoints

---

### PHASE 1 — Routing Skeleton

**Steps:**
1. Update `.htaccess` — add second rewrite rule sending all non-api, non-file paths to `index.php`
2. Create `index.php` — starts session, requires Router, dispatches by path
3. Create `app/core/Auth.php` — `Auth::check()` and `Auth::require()`
4. Create `app/core/Router.php` — simple path matcher/dispatcher
5. Create stub controllers — each method just echoes a placeholder string for now

**`.htaccess` after update:**
```apache
RewriteEngine On

# API routes → existing router (unchanged)
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ /api/router.php [QSA,L]

# App routes → front controller
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ /index.php [QSA,L]
```

---

### CHECKPOINT 1 — Routing Works
```bash
sudo apache2ctl configtest   # confirm no config errors
sudo systemctl reload apache2
```

**What to test manually:**
- Visit `https://reporting.angelo-j.xyz/` — should redirect to `/login`
- Visit `https://reporting.angelo-j.xyz/login` — should show placeholder text (not a 404)
- Visit `https://reporting.angelo-j.xyz/dashboard` — should redirect to `/login` (auth guard)
- Visit `https://reporting.angelo-j.xyz/api/static` — should still return JSON (API unaffected)

**Pass criteria:** Routing dispatches correctly, API still works, protected routes redirect unauthenticated users.

---

### PHASE 2 — Login / Logout

**Steps:**
6. Flesh out `AuthController.php`:
   - `showLogin` — render `views/login.php` (a basic HTML form)
   - `handleLogin` — validate POST credentials (hardcoded bcrypt hash), set `$_SESSION['user']`, redirect to `/dashboard`
   - `logout` — `session_destroy()`, redirect to `/login`
7. Create `views/login.php` — HTML form with username + password fields, error message slot

**Credentials:** One hardcoded user defined in `AuthController` — username and bcrypt-hashed password. No signup page.

| Field | Value |
|-------|-------|
| Username | `admin` |
| Password | `cse135report` |
| Hash (bcrypt cost 12) | `$2y$12$Kfw1PSyLF6G1iZfKqr8D4.QvNlTwf3NvmikdbcLjNnCvQcGBSeqla` |
| Defined in | `app/controllers/AuthController.php` |

---

### CHECKPOINT 2 — Auth Works
No server restart needed (PHP file changes take effect immediately).

**What to test manually:**
- Visit `/login` — login form renders
- Submit wrong credentials — form re-renders with an error message
- Submit correct credentials — redirected to `/dashboard` (placeholder text for now)
- While logged in, visit `/logout` — session cleared, redirected to `/login`
- While logged out, type `/dashboard` directly in the address bar — redirected to `/login` (forceful browsing blocked)

**Pass criteria:** Session auth works end-to-end, forceful browsing is blocked.

---

### PHASE 3 — Dashboard Page

**Steps:**
8. Create `app/controllers/DashboardController.php`:
   - Calls `Auth::require()`
   - Queries DB for row counts from all three tables (reuses `api/db.php`)
   - Passes counts to view
9. Create `views/layout/header.php` — `<head>`, Chart.js CDN script tag, `<nav>` with links
10. Create `views/layout/footer.php` — closing tags
11. Create `views/dashboard.php` — summary cards (total rows per table)

---

### CHECKPOINT 3 — Dashboard Loads with Real Data
No server restart needed.

**What to test manually:**
- Log in and visit `/dashboard`
- Summary cards show real counts pulled from the DB (not hardcoded zeros)
- Nav bar links are present and point to the right paths
- Clicking Logout works

**Pass criteria:** Dashboard renders with live DB data, layout/nav present.

---

### PHASE 4 — Data Tables

**Steps:**
12. Create `app/controllers/ReportsController.php` with three methods:
    - `staticData` — fetch all rows from `static_data`
    - `performanceData` — fetch all rows from `performance_data`
    - `activityData` — fetch all rows from `activity_data`
    - Each calls `Auth::require()` and passes rows to its view
13. Create `views/static.php` — HTML `<table>` of `static_data` rows
    - Columns: session_id, url, user_agent, language, screen_width × screen_height, network_type, timezone
14. Create `views/performance.php` — HTML `<table>` of `performance_data` rows
    - Columns: session_id, ttfb, dom_complete, lcp, cls, inp
15. Create `views/activity.php` — HTML `<table>` of `activity_data` rows
    - Columns: session_id, event_type, timestamp, details

---

### CHECKPOINT 4 — Data Tables Render
No server restart needed.

**What to test manually:**
- Visit `/reports/static` — table renders with real rows
- Visit `/reports/performance` — table renders with real rows
- Visit `/reports/activity` — table renders with real rows
- Try visiting any report URL while logged out — redirected to `/login`

**Pass criteria:** All three tables display live data, auth guard still works on report routes.

---

### PHASE 5 — Charts

**Steps:**
16. Add Chart.js charts to views (inline `<canvas>` + `<script>` blocks, data passed from PHP as JSON):
    - `views/dashboard.php` — bar chart: count of activity event types
    - `views/performance.php` — bar chart: TTFB, LCP, DOM Complete averages per session (or top N sessions)
    - `views/activity.php` — bar chart: event type distribution

---

### CHECKPOINT 5 — Charts Render
No server restart needed.

**What to test manually:**
- Visit `/dashboard` — bar chart renders with real event type counts
- Visit `/reports/performance` — chart renders with performance metrics
- Visit `/reports/activity` — chart renders with event type counts
- Inspect browser console for any JS errors

**Pass criteria:** At least two charts render with real data, no console errors.

---

### PHASE 6 — Polish

**Steps:**
17. Create `public/css/style.css` — minimal styles: nav bar, login form centered, table row striping, card layout for dashboard
18. Link stylesheet in `views/layout/header.php`
19. Replace/delete `index.html` (or ensure `.htaccess` never serves it)
20. Final check: all routes, auth, tables, charts working end-to-end

---

### CHECKPOINT 6 — Final End-to-End Check
```bash
sudo apache2ctl configtest
sudo systemctl reload apache2
```

**What to test manually:**
- Full login → dashboard → reports → logout flow
- Forceful browsing attempt on all protected routes
- Verify `/api/static`, `/api/performance`, `/api/activity` still return JSON (API unaffected)
- Check browser console — no errors on any page

**Pass criteria:** All three assignment requirements met, app looks presentable.

---

## Libraries / CDN Dependencies
| Library | Purpose | How |
|---------|---------|-----|
| Chart.js | Charts | CDN `<script>` tag in header |

No npm, no build step — all PHP + vanilla JS + Chart.js CDN.

---

## Authentication Details
- Session-based: `session_start()` in `index.php`, `$_SESSION['user']` set on login
- Hardcoded credential: one username/password (bcrypt hash) defined in `AuthController`
- `Auth::require()` called at the top of every protected controller method → redirects to `/login` if session missing
- Prevents forceful browsing (typing `/dashboard` directly without being logged in)

---

## Adding Comments

Goal: add "what" and "why" comments to every file in `api/` and `app/` — covering modules, classes, and functions. Comments are only added where they provide genuine value (non-obvious logic, architectural intent, or security reasoning).

---

### `api/db.php`
- **File-level:** bootstraps a shared PDO connection used by all API handler files; included via `require_once` so `$pdo` is available in the calling scope.

---

### `api/router.php`
- **File-level:** entry point for all `/api/*` requests; parses the URL to determine the resource and optional ID, then delegates to the correct handler file.

---

### `api/static.php`
- **File-level:** CRUD handler for `static_data`; each case corresponds to an HTTP method on the `/api/static` resource.
- **`handleStatic()`:** routes `GET`, `POST`, `PUT`, `DELETE` to the appropriate SQL against `static_data`. `POST`/`PUT` read the request body via `php://input` because the body is JSON (not form-encoded), so `$_POST` would be empty.

---

### `api/activity.php`
- **File-level:** CRUD handler for `activity_data`; same structure as `static.php` but for user interaction events.
- **`handleActivity()`:** routes `GET`, `POST`, `PUT`, `DELETE` to the appropriate SQL against `activity_data`. `php://input` is used for the same reason as in `static.php`.

---

### `api/performance.php`
- **File-level:** CRUD handler for `performance_data`; same structure as `static.php` but for page-load timing metrics.
- **`handlePerformance()`:** routes `GET`, `POST`, `PUT`, `DELETE` to the appropriate SQL against `performance_data`. `php://input` is used for the same reason as in `static.php`.

---

### `app/core/Auth.php`
- **Class-level:** thin session-based auth helper used by all protected controllers.
- **`check()`:** returns true if a user session exists — used to conditionally show the nav bar in the header view.
- **`require()`:** hard-gates protected routes; redirects to `/login` and exits immediately if no session is found, preventing forceful browsing.

---

### `app/core/Router.php`
- **Class-level:** front-controller router for all web page routes (`/login`, `/dashboard`, `/reports/*`); completely separate from `api/router.php` which handles `/api/*`.
- **`dispatch()`:** strips trailing slashes then normalises an empty path to `/` so both `/` and `/dashboard` hit the same case. Requires all controllers up front rather than lazily, since any route could be matched.

---

### `app/controllers/AuthController.php`
- **Class-level:** handles login/logout for the single hardcoded admin account; credentials are stored as a bcrypt hash, never in plain text.
- **`showLogin()`:** reads and clears any pending login error from the session before rendering — the error is stored in the session (not a query param) so it survives the redirect without being visible in the URL.
- **`handleLogin()`:** uses `password_verify` against the bcrypt hash; calls `session_regenerate_id(true)` on success to prevent session fixation. Uses a generic error message to avoid leaking whether the username or password was wrong.
- **`logout()`:** fully destroys the session rather than just unsetting the user key, ensuring all session data is cleared.

---

### `app/controllers/DashboardController.php`
- **Class-level:** loads aggregated summary data from all three tables and passes it to the dashboard view; directly reuses `api/db.php` for the DB connection rather than duplicating the connection setup.
- **`index()`:** iterates over table names to get counts in a single loop rather than three separate queries — order matches the display order of the cards. Queries are separate per metric type because each aggregation is different (GROUP BY network_type, AVG timings, GROUP BY event type).

---

### `app/controllers/ReportsController.php`
- **Class-level:** fetches full table data for each of the three report pages and hands it to the corresponding view; all three methods follow the same pattern (auth guard → DB fetch → render view).
- **Each method** orders by `id DESC` so the most recent records appear at the top of the table without the view needing to know the sort order.

---

### `app/views/layout/header.php`
- **File-level:** shared HTML head and nav bar included at the top of every view. Accepts `$pageTitle` from the including view; falls back to `'Analytics'` if not set. Nav bar is only rendered when a session exists — so it is hidden on the login page without any extra logic in the views.

---

### `app/views/layout/footer.php`
- **File-level:** closes the `<main>`, `<body>`, and `<html>` tags opened by `header.php`. Kept as a separate file so every view can include both halves of the layout independently.

---

### `app/views/login.php`
- No non-obvious logic; the only notable point is `$error` is passed from `AuthController::showLogin()` after being read from the session, not from a query parameter.

---

### `app/views/dashboard.php`
- **Chart data (inline PHP blocks):** `array_map` with arrow functions extracts a single column from the query result arrays into flat arrays suitable for `json_encode` — Chart.js expects separate `labels` and `data` arrays, not an array of objects.
- **`$perfAvgs` values:** cast to `float` before `round()` because PDO returns numeric columns as strings in `FETCH_ASSOC` mode.

---

### `app/views/static.php`
- **Session ID display:** `substr($row['session_id'], 0, 12)` truncates the full UUID to 12 chars for display; the full value is kept in the `title` attribute so it is visible on hover without crowding the table column.

---

### `app/views/performance.php`
- **Chart data prep:** `array_reverse($rows)` is needed because the controller fetches rows `ORDER BY id DESC` (newest first) for the table — reversing gives chronological order for the chart. `array_slice(..., 0, 10)` then takes only the 10 most recent sessions to keep the chart readable.
- **`$lcpVals`:** coalesces to `0` with `?? 0` because LCP is nullable (only present if the browser reported it); Chart.js needs a numeric value for every data point.

---

### `app/views/activity.php`
- **`$typeCounts` build:** iterates `$rows` client-side (in PHP) rather than issuing a second SQL query, since the rows are already in memory. `arsort` sorts by count descending so the most frequent event type appears first in the chart.

---

Example of controller that calls model.
```
<?php
// PHP controller that adapts its response format
$users = $userModel->getAll();

$accept = $_SERVER['HTTP_ACCEPT'] ?? 'text/html';
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

if ($isAjax || str_contains($accept, 'application/json')) {
    // JavaScript client → return JSON
    header('Content-Type: application/json');
    echo json_encode($users);
} else {
    // Browser → render full HTML page
    require 'views/users/index.php';
}
?>
```

---

## Refactor Plan — Introduce Model Layer

**Goal:** Move all DB queries out of controllers into dedicated model classes. Controllers become thin: call `Auth::require()`, invoke a model method, pass the result to a view.

---

### New Files

```
app/
└── models/
    ├── User.php           ← Already exists (empty stub)
    ├── StaticModel.php    ← Queries against static_data
    ├── PerformanceModel.php ← Queries against performance_data
    ├── ActivityModel.php  ← Queries against activity_data
    └── DashboardModel.php ← Aggregated queries for dashboard summary cards + chart data
```

Each model:
- Accepts a `PDO $pdo` instance in its constructor (injected, not fetched internally)
- Exposes only the query methods the controllers actually need

---

### Model Interfaces

**`StaticModel`**
- `getAll(): array` — `SELECT * FROM static_data ORDER BY id DESC`

**`PerformanceModel`**
- `getAll(): array` — `SELECT * FROM performance_data ORDER BY id DESC`

**`ActivityModel`**
- `getAll(): array` — `SELECT * FROM activity_data ORDER BY id DESC`

**`DashboardModel`**
- `getCounts(): array` — row counts from all three tables (keyed by table name)
- `getNetworkTypes(): array` — `GROUP BY network_type` from `static_data`
- `getPerfAverages(): array` — `AVG(ttfb, dom_complete, lcp, total_load_time)` from `performance_data`
- `getEventTypes(): array` — `GROUP BY type` from `activity_data`

---

### Controller Changes

**`DashboardController::index()`**
```
require_once api/db.php            // get $pdo
$model = new DashboardModel($pdo)
$counts      = $model->getCounts()
$networkTypes = $model->getNetworkTypes()
$perfAvgs    = $model->getPerfAverages()
$eventTypes  = $model->getEventTypes()
require view
```

**`ReportsController::staticData()`**
```
require_once api/db.php
$model = new StaticModel($pdo)
$rows  = $model->getAll()
require view
```
Same pattern for `performanceData()` and `activityData()`.

---

### Implementation Steps

1. Create `app/models/StaticModel.php` — constructor takes `$pdo`, implements `getAll()`
2. Create `app/models/PerformanceModel.php` — same shape
3. Create `app/models/ActivityModel.php` — same shape
4. Create `app/models/DashboardModel.php` — constructor takes `$pdo`, implements the four aggregation methods
5. Update `DashboardController::index()` — replace inline queries with `DashboardModel` calls
6. Update `ReportsController::staticData/performanceData/activityData()` — replace inline queries with model calls
7. Load model files in `Router::dispatch()` via `require_once` (same place controllers are loaded)

---

### What Does NOT Change

- Views receive the same variables (`$rows`, `$counts`, `$networkTypes`, etc.) — no view edits needed
- `api/db.php` is still the single source of `$pdo` — models receive it, they don't create their own connection
- `api/` handler files are untouched

### Testing

No server restart needed — PHP file changes take effect immediately.

**What to verify manually:**

- [ ] Visit `/dashboard` — summary cards show correct row counts (same numbers as before the refactor)
- [ ] Dashboard bar charts render with real data: network types, avg perf timings, event type breakdown
- [ ] Visit `/reports/static` — table loads with real rows, session IDs truncated with hover tooltip
- [ ] Visit `/reports/performance` — table loads with real rows, per-session chart renders for 10 most recent
- [ ] Visit `/reports/activity` — table loads with real rows, event type chart renders
- [ ] Log out and attempt to visit `/dashboard`, `/reports/static`, `/reports/performance`, `/reports/activity` directly — all redirect to `/login`
- [ ] Visit `/api/static`, `/api/performance`, `/api/activity` — still return JSON (API layer untouched)
- [ ] Check browser console on every page — no JS errors

---

## Refactor Plan — DB-backed Auth

**Goal:** Remove the hardcoded username/hash from `AuthController` and look up credentials from a `users` table instead. `UserModel` becomes the single place that touches the `users` table.

---

### Hashing & Salting

PHP's `password_hash($password, PASSWORD_BCRYPT)` generates a **unique random salt per call** and embeds it inside the returned hash string (e.g. `$2y$12$<22-char-salt><31-char-hash>`). There is nothing to manage manually — `password_verify($input, $storedHash)` reads the salt back out of the stored string automatically.

To generate a hash for the initial seed row:
```php
echo password_hash('cse135report', PASSWORD_BCRYPT);
// paste the output into the INSERT below
```

---

### DB Change

New table in the `analytics` database:

```sql
CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(64)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Seed the existing admin account (generate hash first, paste below)
INSERT INTO users (username, password_hash)
VALUES ('admin', '$2y$12$Kfw1PSyLF6G1iZfKqr8D4.QvNlTwf3NvmikdbcLjNnCvQcGBSeqla');
```

---

### `app/models/User.php`

Add one method — `findByUsername(string $username): ?array`
- Prepared statement: `SELECT * FROM users WHERE username = :username LIMIT 1`
- Returns the row array on match, `null` if not found
- Constructor accepts `PDO $pdo` (same injection pattern as other models)

---

### `app/controllers/AuthController.php`

- Remove `$username` and `$hash` static properties
- `handleLogin()`: instantiate `UserModel($pdo)`, call `findByUsername()`, then `password_verify()` against the returned row's `password_hash`
- Requires `api/db.php` to get `$pdo` (same as other controllers)

---

### `app/core/Router.php`

- Add `require_once` for `app/models/User.php` alongside the other model loads

---

### Implementation Steps

1. Run the `CREATE TABLE` + `INSERT` SQL against the `analytics` DB:

```bash
# Enter MySQL as root
mysql -u root -p analytics

# Then run:
CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(64)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, password_hash)
VALUES ('admin', '$2y$12$Kfw1PSyLF6G1iZfKqr8D4.QvNlTwf3NvmikdbcLjNnCvQcGBSeqla');

-- Verify
SELECT id, username, created_at FROM users;
```
2. Flesh out `UserModel::findByUsername()` in `app/models/User.php`
3. Update `AuthController::handleLogin()` to use `UserModel` instead of hardcoded credentials
4. Add `UserModel` require to `Router::dispatch()`

---

### Testing

No server restart needed.

- [ ] Visit `/login` — page still renders
- [ ] Submit correct credentials (`admin` / `cse135report`) — redirected to `/dashboard`
- [ ] Submit wrong username — generic error message shown, not redirected
- [ ] Submit wrong password — same generic error message
- [ ] Confirm session still works: navigate to `/reports/static` while logged in — loads normally
- [ ] Log out — redirected to `/login`, session cleared

### Refactors - Error Handling
I have heard of the mantra of throw low/catch high. I am not seeing it implemented across the controllers where we catch errors at the controller level and throw at the business logic level.
- Implement try/catch functionality. Controllers should follow a try/catch formula.

***After Googling***
- Some people have a global exception handler while some like to implement try/catches throughout the controller.

From reddit:
```
A global exception handler middleware can be used to catch all unhandled exceptions and return a 500 with a generic message. This is usually my first step in exception handling in web api.

I try to avoid having try/catch in controllers, but sometimes it is preferential to creating something larger just for the sake of eliminating a single try/catch.

It also depends on what you expect to catch. Are you throwing an exception due to an ID collision on insert, or due to an invalid model?
```

From another user
```
Ok, here is my take on this: handling exceptions is a battle between generalization and specifity. E.g. you can add a global exception handler and catch any and all excpetions and return a 500. But that totally lacks good error messages etc. E.g. maybe your dB access failed because a user supplied value was invalid. Would be good to let the user know that. To achieve this I usually catch somewhat expected excpetions at the dB access level, and rethrow a custom exception with the necessary details. Then a custom error handler can catch this (and similarly usuall all such exceptions inherit from a common base class) and return an appropriate error response. Additionally a generic error handler catches everything truly unexpected. At the controller level I usually catch only one of a kind error which are only valid for this controller method

This is exactly what I do as well. Initial exception provides the details on what went wrong and why, then the exception handler in the controller handles converting those error details into a meaningful error message for the user.
```
I think the bottom two comments seem to validate my initial approach of having the controllers gracefully handling error messages. 

So what I am seeing is that:
1. Let the models throw the raw error
2. Wrap those raw errors in graceful error messages within the controller and throw a 500 error

***Notes about the code***
- I added null error checking for the User.php model. Apply this where needed elsewhere as well.

Also from the assignment, we should explicitly care about cases that are unexpected, like 403 pages, 404 pages, script off handling, and other contingencies you can think of.  A server-side program is under our control, so exercising that control demonstrates that we are taking advantage of the medium's characteristics.

---

## Refactor Plan — Error Handling

### Current state of failures

| Failure point | Current behavior |
|---|---|
| DB connection failure | `db.php` catches, outputs raw JSON, calls `exit` — browser sees JSON on an HTML page |
| PDO query failure in a model | `PDOException` propagates unhandled — PHP shows a generic/blank error page |
| Unknown route | Bare `echo '<h1>404 — Page Not Found</h1>'` — no layout, no HTTP status code set properly |
| Any uncaught exception | PHP's default handler — ugly or blank output |
| JS disabled | Charts render as blank `<canvas>` — no fallback |

---

### Step 1 — Create error views
New files:
- `app/views/errors/500.php` — "Something went wrong" page (uses layout header/footer)
- `app/views/errors/404.php` — "Page not found" page
- `app/views/errors/403.php` — "Access denied" page

---

### Step 2 — Global exception handler in `index.php`
Last-resort safety net. Any exception that escapes a controller renders the 500 view instead of a PHP crash.

```php
set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    require __DIR__ . '/app/views/errors/500.php';
});
```

Added before `Router::dispatch()`.

---

### Step 3 — Fix `db.php` — remove its internal try/catch
`db.php` currently catches connection failure and exits with JSON. That's wrong in the MVC context — controllers can't catch an `exit`.

**Change**: Remove the try/catch from `db.php`. Let `PDOException` propagate naturally. The global handler (and controller try/catches) will handle it for MVC pages.

This requires one touch to `api/router.php` — add a try/catch there to restore the JSON error behavior for the API side.

---

### Step 4 — Wrap controller methods in try/catch

Each controller method catches exceptions from model calls and renders the 500 view.

```php
// DashboardController::index() — example
Auth::require();
try {
    require_once __DIR__ . '/../../api/db.php';
    $model        = new DashboardModel($pdo);
    $counts       = $model->getCounts();
    $networkTypes = $model->getNetworkTypes();
    $perfAvgs     = $model->getPerfAverages();
    $eventTypes   = $model->getEventTypes();
    require __DIR__ . '/../views/dashboard.php';
} catch (Exception $e) {
    http_response_code(500);
    require __DIR__ . '/../views/errors/500.php';
}
```

Same pattern applied to all three `ReportsController` methods and `AuthController::handleLogin()`.

`Auth::require()` stays outside the try/catch — a redirect on unauthenticated access is not an error.

---

### Step 5 — Null guards on model methods
`User.php` already null-checks before querying. Extend the pattern:

- `StaticModel::getAll()`, `PerformanceModel::getAll()`, `ActivityModel::getAll()` — return `[]` if `fetchAll()` returns false (defensive; won't normally happen with `ERRMODE_EXCEPTION` but guards against empty/unexpected results)
- `DashboardModel::getPerfAverages()` — `->fetch()` returns `false` if `performance_data` is empty; return a zeroed array as fallback so the view doesn't break

---

### Step 6 — Fix Router's 404 case
Replace bare echo with a proper view render and correct HTTP status:

```php
default:
    http_response_code(404);
    require __DIR__ . '/../views/errors/404.php';
```

---

### Step 7 — `<noscript>` fallbacks for charts
The assignment explicitly calls out script-off handling. Add a `<noscript>` block inside each `<canvas>` container in `dashboard.php`, `performance.php`, and `activity.php`:

```html
<noscript>
    <p>Charts require JavaScript to display. Please enable JavaScript in your browser.</p>
</noscript>
```

---

### All files touched

| File | Change |
|---|---|
| `index.php` | Add `set_exception_handler()` |
| `api/db.php` | Remove try/catch — let PDOException propagate |
| `api/router.php` | Add try/catch to restore JSON error behavior for API |
| `app/controllers/DashboardController.php` | Wrap body in try/catch |
| `app/controllers/ReportsController.php` | Wrap each method body in try/catch |
| `app/controllers/AuthController.php` | Wrap `handleLogin()` in try/catch |
| `app/models/StaticModel.php` | Null guard on fetchAll result |
| `app/models/PerformanceModel.php` | Same |
| `app/models/ActivityModel.php` | Same |
| `app/models/DashboardModel.php` | Null guard on `getPerfAverages` fetch result |
| `app/core/Router.php` | Replace bare 404 echo with error view |
| `app/views/errors/500.php` | **New** |
| `app/views/errors/404.php` | **New** |
| `app/views/errors/403.php` | **New** |
| `app/views/dashboard.php` | Add `<noscript>` fallback |
| `app/views/performance.php` | Same |
| `app/views/activity.php` | Same |

### Adding Authentication System
Have a full working authentication system with authorization rules for access.  You must have three levels of users: super admin, analyst, and viewer.  A super admin can do anything, including managing users.  An analyst can do anything and look at anything.  An analyst can be defined and may look at a defined set of sections in the backend.  For example, an analyst "Sam" may be in charge of performance and can only look at performance data and define "reports," while an analyst "Sally" may be able to look at performance and behavioral sections.  A viewer can only look at saved reports, which are just set views, even if they are made static.

***My Initial Thoughts***
Lets think from Controller Down To the Model
- AuthController needs to be responsible for assigning / marking a user with their relevant status.
    - Within `handleLogin`
        - After there found we want to handle setting their status in the UserModel instance?

Or no I have to remember that these only persist for just the request....I think the source of truth would be in the database.

A user should have their status marked in their tuple in the DB.

What I am thinking is:
1. User logs in
2. On loading dashboard, the controller should delegate what view to render right depending on the user role?

That way we do not need to touch any of the model functions.

---

## Refactor Plan — Role-Based Access Control (RBAC)

### Role definitions

| Role | What they can access |
|---|---|
| `super_admin` | All sections + user management (create/edit/delete users, assign roles and sections) |
| `analyst` | Full read access within their assigned sections only (configured per user) |
| `viewer` | Read-only access to saved/published reports only — no live data tables |

---

### Key design decisions

**Role stored in DB, cached in session.**
`users.role` is the source of truth. On login, `handleLogin()` reads the role (and for analysts, their permitted sections) from the DB and writes them into `$_SESSION`. Every subsequent request reads from `$_SESSION` — no extra DB query per page load.

**Analyst section permissions are per-user, not per-role.**
A single `analyst` role column is not enough — two analysts can have different section access. This requires a separate `user_sections` join table.

**Controllers gate access, models are untouched.**
Model query methods stay exactly as they are. The gating logic lives in `Auth` helpers called at the top of each controller method.

**Saved reports are a new DB-backed concept.**
Viewers can only see reports that a super_admin or analyst has explicitly saved/published. This requires a `reports` table.

---

### Phase 1 — DB changes

```sql
-- Add role to users table
ALTER TABLE users
    ADD COLUMN role ENUM('super_admin', 'analyst', 'viewer') NOT NULL DEFAULT 'viewer';

-- Promote the existing admin account
UPDATE users SET role = 'super_admin' WHERE username = 'admin';

-- Per-user section permissions for analysts
-- section values: 'static', 'performance', 'activity'
CREATE TABLE user_sections (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    section    ENUM('static', 'performance', 'activity') NOT NULL,
    UNIQUE KEY (user_id, section),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Saved reports (for viewer access)
CREATE TABLE reports (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(255) NOT NULL,
    section      ENUM('static', 'performance', 'activity') NOT NULL,
    created_by   INT UNSIGNED NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Seed test users
-- sam: analyst, performance only     (password: sam123)
-- sally: analyst, performance + activity (password: sally123)
-- viewer1: viewer                     (password: viewer123)
INSERT INTO users (username, password_hash, role) VALUES
    ('sam',     '$2y$10$ZRb9gtaCrn.5nnYoInX.RuWR2CH.iMvhk7B8CfpJtyxyoCOurvIHu', 'analyst'),
    ('sally',   '$2y$10$9WPZ.jN739R7ddFLMnEeie9Uvr3yxmm2tBlPVdaMhzy3gqHdgxEFS', 'analyst'),
    ('viewer1', '$2y$10$hdVYGyCUzioeofTcB4ZLMufzWCKGoJnffHA.cR8a7Z8Etr7xhYMCy', 'viewer');

-- Assign sam's sections (performance only)
INSERT INTO user_sections (user_id, section)
SELECT id, 'performance' FROM users WHERE username = 'sam';

-- Assign sally's sections (performance + activity)
INSERT INTO user_sections (user_id, section)
SELECT id, 'performance' FROM users WHERE username = 'sally';
INSERT INTO user_sections (user_id, section)
SELECT id, 'activity' FROM users WHERE username = 'sally';
```

**Verify Phase 1:**
```sql
-- Confirm roles are set correctly
SELECT username, role FROM users;

-- Confirm user_sections are correct
SELECT u.username, s.section
FROM user_sections s
JOIN users u ON u.id = s.user_id
ORDER BY u.username;

-- Confirm reports table exists and is empty
SELECT * FROM reports;
```

Expected output:
- `admin` → super_admin, `sam` → analyst, `sally` → analyst, `viewer1` → viewer
- `sam` has 1 row (performance), `sally` has 2 rows (performance, activity)
- `reports` returns 0 rows

---

### Phase 2 — Session changes in `AuthController::handleLogin()`

After a successful `password_verify`, load the role and (for analysts) their permitted sections, then store both in session:

```php
$_SESSION['user']     = $user['username'];
$_SESSION['role']     = $user['role'];
$_SESSION['sections'] = $userModel->getSectionsForUser($user['id']); // [] for non-analysts
```

Role and sections are read from session on every request — no extra DB query.

**Verify Phase 2:**
- Log in as `admin` — session should contain `role = super_admin`, `sections = []`
- Log in as `sam` — session should contain `role = analyst`, `sections = ['performance']`
- Log in as `sally` — session should contain `role = analyst`, `sections = ['performance', 'activity']`
- Log in as `viewer1` — session should contain `role = viewer`, `sections = []`
- To inspect session values, temporarily add `var_dump($_SESSION); exit;` at the top of `DashboardController::index()` and remove after verifying
- *(Full login flow covered in Testing section → Login & Auth)*

---

### Phase 3 — Extend `Auth` helpers

Add two new static methods to `app/core/Auth.php`:

**`Auth::requireRole(string ...$roles): void`**
Redirects to `/login` (unauthenticated) or renders `403` (authenticated but wrong role) if the session role is not in the allowed list.

**`Auth::canAccessSection(string $section): bool`**
Returns true if the current user is a super_admin, or if they are an analyst with `$section` in their `$_SESSION['sections']` array. Used by controllers to gate report pages.

**Verify Phase 3:**
- Log in as `sam`, visit `/reports/static` — should hit the 403 branch (even before Phase 4 controller changes, you can test `Auth::canAccessSection()` returns false by temporarily dumping its return value)
- Log in as `admin`, confirm `Auth::canAccessSection('static')` returns true
- Log in as `viewer1`, confirm `Auth::requireRole('super_admin', 'analyst')` triggers 403

---

### Phase 4 — Controller changes

**`DashboardController::index()`**
- Viewer: redirect to `/reports/saved` immediately
- Super admin: renders full dashboard — all cards and charts
- Analyst: renders filtered dashboard — only the cards and charts for their permitted sections

The controller passes `$_SESSION['sections']` (or `['static','performance','activity']` for super_admin) to the view as `$allowedSections`. The dashboard view wraps each card/chart block in a check against `$allowedSections` before rendering it.

**`ReportsController` — each method**
```php
Auth::require();
if (!Auth::canAccessSection('performance')) {  // example
    http_response_code(403);
    require .../errors/403.php;
    return;
}
```

**New `UserController`** — super_admin only
- `index()` — list all users
- `create()` / `store()` — new user form + POST handler
- `edit()` / `update()` — edit role and section assignments
- `destroy()` — delete user

**`UserController::update()` — role change rules**
When saving a role change, if the new role is not `analyst`, delete all `user_sections` rows for that user before saving. This covers both demotion (analyst → viewer/super_admin) and any future re-promotion edge cases.

If a user is later re-promoted back to `analyst`, they intentionally start with no section permissions. An admin must explicitly re-assign their sections. This is correct behavior — least-privilege: default to no access, grant explicitly. Do not restore old permissions automatically.

**Session staleness on role change**
If the affected user is currently logged in, their `$_SESSION['role']` and `$_SESSION['sections']` reflect the old state until they log out and back in. This is a known limitation of session-based auth. Accepted trade-off — the change takes effect on next login.

**New `ReportController`** (separate from `ReportsController`)
- `index()` — list saved reports (viewer, analyst, super_admin)
- `store()` — save a report (analyst within their sections, super_admin)

**Verify Phase 4:**
- Log in as `sam`, visit `/dashboard` — only performance card and chart visible, static and activity absent
- Log in as `sally`, visit `/dashboard` — performance and activity visible, static absent
- Log in as `admin`, visit `/dashboard` — all three cards and charts visible
- Log in as `viewer1`, visit `/dashboard` — redirected to `/reports/saved`
- Log in as `sam`, visit `/reports/performance` — loads normally
- Log in as `sam`, visit `/reports/static` — 403 page
- Log in as `viewer1`, visit `/reports/performance` — 403 page
- *(Covered in full by Testing section → Super Admin, Analyst sam, Analyst sally, Viewer)*

---

### Phase 5 — New views

- `app/views/users/index.php` — user list table (super_admin only)
- `app/views/users/form.php` — create/edit user form with role dropdown and section checkboxes
- `app/views/reports/saved.php` — list of saved reports (viewer landing page)

**Verify Phase 5:**
- Log in as `admin`, visit `/users` — user list renders with all four test accounts
- Visit `/users/create` — form renders with role dropdown and section checkboxes
- Section checkboxes should only be visible/enabled when role is set to analyst
- Visit `/reports/saved` as `viewer1` — page renders (empty list is fine at this stage)

---

### Phase 6 — Router additions

```php
case '/users':               UserController::index();   break;
case '/users/create':        UserController::create();  break; // GET
case '/users/store':         UserController::store();   break; // POST
case '/users/edit':          UserController::edit();    break; // GET ?id=
case '/users/update':        UserController::update();  break; // POST
case '/users/delete':        UserController::destroy(); break; // POST
case '/reports/saved':       ReportController::index(); break;
case '/reports/saved/store': ReportController::store(); break; // POST
```

**Verify Phase 6:**
- Visit `/users` — routes correctly to `UserController::index()`
- Visit `/users/create` (GET) — routes to `UserController::create()`
- Visit `/reports/saved` — routes to `ReportController::index()`
- Visit `/gibberish` — still renders 404 page
- Visit `/api/static` — still returns JSON (API unaffected)
- *(Covered in full by Testing section → Forceful Browsing and API Unaffected)*

---

### New files summary

| File | Purpose |
|---|---|
| `app/models/UserModel.php` | Add `getSectionsForUser(int $id): array` |
| `app/models/ReportModel.php` | `getAll()`, `save()` against `reports` table |
| `app/controllers/UserController.php` | CRUD for user management (super_admin only) |
| `app/controllers/ReportController.php` | Saved report list + save action |
| `app/views/users/index.php` | User list |
| `app/views/users/form.php` | Create/edit user form |
| `app/views/reports/saved.php` | Saved reports list (viewer landing page) |

### What does NOT change

- Model query methods (`getAll`, `getCounts`, etc.) — untouched
- Views for live data tables and charts — untouched
- `api/` handler files — untouched

### Testing

**Test users to create via user management:**
| Username | Role | Sections |
|---|---|---|
| `admin` | super_admin | (all, implicit) |
| `sam` | analyst | performance only |
| `sally` | analyst | performance + activity |
| `viewer1` | viewer | (none) |

---

#### Login & Auth

- [ ] Log in as `admin` with correct credentials — redirected to `/dashboard`
- [ ] Log in as `sam` — redirected to `/dashboard`
- [ ] Log in as `sally` — redirected to `/dashboard`
- [ ] Log in as `viewer1` — redirected to `/reports/saved` (not dashboard)
- [ ] Submit wrong password for any user — generic error message shown, not redirected
- [ ] Submit a username that doesn't exist — same generic error message
- [ ] Visit `/dashboard` while logged out — redirected to `/login`

---

#### Super Admin (`admin`)

- [ ] Dashboard shows all three summary cards and all charts
- [ ] Can visit `/reports/static` — table loads
- [ ] Can visit `/reports/performance` — table loads
- [ ] Can visit `/reports/activity` — table loads
- [ ] Can visit `/users` — user list renders with all accounts
- [ ] Can create a new analyst user with sections assigned
- [ ] Can create a new viewer user
- [ ] Can edit a user's role and section assignments
- [ ] Can delete a user — confirm they no longer appear in the user list
- [ ] Can save a report from any section

---

#### Analyst — `sam` (performance only)

- [ ] Dashboard loads — shows only the performance summary card and performance avg timing chart; static and activity cards/charts are not rendered
- [ ] Can visit `/reports/performance` — table and chart load
- [ ] Visit `/reports/static` — renders 403 page
- [ ] Visit `/reports/activity` — renders 403 page
- [ ] Visit `/users` — renders 403 page
- [ ] Can save a report from `/reports/performance`
- [ ] Cannot save a report from a section they don't have access to

**Saved reports flow — analyst (`sam`):**
- [ ] Visit `/reports/performance` — "Generate Report" button is visible
- [ ] Click "Generate Report" — form loads at `/reports/performance/generate`
- [ ] Fill in Title (e.g. "Sam Perf Jan"), Commentary (e.g. "Baseline run"), From ID `1`, To ID `5`, Chart type `bar` — click "Save Report"
- [ ] Redirected to `/reports/saved` — new report appears in the table with correct title, section "performance", created by "sam"
- [ ] Click "View" — report view page loads at `/reports/saved/view?id=X`
  - [ ] Commentary block appears with "Baseline run"
  - [ ] Data table shows only the rows whose IDs fall within the range you specified
  - [ ] Chart renders below the table (bar chart, TTFB or first numeric column as dataset)
- [ ] Visit `/reports/static/generate` directly — renders 403 (sam has no static access)
- [ ] Visit `/reports/activity/generate` directly — renders 403

---

#### Analyst — `sally` (performance + activity)

- [ ] Dashboard loads — shows performance and activity cards/charts; static card/chart is not rendered
- [ ] Can visit `/reports/performance` — loads
- [ ] Can visit `/reports/activity` — loads
- [ ] Visit `/reports/static` — renders 403 page
- [ ] Visit `/users` — renders 403 page

**Saved reports flow — analyst (`sally`):**
- [ ] Visit `/reports/activity` — "Generate Report" button is visible
- [ ] Click "Generate Report" — form loads at `/reports/activity/generate`
- [ ] Fill in Title (e.g. "Sally Activity Snapshot"), no commentary, From ID `1`, To ID `10`, Chart type `line` — click "Save Report"
- [ ] Redirected to `/reports/saved` — new report appears (section "activity", created by "sally")
- [ ] Click "View" — report view page loads; line chart renders
- [ ] Visit `/reports/static/generate` directly — renders 403

---

#### Viewer — `viewer1`

- [ ] Logging in redirects to `/reports/saved`, not `/dashboard`
- [ ] Visit `/dashboard` directly — redirected to `/reports/saved`
- [ ] Visit `/reports/static` — renders 403 page
- [ ] Visit `/reports/performance` — renders 403 page
- [ ] Visit `/reports/activity` — renders 403 page
- [ ] Visit `/users` — renders 403 page
- [ ] `/reports/saved` loads and shows all saved reports (those created by sam and sally above are listed)

**Saved reports flow — viewer (`viewer1`):**
- [ ] `/reports/saved` — both sam's and sally's reports appear in the table
- [ ] Click "View" on sam's report — `/reports/saved/view?id=X` loads correctly (frozen data, chart, commentary)
- [ ] Click "View" on sally's report — loads correctly
- [ ] Visit `/reports/performance/generate` directly — renders 403 (viewers cannot generate reports)
- [ ] Visit `/reports/activity/generate` directly — renders 403
- [ ] Visit `/reports/static/generate` directly — renders 403
- [ ] No "Generate Report" button is visible on any reports page (viewer sees 403 before reaching those pages anyway)

---

#### Role change scenarios (logged in as `admin`)

- [ ] Change `sam` from analyst → viewer
  - [ ] Confirm `user_sections` rows for `sam` are gone: `SELECT * FROM user_sections WHERE user_id = <sam's id>;`
  - [ ] Log in as `sam` — redirected to `/reports/saved`, cannot access `/reports/performance`
- [ ] Change `sam` back from viewer → analyst
  - [ ] Log in as `sam` — analyst role, but no sections assigned yet (access to nothing)
  - [ ] Visit `/reports/performance` as `sam` — 403 (sections must be re-assigned explicitly)
- [ ] Re-assign `sam`'s sections via user edit — confirm access restored

---

#### Session staleness

- [ ] Log in as `sam` (analyst, performance only) in one browser tab
- [ ] In another tab as `admin`, demote `sam` to viewer
- [ ] Without logging out, refresh `sam`'s tab — old session still grants analyst access (known limitation — takes effect on next login)
- [ ] Log `sam` out and back in — now correctly redirected to `/reports/saved` as viewer

---

#### Cascade delete

- [ ] As `admin`, delete `sally`
- [ ] Confirm in DB: `SELECT * FROM user_sections WHERE user_id = <sally's id>;` — returns no rows
- [ ] Confirm `sally` cannot log in (user no longer exists)

---

#### Forceful browsing (all routes, logged out)

- [ ] `/dashboard` → redirect to `/login`
- [ ] `/reports/static` → redirect to `/login`
- [ ] `/reports/performance` → redirect to `/login`
- [ ] `/reports/activity` → redirect to `/login`
- [ ] `/users` → redirect to `/login`
- [ ] `/reports/saved` → redirect to `/login`
- [ ] `/gibberish` → 404 page

---

#### API unaffected

- [ ] `/api/static` — still returns JSON
- [ ] `/api/performance` — still returns JSON
- [ ] `/api/activity` — still returns JSON

---

## Known Bugs & Fixes

### Browser autofill overwrites password on user edit

**Symptom:** Editing a user (e.g. changing role or sections) without touching the password field still updates their password hash, causing them to be unable to log in.

**Root cause:** Browsers silently autofill `<input type="password">` fields. The original edit form had a plain password field with a server-side `!empty($password)` check — the autofilled value passed the check and got hashed and saved.

**Fix (attempt 1):** Replaced the always-visible password field in edit mode with an opt-in flow:
- A "Change password" checkbox is unchecked by default
- The password field is hidden (`display:none`) until the checkbox is checked
- The controller only hashes and saves the password when `change_password` is explicitly present in the POST data

**Why attempt 1 was insufficient:** Some browsers autofill `display:none` fields anyway since the input is still present in the DOM. The hidden field was still being submitted with an autofilled value, overwriting the hash again.

**Fix (attempt 2 — final):** Set the password input as `disabled` by default. Disabled fields are never included in POST data regardless of browser autofill — `$_POST['password']` simply won't exist unless the checkbox is checked and the field is explicitly enabled via JS. This is a hard browser guarantee, not a CSS trick.

**Files changed:** `app/views/users/form.php`, `app/controllers/UserController.php`

[Sun Mar 15 21:26:04.924204 2026] [php:notice] [pid 311246] [client 68.7.214.120:41041] DEBUG handleLogin(): Array\n(\n    [username] => sam\n    [input_password] => sam123456\n    [stored_hash] => $2y$10$tvlbfG6P9cbiMwBeWRWXOyLqLXj6QufunMT0nFkvpzOrq9Su36Uy\n    [verify_result] => FAIL\n)\n, referer: https://reporting.angelo-j.xyz/login


php -r "echo password_hash('sam123', PASSWORD_BCRYPT);"
$2y$10$vyWQAQYR1xbyNaJcID5ZNI1TME3IpJeTl9EleVr0BQ5cuyvTtjr6

---

### Debugging — Create fresh test user to isolate login flow

Goal: create a brand new user with a known password via MySQL directly (no edit form involved), then attempt login. If this fails, the bug is in the login flow not the edit form.

**Step 1 — generate a fresh hash:**
```bash
php -r "echo password_hash('sam123', PASSWORD_BCRYPT);"
```

**Step 2 — insert new user directly in MySQL:**
```sql
INSERT INTO users (username, password_hash, role)
VALUES ('sam_test', '<paste hash from step 1>', 'analyst');

-- Verify it inserted
SELECT id, username, password_hash, role FROM users WHERE username = 'sam_test';
```

**Step 3 — attempt login at `/login` with:**
- Username: `sam_test`
- Password: `sam123`

**Expected:** redirected to dashboard (or saved reports since analyst with no sections)
**If fails:** bug is confirmed in the login flow, not the edit form

**Result:** `sam_test` login succeeded. Login flow is confirmed working correctly. The bug is isolated to `sam`'s stored hash being corrupted by browser autofill before the `disabled` fix was in place.

---

### `$password` variable collision with `api/db.php`

**Symptom:** Editing a user's password via the form saves a hash that never verifies — the user cannot log in with the password they set.

**Root cause:** `api/db.php` defines `$password = "CSE135ajseb@"` (the DB connection password). When `require_once api/db.php` is called inside `UserController::store()` and `UserController::update()`, it overwrites the local `$password` variable that was captured from `$_POST['password']`. Any subsequent call to `password_hash($password, ...)` then hashes the DB connection password instead of the user's input.

The debug log masked this — it ran before `require_once db.php` and correctly logged the POST value, making it appear the right password was being processed. The actual `password_hash()` call came after the overwrite.

**Why `AuthController` didn't have this bug:** It uses `$inputPassword` instead of `$password` for the POST value, which was explicitly chosen to avoid this collision. The same pattern wasn't applied to `UserController`.

**Fix:** Renamed `$password` to `$newPassword` in both `UserController::store()` and `UserController::update()` so it no longer collides with the variable set by `api/db.php`.

**Files changed:** `app/controllers/UserController.php`

---

## Feature Plan — Saved Reports (Full Implementation)

### What a saved report is
- Tied to a section (static / performance / activity)
- Has analyst-written commentary (single text block)
- Contains a filtered subset of records selected by ID range at creation time
- Snapshot — data is frozen as JSON at save time, never re-queried
- Has an analyst-chosen chart type (bar, line, pie, doughnut)
- Viewable by all roles at `/reports/saved/{id}`

---

### Phase 1 — DB changes

```sql
ALTER TABLE reports
    ADD COLUMN commentary    TEXT,
    ADD COLUMN chart_type    VARCHAR(20) NOT NULL DEFAULT 'bar',
    ADD COLUMN snapshot_data JSON NOT NULL;
```

---

### Phase 2 — `ReportModel` updates

- `save(string $title, string $section, string $commentary, string $chartType, array $snapshotData, int $createdBy): void`
  — encodes `$snapshotData` as JSON before inserting
- `getAll(): array` — already exists
- `findById(int $id): ?array` — new, decodes `snapshot_data` JSON back to array on retrieval

---

### Phase 3 — Generation form

New view `app/views/reports/generate.php`:
- Title field
- Commentary textarea
- ID range filter: "From ID" / "To ID" inputs
- Chart type selector: bar / line / pie / doughnut
- Submit → `POST /reports/saved/store`

The form posts `section` as a hidden field (set from the page the analyst came from).

---

### Phase 4 — `ReportController` updates

**`store()` — fleshed out:**
1. Validate title, section, chart_type, id_from, id_to
2. Fetch filtered records from the correct model (`StaticModel`, `PerformanceModel`, `ActivityModel`) using an ID range query
3. Snapshot those records as JSON
4. Save via `ReportModel::save()`
5. Redirect to `/reports/saved`

**New `view(int $id)` method:**
- Fetch report by ID via `ReportModel::findById()`
- Decode snapshot JSON back to array
- Pass to view for rendering

---

### Phase 5 — Report view

New view `app/views/reports/view.php`:
- Displays title, section, commentary, created by, created at
- Renders frozen records as an HTML table
- Renders a Chart.js chart using the snapshot data and saved chart type

---

### Phase 6 — Router additions

```php
case '/reports/static/generate':      ReportController::generate('static');      break;
case '/reports/performance/generate': ReportController::generate('performance'); break;
case '/reports/activity/generate':    ReportController::generate('activity');    break;
case '/reports/saved/view':           ReportController::view();                  break;
// POST /reports/saved/store already exists
```

---

### Files changed

| File | Change |
|---|---|
| DB `reports` table | Add `commentary`, `chart_type`, `snapshot_data` columns |
| `app/models/ReportModel.php` | Update `save()`, add `findById()`, add ID-range fetch helpers |
| `app/controllers/ReportController.php` | Flesh out `store()`, add `generate()`, add `view()` |
| `app/views/reports/generate.php` | New — report generation form |
| `app/views/reports/view.php` | New — frozen report viewer |
| `app/views/static.php` | Add "Generate Report" button |
| `app/views/performance.php` | Same |
| `app/views/activity.php` | Same |
| `app/core/Router.php` | Add generate + view routes |


Analyst (sam — performance only)                                   
  1. Generate a report from /reports/performance → verify snapshot,  
  commentary, and chart on the view page                             
  2. Hit /reports/static/generate and /reports/activity/generate
  directly → both should 403                                         
                                                                     
  Analyst (sally — performance + activity)
  1. Generate a report from /reports/activity with a line chart →    
  verify it appears in saved list and renders correctly              
  2. Hit /reports/static/generate directly → should 403              
                                                                     
  Viewer (viewer1)                                                   
  1. Log in → lands on /reports/saved (not dashboard)                
  2. Both sam's and sally's reports appear in the list → click View  
  on each → frozen data and chart load correctly                     
  3. Hit any /reports/*/generate URL directly → all 403              
  4. Confirm no "Generate Report" button is ever visible (reaches 403
   before the page even renders)  

#### Additional Comments To Note
- UI is terrible
- Chart is not working

---

## Dynamic Navbar Plan

### Goal

The nav bar currently shows all links to every logged-in user. It should reflect what the user can actually access based on their role and (for analysts) their assigned sections.

### Nav items per role

| Link | Super Admin | Analyst | Viewer |
|---|---|---|---|
| Dashboard | ✓ | ✓ | ✗ |
| Static | ✓ | only if section assigned | ✗ |
| Performance | ✓ | only if section assigned | ✗ |
| Activity | ✓ | only if section assigned | ✗ |
| Saved Reports | ✓ | ✓ | ✓ |
| Users | ✓ | ✗ | ✗ |
| Logout | ✓ | ✓ | ✓ |

### Implementation

**Single file change: `app/views/layout/header.php`**

All the data needed is already in `$_SESSION` — set at login and available on every request:
- `$_SESSION['role']` — `super_admin`, `analyst`, or `viewer`
- `$_SESSION['sections']` — array of section strings for analysts (e.g. `['performance', 'activity']`)

No controller changes needed. No new session data needed.

Logic in header:
1. Always show: **Saved Reports**, **Logout**
2. If `role === super_admin`: show Dashboard, Static, Performance, Activity, Users
3. If `role === analyst`: show Dashboard; show each section link only if that section is in `$_SESSION['sections']`
4. If `role === viewer`: show nothing extra — Saved Reports + Logout only

Active link styling: compare current `$_SERVER['REQUEST_URI']` against each href and add an `active` CSS class when they match.

### Files changed

| File | Change |
|---|---|
| `app/views/layout/header.php` | Replace static nav links with role-conditional PHP blocks |

---

### Manual Testing — Dynamic Navbar

#### Super Admin (`admin`)
- [ ] Nav shows: Dashboard, Static, Performance, Activity, Users, Saved Reports, Logout
- [ ] No links are missing; no extra links

#### Analyst (`sam` — performance only)
- [ ] Nav shows: Dashboard, Performance, Saved Reports, Logout
- [ ] Static link is absent
- [ ] Activity link is absent
- [ ] Users link is absent

#### Analyst (`sally` — performance + activity)
- [ ] Nav shows: Dashboard, Performance, Activity, Saved Reports, Logout
- [ ] Static link is absent
- [ ] Users link is absent

#### Viewer (`viewer1`)
- [ ] Nav shows: Saved Reports, Logout only
- [ ] Dashboard link is absent
- [ ] All section links (Static, Performance, Activity) are absent
- [ ] Users link is absent

#### Active link highlighting
- [ ] While on `/reports/performance`, the Performance nav link has the `active` class
- [ ] While on `/dashboard`, the Dashboard nav link has the `active` class
- [ ] While on `/reports/saved`, the Saved Reports nav link has the `active` class

#### Edge cases
- [ ] Analyst with no sections assigned — nav shows only Dashboard, Saved Reports, Logout (no section links)
- [ ] Login page — nav bar is not rendered at all (existing behaviour, unchanged)


### User Experience Improvements
- Column sorting on tables (click header to sort)
- Search/filter bar on the saved reports list

---

## Pagination Plan

### Goal
Limit all data tables to 25 rows per page with prev/next navigation. Applies to: Static, Performance, Activity, Saved Reports, Users.

### Approach
Server-side pagination via `?page=N` query parameter. DB does the work with `LIMIT 25 OFFSET N` — no JS required, no extra memory loading full tables.

### Files changed

| File | Change |
|---|---|
| `app/models/StaticModel.php` | Add `getPage(int $page, int $perPage): array` and `countAll(): int` |
| `app/models/PerformanceModel.php` | Same |
| `app/models/ActivityModel.php` | Same |
| `app/models/ReportModel.php` | Same |
| `app/models/User.php` | Add `getPageWithSections(int $page, int $perPage): array` and `countAll(): int` |
| `app/controllers/ReportsController.php` | Read `?page`, compute offset, pass `$page`/`$totalPages` to view |
| `app/controllers/ReportController.php` | Same for `index()` |
| `app/controllers/UserController.php` | Same for `index()` |
| `app/views/layout/pagination.php` | New shared partial — prev/next arrows + "Page X of Y" |
| `app/views/static.php` | Include pagination partial |
| `app/views/performance.php` | Same |
| `app/views/activity.php` | Same |
| `app/views/reports/saved.php` | Same |
| `app/views/users/index.php` | Same |

### Pagination partial contract
The partial expects three variables set by the controller/view:
- `$page` — current page number (1-based)
- `$totalPages` — total number of pages
- `$baseUrl` — URL without query string (e.g. `/reports/static`)

Renders: `← Previous  |  Page 2 of 8  |  Next →`
Prev is disabled on page 1, Next is disabled on last page.

### Manual testing

- [ ] Static, Performance, Activity pages load page 1 (25 rows max)
- [ ] "Next →" advances to page 2; "← Previous" goes back
- [ ] "← Previous" is disabled/absent on page 1
- [ ] "Next →" is disabled/absent on the last page
- [ ] `?page=9999` clamps to last valid page, does not error
- [ ] `?page=0` and `?page=-1` clamp to page 1
- [ ] Users page paginates correctly
- [ ] Saved Reports page paginates correctly
- [ ] Record count shows "Showing X–Y of Z"

---

## Bug: Chart missing when total records < 25

### Symptom
On the Performance and Activity pages, the chart does not render when there are fewer than 25 total records in the table.

### Root cause
`pagination.php` returns early (`return`) when `$totalPages <= 1`. The partial is now included *before* the `<div class="table-wrap">`, so when it returns early that is fine for layout. However the real issue is with `$totalPages` itself — when `$total < 25`, `$totalPages` equals 1. The partial silently exits which is correct.

The actual problem is that the chart section in `performance.php` and `activity.php` relies on `$chartRows` / `$typeCounts` being populated by the controller. These ARE passed by the controller regardless of record count so that is not the issue.

**Actual root cause (to confirm on inspection):** The `pagination.php` partial calls `return` which exits the *included file*, not the parent. However, if there are 0 records, `$totalPages` is set to `max(1, ceil(0/25)) = 1`, so pagination correctly hides. The chart should still render.

**Most likely cause:** When `$total = 0`, `getRecentForChart()` / `getTypeCounts()` return empty arrays. Chart.js receives empty `labels` and `data` arrays and renders nothing (blank canvas). This is not a PHP error — just an empty chart with no visible output.

### Fix
Wrap the chart block in each view with a guard: only render the `<canvas>` and `<script>` if there is data to plot. Show a friendly "No data available" message otherwise.

### Files to change

| File | Change |
|---|---|
| `app/views/performance.php` | Wrap chart block in `if (!empty($chartRows))` |
| `app/views/activity.php` | Wrap chart block in `if (!empty($typeCounts))` |

### Manual testing
- [ ] Performance page with 0 records — chart section shows "No data available", no blank canvas
- [ ] Activity page with 0 records — same
- [ ] Performance page with 1–24 records — chart renders correctly, pagination hidden
- [ ] Activity page with 1–24 records — chart renders correctly, pagination hidden
- [ ] Both pages with 25+ records — chart and pagination both render as normal

---

---

## Side-by-side Table + Chart Layout (Report Pages)

### Goal
On the Static, Performance, and Activity pages, place the data table and chart next to each other horizontally instead of stacked vertically.

### Layout
```
[ pagination controls                     ]
[ table (scrollable, ~60% width) ][ chart (~40% width) ]
```

The table is wider because it has many columns and needs horizontal scroll room. The chart sits to the right. On narrow viewports (< ~900px) they stack back to vertical — table on top, chart below.

### Approach
- Wrap the `<div class="table-wrap">` and the chart `<div class="chart-wrap">` in a new `<div class="data-layout">` in each of the three views
- Add `.data-layout` to `style.css`: `display: flex; gap: 1.5rem; align-items: flex-start`
- `.data-layout .table-wrap`: `flex: 1 1 60%; min-width: 0` — `min-width: 0` is required to allow flex children with `overflow-x: auto` to shrink correctly
- `.data-layout .chart-wrap`: `flex: 0 0 380px` — fixed width so the chart doesn't stretch too wide
- Media query `@media (max-width: 900px)`: `.data-layout { flex-direction: column }` — stacks on mobile

### Files changed

| File | Change |
|---|---|
| `app/views/static.php` | Wrap table + chart in `<div class="data-layout">` |
| `app/views/performance.php` | Same |
| `app/views/activity.php` | Same |
| `public/css/style.css` | Add `.data-layout` flex rules and mobile breakpoint |

### Notes
- The "No data available yet." fallback still renders inside the wrapper — it will sit to the right of an empty table gracefully
- The `h2` chart heading moves inside the wrapper so it stays above its chart column
- Pagination stays above the wrapper (already positioned there)

### Manual testing
- [ ] Static page — table and chart render side by side on desktop
- [ ] Performance page — same
- [ ] Activity page — same
- [ ] Resize browser below 900px — layout stacks vertically
- [ ] Table horizontal scroll still works when table content overflows
- [ ] "No data available" fallback renders cleanly when chart data is empty

---

### Auth Security
- Login attempt rate limiting — lock account after N failed
  attempts
- Password strength enforcement on user creation/edit


/plugin marketplace add anthropics/claude-code                     
/plugin install frontend-design@claude-code-plugins