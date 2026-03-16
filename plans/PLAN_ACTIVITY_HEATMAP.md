# Plan: Activity Heatmap on Dashboard

## Overview
Add a heatmap chart to the dashboard that visualizes activity event density by hour of day and event type. Each cell's color intensity represents how many events occurred during that hour for that event type. This gives a quick visual of when users are most active and what they're doing.

The heatmap is a grid:
- **X-axis:** Hours of the day (0–23)
- **Y-axis:** Event types (click, scroll_depth, scroll_final, keyboard_activity, page_exit)
- **Cell color:** Intensity from light to dark based on event count (0 = white/empty, high count = deep blue)

## Current State
- Dashboard has three charts: network types (static), avg timings (performance), event types (activity)
- All charts use Chart.js (loaded via CDN)
- Dashboard data is fetched by `DashboardModel` and passed to the view
- The heatmap will sit in the activity section of the dashboard (gated by activity section access)

## Implementation

### Step 1: Add Heatmap Query to DashboardModel
**File:** `app/models/DashboardModel.php`

Add method `getActivityHeatmap(): array`:
```sql
SELECT HOUR(event_timestamp) as hour, type, COUNT(*) as count
FROM activity_data
GROUP BY hour, type
ORDER BY hour, type
```

Return the raw rows. The view/JS will transform this into the grid structure Chart.js needs.

### Step 2: Pass Heatmap Data from Controller
**File:** `app/controllers/DashboardController.php`

Add a call to `$model->getActivityHeatmap()` and pass the result as `$heatmapData` to the view. Only fetch if the user has activity section access.

### Step 3: Add Heatmap to Dashboard View
**File:** `app/views/dashboard.php`

Inside the `<?php if (in_array('activity', $allowedSections)): ?>` block, add a new chart section after the existing "Events by Type" chart:

```html
<div>
    <h2>Activity — Hourly Heatmap</h2>
    <div class="chart-wrap">
        <canvas id="heatmapChart"></canvas>
    </div>
    <p class="chart-desc">Shows when users are most active throughout the day.
    Each row is an event type and each column is an hour (0-23).
    Darker cells indicate more events during that hour.</p>
</div>
```

### Step 4: Render Heatmap with Chart.js Matrix Plugin
Chart.js doesn't have a native heatmap type, but we can use the `chartjs-chart-matrix` plugin (small CDN script). Alternatively, we can build the heatmap as a pure HTML/CSS grid — no extra dependency.

**Recommended approach: HTML/CSS grid** (no extra plugin needed):
- Build the grid in PHP/HTML with inline background colors
- Each cell is a small `<div>` with `background-color` set to an rgba blue value where opacity scales with the count
- Hover shows a tooltip with the exact count

The JS in the view will:
1. Take the `$heatmapData` PHP array (passed via `json_encode`)
2. Find the max count to normalize colors
3. For each cell, calculate opacity: `count / maxCount`
4. Set background: `rgba(30, 58, 95, opacity)` (matches the nav blue)

### Step 5: Style the Heatmap Grid
**File:** `public/css/style.css`

Add styles for the heatmap:
- `.heatmap-grid` — CSS Grid with 25 columns (1 label + 24 hours)
- `.heatmap-cell` — small square with rounded corners, hover effect
- `.heatmap-label` — row/column labels
- `.heatmap-tooltip` — shows count on hover

## Files Modified
| File | Action |
|------|--------|
| `app/models/DashboardModel.php` | **Modify** — Add `getActivityHeatmap()` query |
| `app/controllers/DashboardController.php` | **Modify** — Fetch and pass heatmap data |
| `app/views/dashboard.php` | **Modify** — Add heatmap HTML grid + JS color logic |
| `public/css/style.css` | **Modify** — Heatmap grid styles |

## Manual Testing

### Test 1: Heatmap Renders on Dashboard
1. Log in as `admin`
2. Navigate to `/dashboard`
3. Scroll down past the existing charts
4. Confirm the "Activity — Hourly Heatmap" section appears with a grid

### Test 2: Color Intensity Matches Data
1. Look at the heatmap — the darkest cells should correspond to hours with the most events
2. Cross-reference with the database: hours 3 and 6 should be the darkest (most activity based on current data)
3. Hours with no activity (e.g., 2, 4, 5, 7–9, 11–20, 22–23) should be empty/white

### Test 3: Hover Tooltip
1. Hover over a colored cell
2. Confirm a tooltip shows the event type, hour, and exact count (e.g., "click @ 6:00 — 14 events")

### Test 4: Access Control
1. Log in as `sally` (activity access) → heatmap should appear
2. Log in as an analyst without activity access → heatmap should NOT appear
3. Log in as `viewer1` → redirected to saved reports, no dashboard at all

### Test 5: Empty State
1. If activity_data were empty, confirm the heatmap shows an empty grid or a "No data" message (no crash)
