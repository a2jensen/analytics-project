# Plan: Dark Mode Toggle

## Overview
Add a user-togglable dark/light theme that persists via `localStorage`. This improves accessibility, reduces eye strain during extended use, and demonstrates modern UX practices.

## Current State
- The app has a single light theme with a dark navy nav bar
- No theme switching capability
- CSS is in a single `style.css` file
- No JavaScript-driven UI preferences

## Implementation

### Step 1: Define Dark Mode CSS Variables
**File:** `style.css` (or equivalent main stylesheet)

Add CSS custom properties (variables) at the top of the file for themeable colors:

```css
:root {
    --bg-primary: #ffffff;
    --bg-secondary: #f4f4f4;
    --bg-card: #ffffff;
    --text-primary: #333333;
    --text-secondary: #666666;
    --border-color: #dddddd;
    --nav-bg: #2c3e50;
    --table-stripe: #f9f9f9;
    --input-bg: #ffffff;
    --shadow: rgba(0, 0, 0, 0.1);
}

[data-theme="dark"] {
    --bg-primary: #1a1a2e;
    --bg-secondary: #16213e;
    --bg-card: #0f3460;
    --text-primary: #e0e0e0;
    --text-secondary: #b0b0b0;
    --border-color: #2a2a4a;
    --nav-bg: #0f0f23;
    --table-stripe: #1a1a3e;
    --input-bg: #16213e;
    --shadow: rgba(0, 0, 0, 0.3);
}
```

### Step 2: Replace Hardcoded Colors with Variables
**File:** `style.css`

Go through the existing stylesheet and replace hardcoded color values with the corresponding CSS variables. Key areas:
- `body` background → `var(--bg-primary)`
- Card/container backgrounds → `var(--bg-card)`
- Text color → `var(--text-primary)`
- Table striping → `var(--table-stripe)`
- Border colors → `var(--border-color)`
- Navigation → `var(--nav-bg)`
- Form inputs → `var(--input-bg)`

### Step 3: Add Theme Toggle Button
**File:** `app/views/layout/header.php`

Add a toggle button in the nav bar (right side):
```html
<button id="theme-toggle" class="theme-toggle" title="Toggle dark mode" aria-label="Toggle dark mode">
    <span class="theme-icon">☀️</span>
</button>
```

Style the button to be minimal (no background, just the icon, cursor pointer).

### Step 4: Add Theme Toggle JavaScript
**File:** `app/views/layout/header.php` (or a separate `theme.js` if preferred)

Add a small inline script (or external file) at the end of the header:

```javascript
(function() {
    const toggle = document.getElementById('theme-toggle');
    const icon = toggle.querySelector('.theme-icon');
    const savedTheme = localStorage.getItem('theme') || 'light';

    // Apply saved theme on page load
    if (savedTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        icon.textContent = '🌙';
    }

    toggle.addEventListener('click', function() {
        const current = document.documentElement.getAttribute('data-theme');
        if (current === 'dark') {
            document.documentElement.removeAttribute('data-theme');
            localStorage.setItem('theme', 'light');
            icon.textContent = '☀️';
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
            icon.textContent = '🌙';
        }
    });
})();
```

### Step 5: Prevent Flash of Wrong Theme (FOUC)
**File:** `app/views/layout/header.php`

Add a tiny inline script in the `<head>` (before CSS loads) to apply the theme immediately:
```html
<script>
    (function(){
        var t = localStorage.getItem('theme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    })();
</script>
```

This prevents the flash of the light theme when a dark-mode user loads a page.

### Step 6: Update Chart.js Colors
**File:** Any view files that create Chart.js charts

Chart.js charts use hardcoded colors. Update them to read from CSS variables or use a JS helper:
```javascript
const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
const textColor = isDark ? '#e0e0e0' : '#333333';
const gridColor = isDark ? '#2a2a4a' : '#dddddd';
```

Apply these to chart options for axis labels, grid lines, and legends.

## Files Created/Modified
| File | Action |
|------|--------|
| `style.css` | **Modify** — Add CSS variables for both themes, replace hardcoded colors |
| `app/views/layout/header.php` | **Modify** — Add toggle button, FOUC prevention script, toggle logic |
| Chart view files | **Modify** — Update Chart.js color options for dark mode |

## Manual Testing

### Test 1: Toggle Works
1. Log in to the application
2. Find the theme toggle button in the nav bar
3. Click it — confirm the page switches to dark mode (dark backgrounds, light text)
4. Click again — confirm it switches back to light mode

### Test 2: Preference Persists
1. Switch to dark mode
2. Navigate to a different page (e.g., `/reports/static`)
3. Confirm dark mode is still active
4. Close the browser tab, reopen the app
5. Confirm dark mode is still active (saved in localStorage)

### Test 3: No Flash on Page Load
1. Set dark mode and refresh the page
2. Confirm there is NO brief flash of light theme before dark loads
3. The page should render in dark mode immediately

### Test 4: All Pages Look Correct in Dark Mode
1. Visit each page in dark mode:
   - `/dashboard` — cards, charts readable
   - `/reports/static` — table rows visible, striping works
   - `/reports/performance` — same
   - `/reports/activity` — same
   - `/users` — forms, buttons styled correctly
   - `/reports/saved` — list and detail views readable
2. Confirm no white/unthemed elements remain (no hardcoded colors missed)

### Test 5: Charts Readable in Dark Mode
1. View `/dashboard` in dark mode
2. Confirm Chart.js charts have light-colored text labels and visible grid lines
3. Confirm chart colors (bars, lines) contrast well against the dark background

### Test 6: Forms and Inputs
1. In dark mode, navigate to `/users/create`
2. Confirm form inputs have dark backgrounds and light text
3. Confirm buttons are visible and clickable
4. Confirm error/success messages are readable

### Test 7: Login Page
1. Log out
2. Confirm the login page respects the saved theme preference
3. Toggle theme on the login page (if toggle is visible) — confirm it works
