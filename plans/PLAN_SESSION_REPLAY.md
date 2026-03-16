# Plan: Session Activity Replay

## Overview
Add a visual session replay page where analysts can select a session and watch a timeline-based playback of that user's activity (clicks, scrolls, keyboard events, idle periods, page exits). The replay renders events chronologically on a visual representation of the page, showing where and when interactions happened.

This is NOT a pixel-perfect screen recording — it's an **event-driven timeline replay** that visualizes the collected activity_data as an animated sequence, similar to heatmap tools like Hotjar/FullStory but built from our own beacon data.

## Data Available (activity_data)
Each event has:
- `session_id` — groups events into a user session
- `url` — which page the event occurred on
- `type` — `click`, `scroll_depth`, `scroll_final`, `keyboard_activity`, `page_exit`
- `x`, `y` — pixel coordinates (clicks)
- `scroll_depth` — percentage scrolled (scroll events)
- `tag_name`, `element_text`, `selector` — what element was interacted with
- `key_name` — which key was pressed
- `idle_duration`, `idle_end` — how long the user was idle
- `time_on_page` — total time spent (page_exit events)
- `event_timestamp` — when the event happened

## Implementation

### Step 1: Create Session Replay Model
**File:** `app/models/SessionReplayModel.php`

Methods:
- `getSessionList(int $limit, int $offset): array` — Return distinct sessions with event count, first/last timestamp, URL, and total time on page. Query:
  ```sql
  SELECT session_id, url, COUNT(*) as event_count,
         MIN(event_timestamp) as session_start,
         MAX(event_timestamp) as session_end,
         MAX(time_on_page) as time_on_page
  FROM activity_data
  GROUP BY session_id, url
  ORDER BY session_start DESC
  LIMIT ? OFFSET ?
  ```
- `countSessions(): int` — Count distinct session/url pairs for pagination
- `getSessionEvents(string $sessionId): array` — Return all events for a session ordered by timestamp:
  ```sql
  SELECT * FROM activity_data
  WHERE session_id = ?
  ORDER BY event_timestamp ASC, id ASC
  ```

### Step 2: Create Session Replay Controller
**File:** `app/controllers/SessionReplayController.php`

Two methods:
- `index()` — List all sessions with pagination (session picker). Auth required, super_admin or analyst with activity section access.
- `show()` — Render the replay view for a specific session. Read `$_GET['session_id']`, fetch all events via the model, pass to view as JSON for the JS player.

### Step 3: Create Session List View (Session Picker)
**File:** `app/views/replay/index.php`

A paginated table of sessions:
| Session ID | URL | Events | Start | End | Duration | Action |
|---|---|---|---|---|---|---|
| `d7v1mf...` | test.angelo-j.xyz | 62 | 2026-02-28 06:13 | 2026-02-28 06:35 | 21m 45s | [Replay] |

Each row has a "Replay" button linking to `/replay?session_id=XXX`.

### Step 4: Create Replay Player View
**File:** `app/views/replay/show.php`

The replay page has three sections:

**A. Viewport Canvas (main area)**
- A bordered rectangle representing the user's viewport (use static_data screen dimensions if available, otherwise default 1920x1080)
- Scaled down to fit the browser window (e.g., 60% scale)
- Events are animated onto this canvas:
  - **Clicks** — A ripple/pulse circle appears at the (x, y) coordinates with a label showing the element clicked (`tag_name: element_text`)
  - **Scroll** — A scroll indicator bar on the right side moves to show scroll depth percentage
  - **Keyboard** — A small toast/badge appears showing the key pressed
  - **Idle** — The canvas dims/greys out during idle periods
  - **Page exit** — A "Session ended" overlay

**B. Timeline Bar (bottom)**
- A horizontal progress bar spanning the session duration
- Event markers (dots/ticks) on the timeline at their relative timestamps
- Color-coded by type: blue=click, green=scroll, orange=keyboard, red=page_exit, grey=idle
- Playhead that moves as the replay progresses
- Click anywhere on the timeline to jump to that point

**C. Controls & Event Log (sidebar or top bar)**
- Play/Pause button
- Speed control: 1x, 2x, 5x, 10x
- Current timestamp display
- Event log panel below controls — scrolling list of events as they fire, showing: timestamp, type, and details (coordinates, element, key, etc.). Current event is highlighted.

