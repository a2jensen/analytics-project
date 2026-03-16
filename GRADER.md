# Grader Guide

**URL:** https://reporting.angelo-j.xyz

## Login Credentials

| Role | Username | Password | Access |
|------|----------|----------|--------|
| Super Admin | `admin` | `cse135report` | Full access — dashboard, all reports, user management |
| Analyst | `sam` | `sam123` | Dashboard, static/performance/activity reports, saved reports |
| Analyst | `sally` | `sally123` | Dashboard, activity reports only, saved reports |
| Viewer | `viewer1` | `viewer123` | Saved reports only |

---

## Guided Walkthrough

### Step 1: Super Admin Flow
1. Log in as `admin`
2. You land on `/dashboard` — three summary cards and three Chart.js charts (network types, avg timing, event types)
3. Click **Static Data** in the nav → paginated table of technographic records (25/page), analyst commentary, and 3 charts (network type, device memory, CPU cores)
4. Click **Performance** → timing metrics table, analyst commentary with Web Vitals pass/fail assessment, and 3 charts (session timings, Core Web Vitals, network timing breakdown)
5. Click **Activity** → interaction events table, analyst commentary with engagement analysis, and 3 charts (events by type, clicked elements, scroll depth)
6. Click **Users** in the nav → user management CRUD. You can create, edit, and delete users. Try creating a test user with the analyst role and assigning sections
7. Click **Saved Reports** → view any analyst-generated report snapshots with charts and commentary
8. Log out

### Step 2: Analyst Flow
1. Log in as `sam` (has access to all three sections)
2. Dashboard shows all three charts
3. Visit any report page → note the **Analyst Commentary** section with auto-generated insights, then click **Generate Report** to create a frozen snapshot with a title, chart type, and pre-filled commentary (editable)
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

### Step 6: PDF Export with Charts
1. Navigate to any report page (e.g., `/reports/static`)
2. Click **Export** — the PDF now includes the chart image captured from the page, plus a styled data table
3. This also works on saved reports at `/reports/saved/view?id=X`

### Additional: Security Check (DO THIS AT THE VERY END OF THE GRADING SESSION!)
1. Log out and try accessing `/dashboard` directly → redirected to `/login`
2. On the login page, submit 5 wrong passwords → 6th attempt shows "Too many login attempts. Please try again in 15 minutes." (rate limiting)

---

## Known Issues & Concerns

1. **No CSRF tokens on forms** - Forms (login, user CRUD, report save) do not have CSRF protection yet. Low risk since the app is session-based and not handling sensitive financial data, but it's a gap and something we should consider if we need this application to scale.

2. **Test Users And Weak Passwords** - We added the test users with weak passwords for ease of use of signing in. If this were to be actually deployed we would ensure that there would be no users with weak passwords remaining.

3. **Scalability Issues** - We added pagination and rate limiting on the login page but we do wonder what other features or changes we have to make to the architecture if this application were to scale. Still not fully sure here.

4. ***Auto Generated Analysts** - We implemented auto generated comments but I do not know how much useful it would be to a user. We wanted to play around with added this functionality though and give the analyst something to start off with which we think helps in that area.

---

## Extra Credit / Expanded Features

- **Rate Limiting** — IP-based rate limiting on login (5 attempts / 15 min) and API endpoints (100 req / min). Uses a `rate_limits` MySQL table.
- **User Signup** — Public registration at `/signup`. New accounts default to `viewer` role. Password hashed with bcrypt, validated (8+ chars).
- **Dashboard Activity Heatmap** — Full-width stacked bar chart on `/dashboard` showing hourly activity breakdown (0–23h) by event type (click, scroll, keyboard, page_exit).
- **Additional Charts per Report Page** — Each report page has 3 stacked charts in the sidebar: Static (network type bar, memory doughnut, CPU cores bar), Performance (session timings grouped bar, Web Vitals with threshold colors, network timing stacked bar), Activity (events by type, clicked elements horizontal bar, scroll depth quartiles).
- **Data-Driven Analyst Commentary** — Each report page has an auto-generated "Analyst Commentary" section that interprets the actual data (dominant network type %, Web Vitals pass/fail vs Google thresholds, engagement ratios, scroll depth analysis). Not hardcoded descriptions — values update dynamically.
- **Auto-Populated Commentary on Report Generation** — When generating a saved report, the commentary textarea is pre-filled with auto-generated insights via `CommentaryGenerator`. Analysts can edit/add their own notes before saving.