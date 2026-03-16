# UI / CSS Improvement Notes

## Layout & Spacing

1. **Cards don't stretch to fill width** — `.cards` uses `flex-wrap` but cards have a fixed `min-width: 180px` with no `flex: 1`. On wide screens, cards cluster to the left instead of filling the row evenly. Add `flex: 1 1 180px` to `.card` so they grow to share available space.

2. **`.data-layout` chart column is a fixed 480px** — On very wide screens the chart stays narrow while the table stretches. Consider making the chart column proportional (e.g. `flex: 0 1 40%`) so both sides scale together.

3. **No max-width on tables inside `.data-layout`** — Wide tables push the flex container and can cause horizontal overflow on medium screens. Adding `overflow-x: auto` on the `.data-layout` container (or `min-width: 0` on the table-wrap parent div in `view.php`) would prevent this.

4. **`main` padding drops to 1rem on sides** — At very large viewport widths this looks fine, but on tablet-sized screens (~1000px) the 1rem side padding feels tight. Consider a responsive bump, e.g. `padding: 1.5rem clamp(1rem, 2vw, 2rem)`.

## Navigation

5. **Nav has no responsive/mobile treatment** — At narrow widths the nav links wrap awkwardly because the flex container doesn't handle overflow. A hamburger menu or horizontal scroll (`overflow-x: auto; white-space: nowrap`) would improve mobile usability.

6. **Active link indicator uses `border-bottom` with `padding-bottom`** — This shifts the link text up slightly compared to inactive links. Using a `box-shadow` inset or `border-bottom` on all links (transparent for inactive) would keep alignment consistent.

7. **Nav logout `!important` overrides** — `.nav-logout` uses `!important` for both `margin-left` and `color`. This can be avoided by increasing specificity (e.g. `nav .nav-logout`) or restructuring the nav with a spacer element.

## Typography & Readability

8. **Global `h2` margin applies everywhere** — `h2 { margin: 1.75rem 0 0.75rem }` affects headings inside cards, charts, and forms where top margin is unwanted. Scope it to `main > h2` or use more targeted selectors.

9. **Table font size (0.85rem) may be too small on data-heavy pages** — The performance table has 15 columns; at small font sizes with `white-space: nowrap` on headers, columns get cramped. Consider `0.8rem` for headers only and `0.875rem` for cell data, or allow header text to wrap on wide tables.

## Visual Polish

10. **No focus/active states on interactive elements** — Buttons (`.btn`, `.btn-page`, login button) and nav links have `:hover` but no `:focus-visible` styles. This hurts keyboard accessibility. Add a visible focus ring, e.g. `outline: 2px solid #60a5fa; outline-offset: 2px`.

11. **No transition on hover states** — Button and link color changes are instant. Adding `transition: background-color 0.15s ease` makes interactions feel smoother.

12. **Inconsistent shadow values** — Most elements use `rgba(0,0,0,.1)` but login/form wrappers use `rgba(0,0,0,.15)`. Standardize to one value or use CSS custom properties for the shadow.

13. **Delete button color (`#dc2626`) has no focus ring** — The destructive action button should have a distinct focus indicator (e.g. red outline) so keyboard users can identify it.

## Component-Specific

14. **`.chart-wrap` has `max-width: none`** — After the recent change, charts can stretch extremely wide on large monitors, making bar charts hard to read (bars become very thin). Consider capping at something like `max-width: 100%` within a proportional flex column rather than removing the cap entirely.

15. **Pagination could use more visual weight** — The pagination sits directly under content with only `margin-top: 1.25rem`. Adding a subtle top border or extra spacing would better separate it from the data above.

16. **Report meta info on view page has no styling** — The `.report-meta` and `.report-commentary` classes are used in `reports/view.php` but have no CSS definitions. They render as unstyled paragraphs. Add styles for visual hierarchy (muted color, smaller font for meta; bordered/background box for commentary).

## Forms

17. **Form wrapper max-width (360px) is very narrow** — For the report generation form, a wider container (e.g. 480px) would give more room for longer field labels and textareas.

18. **Nested CSS selectors inside `form { }` rely on CSS nesting** — Browser support for native CSS nesting is recent (2023+). If older browser support is needed, flatten these into standard selectors.

## Responsive

19. **Only one breakpoint (900px)** — The entire stylesheet has a single `@media` query. Additional breakpoints would help:
    - `@media (max-width: 600px)`: stack cards vertically, reduce `h1` size, increase table font for touch targets
    - `@media (min-width: 1400px)`: dashboard charts grid could use `minmax(400px, 1fr)` for better use of wide screens

20. **Dashboard `.charts-grid` minmax(320px)** — On screens just above 640px, this creates two very narrow chart columns. Bumping to `minmax(380px, 1fr)` would trigger single-column layout sooner and avoid cramped charts.