### Step 5: Replay Player JavaScript
**File:** Inline in `app/views/replay/show.php` (or `public/js/replay.js` if large)

The JS replay engine:
1. Receive events array as JSON from PHP (embedded via `json_encode`)
2. Calculate session duration from first to last event timestamp
3. On Play: start a `requestAnimationFrame` loop that advances a virtual clock
4. As the clock passes each event's timestamp, render the event on the canvas:
   - **Click**: Draw a circle at (x, y) that fades out after 1.5 seconds. Show a tooltip with the selector/element_text.
   - **Scroll**: Animate the scroll indicator to the new `scroll_depth` percentage.
   - **Keyboard**: Show a key badge that fades after 1 second.
   - **Idle**: Grey overlay with "Idle for Xs" text. In fast playback, compress idle periods.
   - **Page exit**: Show "Session ended — X seconds on page" overlay.
5. Speed multiplier scales the clock advancement rate
6. Timeline click: set the virtual clock to the clicked position, re-render all events up to that point

### Step 6: Cross-Reference with Static Data
When loading a session for replay, also query `static_data` for the same `session_id` to get:
- `screen_width`, `screen_height` — set canvas dimensions
- `viewport_width`, `viewport_height` — set the visible area
- `user_agent` — display in session info header
- `network_type` — display in session info

Add a method to `SessionReplayModel`:
- `getSessionMeta(string $sessionId): ?array` — Query static_data for device info

### Step 7: Register Routes
**File:** `app/core/Router.php`

- `GET /replay` → `SessionReplayController::index()` (session list)
- `GET /replay/show` → `SessionReplayController::show()` (replay player, requires `?session_id=`)

### Step 8: Add Nav Link
**File:** `app/views/layout/header.php`

Add "Replay" link in the nav for super_admin and analysts with activity access, between Activity and Saved Reports.

## Files Created/Modified
| File | Action |
|------|--------|
| `app/models/SessionReplayModel.php` | **Create** — Session listing and event queries |
| `app/controllers/SessionReplayController.php` | **Create** — Index and show methods |
| `app/views/replay/index.php` | **Create** — Session picker with pagination |
| `app/views/replay/show.php` | **Create** — Replay player with canvas, timeline, controls |
| `app/core/Router.php` | **Modify** — Add `/replay` and `/replay/show` routes |
| `app/views/layout/header.php` | **Modify** — Add Replay nav link |
| `public/css/style.css` | **Modify** — Styles for replay canvas, timeline, controls |

## Manual Testing

### Test 1: Session List Loads
1. Log in as `admin`
2. Navigate to `/replay`
3. Confirm a paginated table of sessions appears with session ID, URL, event count, timestamps, and duration
4. Confirm sessions are ordered by most recent first

### Test 2: Replay Player Loads
1. Click "Replay" on a session with many events (e.g., `d7v1mfshzxwmm5rx63l` with 62 events)
2. Confirm the replay page loads with: viewport canvas, timeline bar with event markers, controls, and event log

### Test 3: Playback Works
1. Click Play
2. Confirm events animate in chronological order on the canvas
3. Confirm clicks appear as circles at the correct x/y positions
4. Confirm scroll events move the scroll indicator
5. Confirm the timeline playhead advances
6. Confirm the event log scrolls to show each event as it fires

### Test 4: Speed Control
1. Set speed to 10x
2. Confirm replay runs faster — events fire more rapidly
3. Set to 1x and confirm it slows back down

### Test 5: Timeline Scrubbing
1. During playback, click on a point in the middle of the timeline
2. Confirm playback jumps to that point and all prior events are rendered on the canvas

### Test 6: Session Metadata
1. Open a replay where the session also has static_data
2. Confirm the canvas dimensions match the user's screen size
3. Confirm user agent and network type are displayed in the session info header

### Test 7: Access Control
1. Log in as `viewer1`
2. Try to visit `/replay` → confirm 403
3. Log in as `sally` (activity access) → confirm `/replay` works
4. Log in as `sam` (all access) → confirm `/replay` works

### Test 8: Empty/Short Sessions
1. Find a session with only 1-2 events
2. Open the replay — confirm it doesn't crash, shows the events, and displays "Session ended" appropriately
