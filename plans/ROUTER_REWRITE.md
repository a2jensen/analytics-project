# RESTful Router Rewrite Plan

## Goals

- Replace action-verb URLs with noun + HTTP method
- Move resource IDs from query params / POST body into the URL path
- Enforce HTTP method semantics on every route
- Rewrite `Router::dispatch()` to match on `[method, path]` and extract dynamic segments

---

## The Problem (Current State)

The router ignores HTTP methods almost entirely and bakes actions into the URL:

```
GET  /users/create      → show form
POST /users/store       → create user
GET  /users/edit?id=5   → show edit form
POST /users/update      → update user  (id in POST body)
POST /users/delete      → delete user  (id in POST body)
GET  /replay/show?session_id=abc → show replay
POST /reports/saved/store → save report
GET  /reports/saved/view?id=3 → view report
```

This is RPC-over-HTTP, not REST.

---

## HTML Form Limitation

Browsers only send `GET` and `POST` from `<form>`. To support `PUT` and `DELETE` without JavaScript, the router will read a `_method` hidden field when the real method is `POST`:

```html
<form method="POST" action="/users/5">
    <input type="hidden" name="_method" value="DELETE">
    ...
</form>
```

The router normalizes this before matching routes.

---

## New Route Table

### Auth

| Method | Path      | Controller Method          | Was                  |
|--------|-----------|----------------------------|----------------------|
| GET    | /login    | AuthController::showLogin  | GET /login           |
| POST   | /login    | AuthController::handleLogin| POST /login          |
| GET    | /signup   | AuthController::showSignup | GET /signup          |
| POST   | /signup   | AuthController::handleSignup | POST /signup       |
| POST   | /logout   | AuthController::logout     | GET /logout          |

> `/logout` changes from GET to POST — a GET should never have side effects.

### Dashboard

| Method | Path       | Controller Method         | Was        |
|--------|------------|---------------------------|------------|
| GET    | /          | DashboardController::index | GET /      |
| GET    | /dashboard | DashboardController::index | GET /dashboard |

### Reports (data tables)

| Method | Path                  | Controller Method              | Was                      |
|--------|-----------------------|--------------------------------|--------------------------|
| GET    | /reports/static       | ReportsController::staticData  | same                     |
| GET    | /reports/performance  | ReportsController::performanceData | same                 |
| GET    | /reports/activity     | ReportsController::activityData | same                    |

### Saved Reports

| Method | Path                        | Controller Method       | Was                          |
|--------|-----------------------------|-------------------------|------------------------------|
| GET    | /reports/saved              | ReportController::index | GET /reports/saved           |
| POST   | /reports/saved              | ReportController::store | POST /reports/saved/store    |
| GET    | /reports/saved/{id}         | ReportController::view  | GET /reports/saved/view?id=N |

### Report Generation Forms

| Method | Path                           | Controller Method              | Was                              |
|--------|--------------------------------|--------------------------------|----------------------------------|
| GET    | /reports/static/new            | ReportController::generate('static')      | GET /reports/static/generate     |
| GET    | /reports/performance/new       | ReportController::generate('performance') | GET /reports/performance/generate|
| GET    | /reports/activity/new          | ReportController::generate('activity')    | GET /reports/activity/generate   |

> "generate" is a verb. `/new` is the REST convention for a form that creates a resource.

### Session Replay

| Method | Path                | Controller Method             | Was                              |
|--------|---------------------|-------------------------------|----------------------------------|
| GET    | /replay             | SessionReplayController::index | GET /replay                     |
| GET    | /replay/{session_id}| SessionReplayController::show  | GET /replay/show?session_id=STR |

### Users

| Method | Path              | Controller Method       | Was                         |
|--------|-------------------|-------------------------|-----------------------------|
| GET    | /users            | UserController::index   | GET /users                  |
| GET    | /users/new        | UserController::create  | GET /users/create           |
| POST   | /users            | UserController::store   | POST /users/store           |
| GET    | /users/{id}/edit  | UserController::edit    | GET /users/edit?id=N        |
| PUT    | /users/{id}       | UserController::update  | POST /users/update (id in body) |
| DELETE | /users/{id}       | UserController::destroy | POST /users/delete (id in body) |

---

## Router Rewrite

`app/core/Router.php` needs three new capabilities:

### 1. Method override

```php
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_POST['_method'])) {
    $override = strtoupper($_POST['_method']);
    if (in_array($override, ['PUT', 'PATCH', 'DELETE'])) {
        $method = $override;
    }
}
```

### 2. Route table with dynamic segments

Replace the `switch` with a route table — an array of `[method, pattern, handler]` entries. Patterns use named placeholders like `{id}` or `{session_id}`.

