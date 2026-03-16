# Plan: Improve Report Pages with Analyst Commentary

## Overview
The assignment requires reports with "analyst comments" — text items that decode the meaning of the data, not just describe what the chart shows. Currently the report pages have `chart-desc` paragraphs that explain what the chart *is*, but they don't interpret what the data *means*. This plan adds data-driven analyst commentary sections to each report page that dynamically interpret the actual values.

## Current State
- Each report page (static, performance, activity) has charts with static `chart-desc` text
- These descriptions explain what the chart measures, but don't analyze the data itself
- The saved reports feature already has a "commentary" text field — but the live report pages have nothing similar
- The assignment specifically calls for "analyst comments" that decode meaning, and warns that "just meeting the syntax requirements" may receive only half credit

## What's Missing (Gap Analysis)

### 1. No data-driven commentary
The current `chart-desc` text is hardcoded. It says things like "Distribution of visitor sessions by network connection type" — that's a chart label, not analysis. A proper analyst comment would say: "The majority of users (58%) are on 4G connections, suggesting a mobile-heavy audience. Consider optimizing asset sizes for bandwidth-constrained users."

### 2. No summary/insights section
There's no dedicated section on each page that ties the charts together into a cohesive narrative about what the data is telling us.

### 3. No visual distinction for commentary
Commentary should look visually different from chart descriptions — styled as an analyst's written interpretation, not a UI tooltip.

## Implementation

### Step 1: Add Data-Driven Commentary Blocks to Each Report View
For each report page, add a styled "Analyst Commentary" section **above the data-layout** (between the record count and the table/charts). This section computes insights from the data passed by the controller and renders them as formatted text.

The commentary is generated server-side in PHP using the same data variables already available to the view — no new queries needed.

### Step 2: Static Data Commentary
**File:** `app/views/static.php`

Add a commentary section that computes and displays:
- **Dominant network type** — Which network type has the most sessions and what percentage it represents. Interpretation: mobile-heavy vs desktop-heavy audience.
- **Device capability summary** — Most common RAM and core count. Interpretation: are users on low-end or high-end hardware? Should we optimize for constrained devices?
- **Browser feature support** — What % have cookies enabled, JS enabled. Interpretation: any risk of features breaking for users without JS/cookies?

Example output:
> **Network Profile:** 59% of sessions are on 4G connections, indicating a predominantly mobile user base. Only 3% are on wifi, suggesting most visits occur outside of home/office environments.
>
> **Device Hardware:** The majority of users (59%) have 8 GB RAM with 8-core processors — mid-to-high-end devices. 35% report 0 GB memory (API unsupported), making hardware-based optimizations unreliable for this segment.

### Step 3: Performance Data Commentary
**File:** `app/views/performance.php`

Add commentary computing:
- **Core Web Vitals verdict** — For each vital (LCP, CLS, INP), state whether it passes Google's thresholds and what that means. Color-code the text (green/orange/red).
- **Bottleneck identification** — Which network phase takes the most time (DNS, TCP, TLS, TTFB, download)? What does that suggest about the infrastructure?
- **Load time summary** — Average total load time and how it compares to industry benchmarks (e.g., under 3s is good).

Example output:
> **Web Vitals Assessment:** LCP averages 465ms (✓ Good — under 2500ms). CLS averages 0.0003 (✓ Excellent — under 0.1). INP averages 143ms (✓ Good — under 200ms). All three Core Web Vitals pass Google's recommended thresholds.
>
> **Network Bottleneck:** The download phase dominates at 132ms average, accounting for 61% of total request time. DNS and TLS are negligible. Consider CDN optimization or compression to reduce transfer times.

### Step 4: Activity Data Commentary
**File:** `app/views/activity.php`

Add commentary computing:
- **Engagement profile** — Which event type dominates? What does click-to-scroll ratio suggest about user behavior?
- **Click target analysis** — Most clicked element type and what that implies (e.g., IMG clicks suggest users expect images to be interactive).
- **Scroll behavior summary** — What % of users reach the bottom of the page? Is content being consumed fully?

