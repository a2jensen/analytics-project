# Plan: Additional Charts on Report Pages

## Overview
Add 2 additional charts to each report page (static, performance, activity) to give users more variety in how they view the data. Currently each page has a single chart — this brings each to 3 charts total, arranged in a row below the data table.

## Static Data Page — Current: Network Type bar chart

### New Chart 1: Device Memory (RAM) Distribution
**Type:** Doughnut chart
**Query:** `SELECT memory, COUNT(*) as count FROM static_data GROUP BY memory ORDER BY memory`
**What it shows:** Distribution of users by device RAM (e.g., 0 GB, 8 GB). Helps understand the hardware profile of the user base — useful for knowing if your audience is on low-end or high-end devices.
**Model method:** `StaticModel::getMemoryDistribution(): array`

### New Chart 2: CPU Cores Distribution
**Type:** Bar chart
**Query:** `SELECT cores, COUNT(*) as count FROM static_data GROUP BY cores ORDER BY cores`
**What it shows:** How many sessions come from devices with different CPU core counts (4, 8, 12). Paired with RAM, gives a fuller picture of user device capability.
**Model method:** `StaticModel::getCoresDistribution(): array`

---

## Performance Data Page — Current: TTFB/DOM Complete/LCP grouped bar (recent 10 sessions)

### New Chart 1: Core Web Vitals Summary
**Type:** Bar chart (horizontal)
**Query:** `SELECT ROUND(AVG(lcp),1) as lcp, ROUND(AVG(cls),4) as cls, ROUND(AVG(inp),1) as inp FROM performance_data`
**What it shows:** Average values for the three Core Web Vitals (LCP, CLS, INP) with color-coded thresholds:
- LCP: green < 2500ms, orange < 4000ms, red > 4000ms
- CLS: green < 0.1, orange < 0.25, red > 0.25
- INP: green < 200ms, orange < 500ms, red > 500ms
This gives a quick "health check" of the site's user experience.
**Model method:** `PerformanceModel::getWebVitalsAvg(): array`

### New Chart 2: Network Timing Breakdown
**Type:** Stacked bar chart (single bar showing the average request lifecycle)
**Query:** `SELECT ROUND(AVG(dns_lookup),1) as dns, ROUND(AVG(tcp_connect),1) as tcp, ROUND(AVG(tls_handshake),1) as tls, ROUND(AVG(ttfb),1) as ttfb, ROUND(AVG(download),1) as download FROM performance_data`
**What it shows:** Average time spent in each phase of a network request (DNS → TCP → TLS → TTFB → Download). Helps identify which phase is the bottleneck.
**Model method:** `PerformanceModel::getNetworkTimingAvg(): array`

---

## Activity Data Page — Current: Events by Type bar chart

### New Chart 1: Most Clicked Elements
**Type:** Horizontal bar chart
**Query:** `SELECT tag_name, COUNT(*) as count FROM activity_data WHERE type='click' AND tag_name IS NOT NULL GROUP BY tag_name ORDER BY count DESC LIMIT 10`
**What it shows:** Which HTML elements users click most (IMG, A, BUTTON, DIV, etc.). Reveals what users are interacting with — if they're clicking images expecting links, or if CTAs are being engaged.
**Model method:** `ActivityModel::getClickedElements(): array`

### New Chart 2: Scroll Depth Distribution
**Type:** Bar chart (histogram-style)
**Query:** `SELECT CASE WHEN scroll_depth BETWEEN 0 AND 25 THEN '0-25%' WHEN scroll_depth BETWEEN 26 AND 50 THEN '26-50%' WHEN scroll_depth BETWEEN 51 AND 75 THEN '51-75%' ELSE '76-100%' END as depth_range, COUNT(*) as count FROM activity_data WHERE type='scroll_final' AND scroll_depth IS NOT NULL GROUP BY depth_range ORDER BY depth_range`
**What it shows:** How far users scroll before leaving — bucketed into quartiles. Shows whether users read the full page or bounce early. Important for content strategy.
**Model method:** `ActivityModel::getScrollDepthDistribution(): array`

---

## Implementation

### Step 1: Add Model Methods
**Files:**
- `app/models/StaticModel.php` — Add `getMemoryDistribution()` and `getCoresDistribution()`
- `app/models/PerformanceModel.php` — Add `getWebVitalsAvg()` and `getNetworkTimingAvg()`
- `app/models/ActivityModel.php` — Add `getClickedElements()` and `getScrollDepthDistribution()`

### Step 2: Update Controllers to Fetch New Data
**File:** `app/controllers/ReportsController.php`

In each section's method (`staticData()`, `performanceData()`, `activityData()`), call the new model methods and pass the results to the view.

### Step 3: Add Charts to Views
**Files:** `app/views/static.php`, `app/views/performance.php`, `app/views/activity.php`

For each page, add a charts row below the existing chart using the existing `charts-grid` CSS class (same layout as the dashboard). Each row contains the existing chart + 2 new charts in a responsive grid.

### Step 4: Style (if needed)
The existing `charts-grid` and `chart-wrap` CSS classes should handle layout. No new CSS expected unless adjustments are needed for horizontal bar charts.

## Files Modified
| File | Action |
|------|--------|
| `app/models/StaticModel.php` | **Modify** — Add 2 query methods |
| `app/models/PerformanceModel.php` | **Modify** — Add 2 query methods |
| `app/models/ActivityModel.php` | **Modify** — Add 2 query methods |
| `app/controllers/ReportsController.php` | **Modify** — Fetch and pass new chart data |
| `app/views/static.php` | **Modify** — Add 2 new Chart.js charts |
| `app/views/performance.php` | **Modify** — Add 2 new Chart.js charts |
| `app/views/activity.php` | **Modify** — Add 2 new Chart.js charts |

## Manual Testing

### Test 1: Static Page Charts
1. Log in as `admin`, go to `/reports/static`
2. Confirm 3 charts visible: Network Type (existing), Device Memory (doughnut), CPU Cores (bar)
3. Hover over doughnut slices — confirm tooltips show memory value and count
4. Confirm CPU cores chart shows bars for each core count

### Test 2: Performance Page Charts
1. Go to `/reports/performance`
2. Confirm 3 charts: Session Timings (existing), Core Web Vitals (bar with threshold colors), Network Timing Breakdown (stacked bar)
3. Confirm Web Vitals bars are color-coded (green/orange/red based on thresholds)
4. Confirm Network Timing shows DNS → TCP → TLS → TTFB → Download as stacked segments

### Test 3: Activity Page Charts
1. Go to `/reports/activity`
2. Confirm 3 charts: Events by Type (existing), Most Clicked Elements (horizontal bar), Scroll Depth Distribution (bar)
3. Confirm clicked elements shows tag names (IMG, A, etc.) sorted by count
4. Confirm scroll depth shows 4 buckets (0-25%, 26-50%, 51-75%, 76-100%)

### Test 4: Responsive Layout
1. Resize the browser window narrower
2. Confirm charts wrap to stack vertically instead of breaking layout

### Test 5: Empty Data Handling
1. If any chart has no data (e.g., no clicks with tag_name), confirm it either shows an empty chart or a "No data" message — no JS errors