```php
private static array $routes = [
    ['GET',    '/',                           [DashboardController::class, 'index']],
    ['GET',    '/dashboard',                  [DashboardController::class, 'index']],

    ['GET',    '/login',                      [AuthController::class, 'showLogin']],
    ['POST',   '/login',                      [AuthController::class, 'handleLogin']],
    ['GET',    '/signup',                     [AuthController::class, 'showSignup']],
    ['POST',   '/signup',                     [AuthController::class, 'handleSignup']],
    ['POST',   '/logout',                     [AuthController::class, 'logout']],

    ['GET',    '/reports/static',             [ReportsController::class, 'staticData']],
    ['GET',    '/reports/performance',        [ReportsController::class, 'performanceData']],
    ['GET',    '/reports/activity',           [ReportsController::class, 'activityData']],

    ['GET',    '/reports/static/new',         fn() => ReportController::generate('static')],
    ['GET',    '/reports/performance/new',    fn() => ReportController::generate('performance')],
    ['GET',    '/reports/activity/new',       fn() => ReportController::generate('activity')],

    ['GET',    '/reports/saved',              [ReportController::class, 'index']],
    ['POST',   '/reports/saved',              [ReportController::class, 'store']],
    ['GET',    '/reports/saved/{id}',         [ReportController::class, 'view']],

    ['GET',    '/replay',                     [SessionReplayController::class, 'index']],
    ['GET',    '/replay/{session_id}',        [SessionReplayController::class, 'show']],

    ['GET',    '/users',                      [UserController::class, 'index']],
    ['GET',    '/users/new',                  [UserController::class, 'create']],
    ['POST',   '/users',                      [UserController::class, 'store']],
    ['GET',    '/users/{id}/edit',            [UserController::class, 'edit']],
    ['PUT',    '/users/{id}',                 [UserController::class, 'update']],
    ['DELETE', '/users/{id}',                 [UserController::class, 'destroy']],
];
```

### 3. Pattern matcher

Convert `{placeholder}` patterns to named regex groups, then pass matched params to the controller:

```php
private static function match(string $pattern, string $path): array|false {
    $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
    $regex = '@^' . $regex . '$@';
    if (!preg_match($regex, $path, $m)) return false;
    return array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
}
```

Controllers that previously read `$_GET['id']` or `$_GET['session_id']` will instead receive params as a function argument (or via a request context object).

---

## Controller Changes Required

Each controller that reads an ID needs to accept it as a parameter instead of from `$_GET` or `$_POST`:

| Controller method        | Currently reads              | Will receive           |
|--------------------------|------------------------------|------------------------|
| `ReportController::view` | `$_GET['id']`                | `$params['id']`        |
| `UserController::edit`   | `$_GET['id']`                | `$params['id']`        |
| `UserController::update` | `$_POST['id']`               | `$params['id']`        |
| `UserController::destroy`| `$_POST['id']`               | `$params['id']`        |
| `SessionReplayController::show` | `$_GET['session_id']` | `$params['session_id']`|

---

## View / Form Changes Required

Every form or link that targets a renamed or method-changed route needs updating:

| Was                              | Becomes                                  |
|----------------------------------|------------------------------------------|
| `action="/users/store"`          | `action="/users"` (POST)                 |
| `action="/users/update"`         | `action="/users/5"` + `_method=PUT`      |
| `action="/users/delete"`         | `action="/users/5"` + `_method=DELETE`   |
| `href="/users/edit?id=5"`        | `href="/users/5/edit"`                   |
| `href="/users/create"`           | `href="/users/new"`                      |
| `action="/reports/saved/store"`  | `action="/reports/saved"` (POST)         |
| `href="/reports/saved/view?id=3"`| `href="/reports/saved/3"`                |
| `href="/reports/static/generate"`| `href="/reports/static/new"`             |
| `href="/replay/show?session_id=X"`| `href="/replay/X"`                      |
| `href="/logout"`                 | form with POST to `/logout`              |

---

## Implementation Order

1. **Rewrite `Router::dispatch()`** — add method override, route table, and pattern matcher. Keep requires at the top. This is self-contained and testable before touching anything else.
2. **Update controllers** — change method signatures to accept `$params` array.
3. **Update views/forms** — update `action`, `href`, and add `_method` hidden fields where needed.
4. **Test each route group** — auth → dashboard → reports → replay → users.

---

## What Does NOT Change

- `api/router.php` — already RESTful with path-based IDs and proper method routing. Leave it alone.
- `.htaccess` — rewrite rules are correct and unchanged.
- Auth logic, session handling, RBAC — none of this is touched.
- Query params for pagination (`?page=N`) — these are filters, not resource identifiers. Keeping them as query params is correct REST.