Example output:
> **User Engagement:** Page exits and scroll events dominate the activity log (31% each), with clicks at 15%. The high exit-to-click ratio suggests users are browsing passively rather than actively engaging with interactive elements.
>
> **Click Targets:** Images (IMG) receive the most clicks (30%), followed by links (A) at 14%. Users appear to treat product images as clickable — ensure all images have appropriate link wrappers.
>
> **Scroll Depth:** 52% of users scroll to 76–100% of the page, indicating strong content engagement. Only 22% bounce in the 0–25% range.

### Step 5: Style the Commentary Section
**File:** `public/css/style.css`

Add a `.analyst-commentary` class styled distinctly from chart descriptions:
- Left border accent (like the existing `.report-commentary` on saved reports)
- Slightly larger font than `chart-desc`
- Background card with shadow
- Bold labels for each insight category
- Use existing color scheme

### Step 6: Compute Percentages/Insights in the View
Each commentary block will use inline PHP to compute:
- Percentages from count arrays (e.g., `$topNetwork['count'] / $total * 100`)
- Max/min values from aggregates
- Threshold comparisons for Web Vitals
- Simple conditional text (green ✓ / red ✗)

No new model methods needed — all data is already available in the view variables.

## Files Modified
| File | Action |
|------|--------|
| `app/views/static.php` | **Modify** — Add analyst commentary section with computed insights |
| `app/views/performance.php` | **Modify** — Add analyst commentary section with Web Vitals verdicts and bottleneck analysis |
| `app/views/activity.php` | **Modify** — Add analyst commentary section with engagement and scroll analysis |
| `public/css/style.css` | **Modify** — Add `.analyst-commentary` styles |

## Manual Testing

### Test 1: Commentary Renders with Correct Data
1. Log in as `admin`, visit `/reports/static`
2. Confirm an "Analyst Commentary" section appears between the record count and the data table
3. Confirm the percentages and values match the actual data (cross-reference with the charts)

### Test 2: All Three Pages Have Commentary
1. Visit `/reports/static` — confirm commentary about network, RAM, cores
2. Visit `/reports/performance` — confirm commentary about Web Vitals, bottlenecks
3. Visit `/reports/activity` — confirm commentary about engagement, clicks, scrolls

