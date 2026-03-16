## Links

**URL:** 
1. https://reporting.angelo-j.xyz
2. https://test.angelo-j.xyz
3. https://collector.angelo-j.xyz/
4. https://angelo-j.xyz/
5. [repo](https://github.com/a2jensen/analytics-project)

## AI Usage
We used claude code religously throughout this entire process while also referring to documentation from the course website alongside online documentation. The workflow was `1. plan mode, 2. review, 3. implement 4. test`. Within plan mode we had it refer to this file which housed the architecture context and we had the model write out a new markdown file detailing a plan on how it will implement a given feature with additional manual testing so we can also verify. We referred to the course website for ensuring we followed some of the architectural practices. In the `repo` you can find the plan markdown files in the `/plans` folder.

We did not have any problems in token usage. Maybe because just in the last couple days they expanded context windows to 1 million tokens(just a hunch).

Errors found
- Initial setup did not have controllers throwing 500 error pages. Had to refactor and fix that.
- UI was very poor to start. We still think the UI could be better in certain ways but the model was absolutely terrible in this regard.
- Did not factor in strong passwords nor pagination at the start. We had to inform the model of this.

Usage of Claude Code provided a lot of value but we still approached its code generation with pessimism and we tried our best to match the architecuture and coding practices with whats mentioned on the course website.

## Future Features
We attempted add in a session replay feature but due to time constraints we could not. Given that our architecuture leaned heavily towards the server handling most of the load there would have been additional compexity to get a dynamic feature like a replay mode added.


## Architecture
Our server stack is Apache + PHP. PHP runs as an Apache module.

1. Apache receives the request
2. Passes it in to the embedded PHP inerpreter
3. PHP runs, outputs response, done
4. Aside from the SESSION state, everything dies at the end of the request


## Authentication and Authorization

Our application uses PHP sessions for stateful server side storage.

On a user logging in:
1. PHP creates a session file on the server
2. Sends the session ID to the browser as a cookie
3. On every subsequent request, the browser sends that cookie back, PHP/server reads the matching file and then $_SESSION is populated

A different approach compared to using JWT(in which the server holds no state).

---

## Model

Models encapsulate all database queries. Each analytics table has a dedicated model, and additional models handle users, saved reports, and session replay.

| Model | Table(s) | Purpose |
|---|---|---|
| `StaticModel` | `static_data` | Device/browser environment data — network type, screen size, memory, cores, color scheme, timezone |
| `PerformanceModel` | `performance_data` | Page load timing — TTFB, DOM events, Core Web Vitals (LCP, CLS, INP), network phases (DNS, TCP, TLS) |
| `ActivityModel` | `activity_data` | User interaction events — clicks, scrolls, keyboard, idle periods, time on page |
| `DashboardModel` | All three data tables | Aggregation layer for the dashboard — row counts, averages, distributions, hourly heatmap |
| `ReportModel` | `reports` | Saved report CRUD — stores frozen JSON snapshots of data with user attribution |
| `User` | `users`, `user_sections` | User CRUD + role/section assignment. `user_sections` is a junction table granting analysts access to specific data sections |
| `SessionReplayModel` | `activity_data`, `static_data` | Groups activity events by session for replay — retrieves ordered events and device metadata (screen size, user agent) |
| `Stats` | All three data tables | Legacy stub with simple static getters — kept for backward compatibility |

All models receive a shared PDO instance from `api/db.php`. The three analytics models (`Static`, `Performance`, `Activity`) are read-only from the web app side — writes come exclusively through the API layer. ReportModel has responsible for writing.

---

## View

Views are PHP templates rendered server-side, we chose a server side MVC model since our application did not have too much interactivity and thus we wanted to take advantage of speed. The controller passes data as local variables, the view produces HTML, and the browser receives a complete page.


---

## Controller

Controllers can take multiple forms and are invoked first whenever a request is made. They are responsible for calling the models/service layers and serving them back to the requester. Think of these as part of the interface layer of a web application/website alongside the defined HTTP endpoints.

High Level Flow:
1. Controller Handles Request
2. Calls relevant models and gets data
3. Passes data into the view to render

A proper flow:
1. User sends a GET req to `/stats`
2. On our server getting the req, our `Router.php` will then invoke `StatsController.php`
3. The controller calls the relevant business logic(`StatsModel.php`), gets the data and renders the view in either two ways:
    4. Send back as JSON, thus the client will take the JSON and format it onto a page that they render on their side(done by REACT, VUE, Angular)
    5. Send back as a page(which is what we do), where we invoke a view to be built on the server side and send it over to the client via `html`. The client simply renders an HTML page.

The three types of controllers:
1. Resource Based : Correspond to domain resources or entities in the system
    - Example: `UsersController` which handles requests to `/users`. `/users` depending on the context could be a page on the client displaying all the users or an endpoint that returns JSON.
        - Here the controller will fetch data from the models/service layer and send them back to the client as either a file page or JSON. In the context of this project, the data is purely static so we render the view on the server and send it over to the client which just renders it quickly as an HTML page.
2. Concern-based controllers: Handle application behaviors such as authorization and authentication
    - Example: `AuthController` which handles requests to `/login`
3. Page Controllers: Some pages may aggregate from multiple models, so they may build up a view that uses multiple models. 
    - Example: `DashboardController` which handles requests to `/dashboard` and `/dashboard` is a page on the client that may display statistics from multiple tables(like how our project does it).
        - The controller will now access multiple models and aggregate the data to send over as a JSON or a file page.

| Controller | Route(s) | Page/Responsibility |
|---|---|---|
| `AuthController` | `/login`, `/logout` | Login form + session lifecycle |
| `DashboardController` | `/dashboard` | Summary overview page — cards + charts aggregated from all three tables |
| `ReportsController` | `/reports/static`, `/reports/performance`, `/reports/activity` | Live data table + chart pages for each section |
| `UserController` | `/users`, `/users/create`, `/users/edit`, `/users/update`, `/users/delete` | User management CRUD — super_admin only |
| `ReportController` | `/reports/saved`, `/reports/saved/store` | Saved/published reports — viewer landing page |

### Pages

| View | What it renders |
|---|---|
| `login.php` | Username/password form, error display |
| `dashboard.php` | Summary cards (record counts) + charts grid (network types, avg performance, event breakdown, hourly heatmap) |
| `static.php` | Static data table + charts (network type, device RAM, CPU cores) |
| `performance.php` | Performance data table + charts (timing per session, Core Web Vitals health, network timing breakdown) |
| `activity.php` | Activity data table + charts (events by type, most clicked elements, scroll depth distribution) |
| `reports/saved.php` | List of all saved reports with title, section, author, date |
| `reports/view.php` | Single saved report — metadata, commentary, data table + chart from the frozen snapshot |
| `reports/generate.php` | Form for creating a new saved report (title, commentary, chart type) |
| `users/index.php` | User management table (super_admin only) |
| `users/form.php` | Create/edit user form with role and section assignment |

Charts use Chart.js and are configured inline with `<script>` blocks. Each chart includes a title, labeled axes, contextual tooltips, and a `.chart-desc` paragraph explaining what the data represents.

---

## API Layer

The API is a separate routing layer under `/api/` for ingesting analytics data from a client-side SDK. It is distinct from the web MVC routes. This was added for `curl` testing.

- `api/router.php` — API gateway. Handles CORS, OPTIONS preflight, rate limiting (100 req/min per IP), and dispatches to resource handlers based on URL path.
- `api/db.php` — Shared PDO connection factory.
- `api/static.php` — RESTful CRUD for `static_data` (device/environment metadata).
- `api/performance.php` — RESTful CRUD for `performance_data` (page load timings, Web Vitals).
- `api/activity.php` — RESTful CRUD for `activity_data` (clicks, scrolls, keyboard, errors).

All API handlers read JSON from `php://input` for POST/PUT and return JSON responses.

---

## Router

`app/core/Router.php` is the front controller for all web page routes. It normalizes URLs (strips trailing slashes), requires controller files, and dispatches to the correct method. This is separate from `api/router.php` which handles `/api/*` requests.

We added pagination across our web application to ensure scalability.

---

## Auth

`app/core/Auth.php` provides session-based authentication and authorization.

- `Auth::check()` — Returns true if a session exists
- `Auth::require()` — Redirects to `/login` if not authenticated
- `Auth::requireRole(...)` — Gates by role, returns 403 if unauthorized
- `Auth::canAccessSection(string)` — Checks if an analyst has access to a specific data section; super_admins bypass this check

User role, username, and allowed sections are cached in `$_SESSION` at login so every request can authorize without a database query.

#### Planned Security / Scalability Improvements

1. **CSRF Protection** — Add CSRF token generation and validation to all state-changing forms (login, user create/edit/delete, report save). Prevents cross-site request forgery attacks where a malicious site tricks an authenticated user's browser into submitting forms.
   - See: `PLAN_CSRF_PROTECTION.md`

3. **Environment Variable Configuration** — Move hardcoded database credentials and sensitive config out of source files into a `.env` file. Prevents credential leakage through version control or file exposure.
   - See: `PLAN_ENV_CONFIG.md`

---

