### Instructions for the assignment
As we try to build out our analytics backend system we have three pressing issues we need to address

1. Building the scaffold of an MVC style app with authentication and navigation

2. Connecting our Datastore to a Data Table / Grid

3. Connection our Datastore to a Chart

While we may have an ability to build these things out quickly with GenAI technology, we do not want to add risk so this is a derisk checkpoint assignment to keep you on pace.  It is possible you may have already accomplished it.  If so, that's wonderful!

So to show you have accomplished step 1 you need the first cut of a backend with a login page that you can login to some pages behind.  Forceful browsing where we can type in a reports URL and bypass the login is the key here you must have an authentication system working with login and logout.  You do not need to make a sign-up system, but you can if you like.

To show that you have accomplished step 2 you must have a table created in raw HTML or using a package like ZingGrid or others to display some data in a raw format from your database that contains collected data.

To show that you have accomplished step 3 you must have a chart or two using ChartJS, D3, or ZingChart as you like. 

You can only use PHP or NodeJS.  If you use frameworks that is allowed, but try not to just slap boilerplate code everywhere as it may add risk to get to a complete solution by the end of the quarter.

Turn in a README detailing your three points and providing a URL, username/password for the grader to look that you accomplished the tasks defined.  

As stated you are free to accelerate and expand, but this is your minimum by the weekend requirement

### relevant data from the previous assignment submission
the prev assignment handled funcionality of collecting data, so all the API routes are all setup. 


- **Reporting API Static Data:** https://reporting.angelo-j.xyz/api/static
- **Reporting API Activity Data:** https://reporting.angelo-j.xyz/api/activity
- **Reporting API Performance Data:** https://reporting.angelo-j.xyz/api/performance


## API Endpoints

| HTTP Method | Route | Description |
|-------------|-------|-------------|
| GET | /api/static | Retrieve all static entries |
| GET | /api/static/{id} | Retrieve specific static entry |
| POST | /api/static | Add new static entry |
| DELETE | /api/static/{id} | Delete specific static entry |
| PUT | /api/static/{id} | Update specific static entry |
| GET | /api/performance | Retrieve all performance entries |
| GET | /api/performance/{id} | Retrieve specific performance entry |
| POST | /api/performance | Add new performance entry |
| DELETE | /api/performance/{id} | Delete specific performance entry |
| PUT | /api/performance/{id} | Update specific performance entry |
| GET | /api/activity | Retrieve all activity entries |
| GET | /api/activity/{id} | Retrieve specific activity entry |
| POST | /api/activity | Add new activity entry |
| DELETE | /api/activity/{id} | Delete specific activity entry |
| PUT | /api/activity/{id} | Update specific activity entry |


## Database Schema

Three tables in the `analytics` MySQL database:

- **static_data** — technographic info collected on pageview (user agent, screen dimensions, network type, language, timezone, etc.)
- **performance_data** — page load timing and web vitals (TTFB, DOM complete, LCP, CLS, INP, etc.)
- **activity_data** — continuously collected user interactions (clicks, scrolls, keyboard activity, page enter/exit, idle time)

All three tables are linked by `session_id` which is also present in Apache access logs via a cookie, enabling a full join between server-side logs and client-side beacon data.

---

## Submission

**URL:** https://reporting.angelo-j.xyz

**Login credentials:**
| Field | Value |
|-------|-------|
| Username | `admin` |
| Password | `cse135report` |

### 1. MVC App with Authentication and Navigation

Built a PHP MVC-style app with a front controller (`index.php`), a router (`app/core/Router.php`), and separate controllers and views. Authentication is session-based — logging in sets `$_SESSION['user']` and every protected route calls `Auth::require()` which redirects unauthenticated users to `/login`. Forceful browsing (typing `/dashboard` or any report URL directly) is blocked and redirects to the login page. Logout destroys the session. A persistent nav bar links to all sections.

- Login: https://reporting.angelo-j.xyz/login
- Dashboard: https://reporting.angelo-j.xyz/dashboard (protected)

### 2. Data Tables Connected to the Datastore

Three report pages each fetch live rows from the MySQL `analytics` database and render them as raw HTML tables:

- **Static data** (technographic info per session): https://reporting.angelo-j.xyz/reports/static
- **Performance data** (page load timings and web vitals): https://reporting.angelo-j.xyz/reports/performance
- **Activity data** (user interaction events): https://reporting.angelo-j.xyz/reports/activity

### 3. Charts Connected to the Datastore

Built with Chart.js (CDN). Charts appear on the dashboard and on the performance and activity report pages:

- **Dashboard** — three charts, one per data source:
  - Sessions by network type (static_data)
  - Average timing metrics — TTFB, DOM Complete, LCP, Total Load (performance_data)
  - Event count by type (activity_data)
- **Performance report** — grouped bar chart of TTFB, DOM Complete, and LCP per session (10 most recent)
- **Activity report** — bar chart of event type distribution

---

## Original index.html (HW1 placeholder)

Replaced by `index.php` during HW2 MVC implementation.

```html
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CSE 135 HW Lives!</title>
</head>

<body>
  <h1>CSE 135 HW1 Lives!</h1>
  <h2>reporting.angelo-j.xyz</h2>
  <script>
    document.write(`Live @ ${new Date()}`);
  </script>

</body>

</html>
```