### Test 3: Commentary Updates with Data
1. If new data is added to the database, refresh the report page
2. Confirm the commentary values update accordingly (they're computed dynamically, not hardcoded)

### Test 4: Visual Distinction
1. Confirm the commentary section is visually distinct from chart descriptions
2. Should have a card-like appearance with a left border accent, not just plain text

### Test 5: Empty Data Graceful
1. If a section has no data, confirm the commentary either shows a "No data available for analysis" message or is hidden entirely — no PHP errors or division by zero

---

## Phase 2: Auto-Generated + Custom Commentary on Report Generation

### Problem
The generate report form (`/reports/{section}/generate`) already has a "Commentary" textarea, but it starts blank. The analyst has to write everything from scratch with no context. Meanwhile, the live report pages now have rich auto-generated insights — but those insights are not carried into saved reports.

The ideal flow: when an analyst opens the generate form, the commentary textarea is **pre-populated with auto-generated insights** (the same kind shown on the live report page). The analyst can then **edit, delete, or add to** these insights before saving. This gives them a head start while preserving full control.

### Step 7: Create a Commentary Generator Helper
**File:** `app/core/CommentaryGenerator.php` (new)

Create a helper class with static methods that compute the same insights currently done inline in the views, but return them as plain text strings (not HTML). One method per section:

- `CommentaryGenerator::static(PDO $pdo): string` — Queries static_data, computes dominant network type, device capability summary. Returns multi-line text.
- `CommentaryGenerator::performance(PDO $pdo): string` — Queries performance_data, computes Web Vitals verdict (Good/Needs Improvement/Poor with thresholds), network bottleneck. Returns multi-line text.
- `CommentaryGenerator::activity(PDO $pdo): string` — Queries activity_data, computes engagement profile, click targets, scroll depth. Returns multi-line text.

This centralizes the insight logic so it can be used both in the generate form (pre-population) and in the view templates (if desired later). The output is plain text with simple formatting (e.g., "Web Vitals: LCP 465ms — Good (under 2500ms)") rather than HTML, since it goes into a textarea.

### Step 8: Pre-Populate the Generate Form with Auto-Commentary
**Files:** `app/controllers/ReportController.php`, `app/views/reports/generate.php`

Changes to `ReportController::generate()`:
1. Load db.php and call `CommentaryGenerator::{$section}($pdo)` to get the auto-generated commentary string.
2. Pass it to the view as `$autoCommentary`.

Changes to `app/views/reports/generate.php`:
1. The existing textarea currently defaults to `$_POST['commentary'] ?? ''`. Change the fallback to `$autoCommentary ?? ''` so on first load (GET) it shows the auto-generated text, but on validation error re-render (POST) it preserves whatever the analyst typed.
2. Add a small label hint below the textarea: "Auto-generated insights are pre-filled. Edit or add your own commentary below."

This way:
- On first visit: textarea is pre-filled with data-driven insights.
- Analyst can freely edit, append, or clear the text.
- On form resubmission after validation error: their edits are preserved (from `$_POST`).

### Step 9: Display Commentary in Saved Report View
**File:** `app/views/reports/view.php`

The saved report already stores the `commentary` field from the form. No schema change needed — the commentary column already exists in `saved_reports` and is already rendered in `view.php` inside `.report-commentary`.

Since the commentary now contains richer auto-generated + custom text, just verify:
1. The `.report-commentary` div in view.php renders `nl2br(htmlspecialchars($report['commentary']))` so line breaks are preserved.
2. If commentary is empty, the section is hidden (already the case with the existing `!empty()` check).

### Step 10: Include Commentary in PDF Exports
**File:** `api/export.php`

When exporting a saved report to PDF:
1. The export already has access to the report row. Check if `$report['commentary']` is non-empty.
2. If present, render a styled commentary block in the PDF HTML before the data table — left border accent, slightly indented, with a "Analyst Commentary" heading.
3. Use `nl2br(htmlspecialchars())` to preserve line breaks in the PDF.

### Files Modified (Phase 2)
| File | Action |
|------|--------|
| `app/core/CommentaryGenerator.php` | **Create** — Helper class with static methods to generate plain-text insights per section |
| `app/controllers/ReportController.php` | **Modify** — Call CommentaryGenerator in `generate()`, pass result to view |
| `app/views/reports/generate.php` | **Modify** — Pre-fill textarea with auto-commentary, add hint label |
| `app/views/reports/view.php` | **Verify** — Ensure commentary renders with line breaks preserved |
| `api/export.php` | **Modify** — Include commentary section in PDF export HTML |

### Manual Testing (Phase 2)

#### Test 6: Auto-Commentary Pre-Fills on Generate Form
1. Visit `/reports/static`, click "Generate Report"
2. Confirm the commentary textarea is pre-filled with data-driven insights (network profile, device summary)
3. Repeat for `/reports/performance/generate` (Web Vitals, bottleneck) and `/reports/activity/generate` (engagement, clicks, scroll)

#### Test 7: Analyst Can Edit Auto-Commentary
1. On the generate form, modify the pre-filled text — delete a line, add a custom note
2. Submit the form with valid data
3. Open the saved report — confirm it shows the edited version, not the original auto-text

#### Test 8: Validation Error Preserves Edits
1. Edit the pre-filled commentary, then submit with an invalid ID range (e.g., from > to)
2. Confirm the form re-renders with the analyst's edited text, not the original auto-generated text

#### Test 9: PDF Export Includes Commentary
1. From a saved report that has commentary, click "Export"
2. Open the PDF — confirm commentary appears with proper formatting

#### Test 10: Backward Compatibility
1. Open a saved report generated before this feature
2. Confirm it still renders correctly — commentary section hidden if empty, no errors

---

## Phase 3: Section-Aware Charts for Saved Reports

### Problem
The saved report view (`app/views/reports/view.php`) renders a single generic chart: it picks the first numeric column from the snapshot and plots each row's value by record ID. This produces meaningless charts — for example, a static report charts `screen_width` by ID (a scatter of pixel values), while the live `/reports/static` page shows a meaningful "Sessions by Network Type" distribution bar chart, a memory doughnut, and a CPU cores bar.

The chart on saved reports doesn't match any of the purpose-built visualizations from the live report pages. The user-selected `chart_type` (bar/line/pie/doughnut) only controls the generic chart's type — it doesn't change _what_ is being charted.

### Goal
When viewing a saved report, render **the same style of charts** as the live report page for that section, computed from the snapshot data. The section is already stored in the `reports.section` column, so the view knows whether it's looking at static, performance, or activity data.

### Step 11: Compute Aggregates from Snapshot Data in the View
**File:** `app/views/reports/view.php`

Instead of (or in addition to) the generic first-numeric-column chart, add section-specific chart logic. Since the snapshot already contains the raw rows, we can aggregate them client-side or server-side:

**Server-side (PHP) approach** — compute aggregates from `$rows` in the view before rendering charts:

For **static** section snapshots:
- Group by `network_type` → count per type → bar chart (mirrors live "Sessions by Network Type")
- Group by `memory` → count per value → doughnut chart (mirrors live "Device Memory")
- Group by `cores` → count per value → bar chart (mirrors live "CPU Cores")

For **performance** section snapshots:
- Compute averages of `ttfb`, `lcp`, `dom_complete` → grouped bar chart (mirrors live "Session Timings")
- Compute averages of `lcp`, `cls`, `inp` → horizontal bar with threshold colors (mirrors live "Core Web Vitals")
- Compute averages of `dns_lookup`, `tcp_connect`, `tls_handshake`, `ttfb`, `download` → stacked horizontal bar (mirrors live "Network Timing Breakdown")

For **activity** section snapshots:
- Group by `type` → count per type → bar chart (mirrors live "Events by Type")
- Filter `type='click'`, group by `tag_name` → horizontal bar (mirrors live "Most Clicked Elements")
- Filter `type='scroll_final'`, bucket `scroll_depth` into quartiles → bar chart (mirrors live "Scroll Depth")

### Step 12: Render Section-Specific Charts
**File:** `app/views/reports/view.php`

Replace or supplement the current single generic chart with a `chart-col` that stacks multiple charts, matching the live page layout:

1. Use a `switch ($section)` block in the view to choose which charts to render.
2. Each section gets its own set of `<canvas>` elements and Chart.js instantiation code.
3. Keep the user-selected `chart_type` for the primary chart only (the first one). Secondary charts use fixed types that match the live pages (doughnut for memory, horizontal bar for clicked elements, etc.).
4. The generic fallback (first numeric column) is kept as a last resort for unknown sections or empty aggregates.

### Step 13: Update the Export Button
**File:** `app/views/reports/view.php`

The `exportWithChart()` call currently captures a single canvas (`reportChart`). With multiple charts, either:
- Capture the first/primary chart only (simplest, current behavior), OR
- Update to capture all canvases and send multiple images (more complex, optional)

Recommend: keep capturing the primary chart only for now. The PDF already has the data table and commentary.

### Files Modified (Phase 3)
| File | Action |
|------|--------|
| `app/views/reports/view.php` | **Modify** — Add section-specific chart aggregation and rendering, replacing the generic single-column chart |

### Manual Testing (Phase 3)

#### Test 11: Static Section Saved Report Charts
1. Generate a static report with a valid ID range
2. View the saved report — confirm it shows a "Sessions by Network Type" bar chart, "Device Memory" doughnut, and "CPU Cores" bar chart computed from the snapshot rows
3. Verify the charts match the live `/reports/static` page (same chart types, meaningful labels)

#### Test 12: Performance Section Saved Report Charts
1. Generate a performance report
2. View it — confirm session timings grouped bar, Core Web Vitals horizontal bar (with threshold colors), and Network Timing stacked bar are rendered from the snapshot data

#### Test 13: Activity Section Saved Report Charts
1. Generate an activity report
2. View it — confirm "Events by Type" bar, "Most Clicked Elements" horizontal bar, and "Scroll Depth" quartile bar are rendered

#### Test 14: Export Still Works
1. From a saved report with section-specific charts, click Export
2. Confirm the PDF renders with at least the primary chart image and data table

#### Test 15: Empty/Edge Cases
1. Generate a report with a very small ID range (e.g., 1 record)
2. Confirm charts either render with minimal data or gracefully show "Not enough data" — no JS errors
