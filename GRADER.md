# Grader Guide

**URL:** https://reporting.angelo-j.xyz

## Login Credentials

| Role | Username | Password | Access |
|------|----------|----------|--------|
| Super Admin | `admin` | `` | Full access — dashboard, all reports, user management |
| Analyst | `sam` | `` | Dashboard, static/performance/activity reports, saved reports |
| Analyst | `sally` | `` | Dashboard, activity reports only, saved reports |
| Viewer | `viewer1` | `` | Saved reports only |

---

## Guided Walkthrough

### Step 1: Super Admin Flow
1. Log in as `admin`
2. You land on `/dashboard` — three summary cards and three Chart.js charts (network types, avg timing, event types)
3. Click **Static Data** in the nav → paginated table of technographic records (25/page), chart of network type distribution
4. Click **Performance** → timing metrics table + grouped bar chart of recent sessions
5. Click **Activity** → interaction events table + event type chart
6. Click **Users** in the nav → user management CRUD. You can create, edit, and delete users. Try creating a test user with the analyst role and assigning sections
7. Click **Saved Reports** → view any analyst-generated report snapshots with charts and commentary
8. Log out

### Step 2: Analyst Flow
1. Log in as `sam` (has access to all three sections)
2. Dashboard shows all three charts
3. Visit any report page → click **Save Report** to create a frozen snapshot with a title, chart type, and commentary
4. Visit **Saved Reports** to see it listed
5. Log out, then log in as `sally` (activity section only)
6. Dashboard only shows the activity chart — static/performance cards and charts are hidden
7. Nav only shows Activity under reports — trying to visit `/reports/static` directly returns 403

### Step 3: Viewer Flow
1. Log in as `viewer1`
2. Redirected to `/reports/saved` — this is the only page accessible
3. Can view saved report details but cannot create new ones
4. Nav only shows Saved Reports — all other routes return 403

### Step 4: User Signup
1. Log out and click **Sign up** on the login page (or visit `/signup` directly)
2. Create a new account with a username (3+ chars) and password (8+ chars)
3. On success, redirected to `/login` with a green "Account created" message
4. Log in with the new credentials — the account defaults to `viewer` role (saved reports only)

### Step 5: Extra Credit — Hourly Activity Heatmap
1. Log in as `admin` and go to `/dashboard`
2. Scroll below the three main charts — a full-width stacked bar chart shows **user activity by hour of day** (0–23)
3. Each bar is broken down by event type (clicks, scrolls, keyboard, page exits) with a color legend
4. Hover over any segment to see the exact count

### Step 6: — PDF Export with Charts
1. Navigate to any report page (e.g., `/reports/static`)
2. Click **Export** — the PDF now includes the chart image captured from the page, plus a styled data table
3. This also works on saved reports at `/reports/saved/view?id=X`

### Additional: Security Check (DO THIS AT THE VERY END OF THE GRADING SESSION!)
1. Log out and try accessing `/dashboard` directly → redirected to `/login`
2. On the login page, submit 5 wrong passwords → 6th attempt shows "Too many login attempts. Please try again in 15 minutes." (rate limiting)

---

## Known Issues & Concerns

1. **No CSRF tokens on forms** - Forms (login, user CRUD, report save) do not have CSRF protection yet. Low risk since the app is session-based and not handling sensitive financial data, but it's a gap and something we should consider if we need this application to scale.

2. **Hardcoded DB credentials** - `api/db.php` has the database password in plain text. Plan to move to `.env` file exists in `PLAN_ENV_CONFIG.md` but is not yet implemented. This is something that should definitely not occur AT ALL when actually releasing this to users but we just need

3. ***Test Users And Weak Passwords** - We added the test users with weak passwords for ease of use of signing in. If this were to be actually deployed we would ensure that there would be no users with weak passwords remaining.