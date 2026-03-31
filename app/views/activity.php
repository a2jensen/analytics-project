<?php $pageTitle = 'Activity Data — Analytics'; require __DIR__ . '/layout/header.php'; ?>

<h1>Activity Data</h1>
<?php if ($_SESSION['role'] === 'super_admin' || ($_SESSION['role'] === 'analyst' && Auth::canAccessSection('activity'))): ?>
<a href="/reports/activity/new" class="btn">Generate Report</a>
<button class="btn" onclick="exportWithChart('activity', null, 'activityChart')">Export</button>
<?php endif; ?>
<p><?= count($rows) ?> of <?= $total ?> records</p>

<?php require __DIR__ . '/layout/pagination.php'; ?>

<?php if ($total > 0): ?>
<div class="analyst-commentary">
    <h2>Analyst Commentary</h2>

    <?php
    $totalEvents = array_sum($typeCounts);
    $clickCount = $typeCounts['click'] ?? 0;
    $scrollCount = ($typeCounts['scroll_depth'] ?? 0) + ($typeCounts['scroll_final'] ?? 0);
    $exitCount = $typeCounts['page_exit'] ?? 0;
    $keyCount = $typeCounts['keyboard_activity'] ?? 0;

    arsort($typeCounts);
    $topType = array_key_first($typeCounts);
    $topTypeCount = $typeCounts[$topType];
    $topTypePct = $totalEvents > 0 ? round(($topTypeCount / $totalEvents) * 100) : 0;

    $clickPct = $totalEvents > 0 ? round(($clickCount / $totalEvents) * 100) : 0;
    $scrollPct = $totalEvents > 0 ? round(($scrollCount / $totalEvents) * 100) : 0;
    ?>

    <div class="insight">
        <span class="insight-label">User Engagement:</span>
        <strong><?= str_replace('_', ' ', ucfirst($topType)) ?></strong> events dominate at <?= $topTypePct ?>% of all activity (<?= number_format($topTypeCount) ?> of <?= number_format($totalEvents) ?> events).
        <?php if ($clickCount > 0 && $scrollCount > 0): ?>
            Clicks account for <?= $clickPct ?>% and scroll events for <?= $scrollPct ?>%.
            <?php if ($scrollPct > $clickPct * 2): ?>
                The high scroll-to-click ratio suggests users are consuming content passively rather than actively engaging with interactive elements.
            <?php elseif ($clickPct > $scrollPct): ?>
                Clicks outpace scrolling, indicating an interaction-heavy experience — users are actively engaging with page elements.
            <?php else: ?>
                Click and scroll activity are roughly balanced, suggesting a healthy mix of reading and interaction.
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($clickedElements)): ?>
    <?php
    $topEl = $clickedElements[0];
    $topTag = strtoupper($topEl['tag_name']);
    $totalClicks = array_sum(array_map(fn($r) => (int)$r['count'], $clickedElements));
    $topElPct = $totalClicks > 0 ? round(((int)$topEl['count'] / $totalClicks) * 100) : 0;
    ?>
    <div class="insight">
        <span class="insight-label">Click Targets:</span>
        <strong><?= $topTag ?></strong> elements receive the most clicks (<?= $topElPct ?>% of all clicks).
        <?php if (in_array(strtolower($topEl['tag_name']), ['img', 'image'])): ?>
            Users are clicking images frequently — ensure all images have appropriate link wrappers or lightbox functionality.
        <?php elseif (in_array(strtolower($topEl['tag_name']), ['a', 'button'])): ?>
            Primary click targets are interactive elements as expected — navigation and call-to-action elements are driving engagement.
        <?php elseif (in_array(strtolower($topEl['tag_name']), ['div', 'span', 'p'])): ?>
            Users are clicking non-interactive elements (<?= $topTag ?>), which may indicate missing clickable affordances or unclear UI.
        <?php endif; ?>
        <?php if (count($clickedElements) > 1): ?>
            <?= count($clickedElements) ?> distinct element types received clicks across the tracked sessions.
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($scrollDepthDist)): ?>
    <?php
    $scrollTotal = array_sum(array_map(fn($r) => (int)$r['count'], $scrollDepthDist));
    $deepScrolls = 0;
    $shallowScrolls = 0;
    foreach ($scrollDepthDist as $s) {
        if (str_starts_with($s['depth_range'], '76') || str_starts_with($s['depth_range'], '100')) {
            $deepScrolls += (int)$s['count'];
        }
        if (str_starts_with($s['depth_range'], '0')) {
            $shallowScrolls += (int)$s['count'];
        }
    }
    $deepPct = $scrollTotal > 0 ? round(($deepScrolls / $scrollTotal) * 100) : 0;
    $shallowPct = $scrollTotal > 0 ? round(($shallowScrolls / $scrollTotal) * 100) : 0;
    ?>
    <div class="insight">
        <span class="insight-label">Scroll Depth:</span>
        <?= $deepPct ?>% of sessions reach the bottom quartile (76–100%) of the page<?= $deepPct >= 50 ? ', indicating <span class="good">strong content engagement</span>' : '' ?>.
        <?php if ($shallowPct > 30): ?>
            However, <?= $shallowPct ?>% bounce in the 0–25% range — <span class="warn">consider improving above-the-fold content</span> to retain more visitors.
        <?php elseif ($shallowPct <= 15): ?>
            Only <?= $shallowPct ?>% leave within the first quartile — users are consistently engaging beyond the fold.
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="data-layout">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Session ID</th>
                    <th>URL</th>
                    <th>Type</th>
                    <th>X</th>
                    <th>Y</th>
                    <th>Mouse Button</th>
                    <th>Key</th>
                    <th>Idle Duration</th>
                    <th>Time on Page (s)</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td title="<?= htmlspecialchars($row['session_id']) ?>"><?= htmlspecialchars(substr($row['session_id'], 0, 12)) ?>…</td>
                    <td><?= htmlspecialchars($row['url']) ?></td>
                    <td><?= htmlspecialchars($row['type']) ?></td>
                    <td><?= $row['x'] ?? '—' ?></td>
                    <td><?= $row['y'] ?? '—' ?></td>
                    <td><?= $row['mouse_button'] ?? '—' ?></td>
                    <td><?= htmlspecialchars($row['key_name'] ?? '—') ?></td>
                    <td><?= $row['idle_duration'] ?? '—' ?></td>
                    <td><?= $row['time_on_page'] ?? '—' ?></td>
                    <td><?= htmlspecialchars($row['event_timestamp'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="chart-col">
        <h2>Events by Type</h2>
        <?php if (!empty($typeCounts)): ?>
        <div class="chart-wrap">
            <canvas id="activityChart"></canvas>
            <noscript><p>Charts require JavaScript to display. Please enable JavaScript in your browser.</p></noscript>
        </div>
        <script>


        new Chart(document.getElementById('activityChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($typeCounts)) ?>,
                datasets: [{
                    label: 'Total Events',
                    data: <?= json_encode(array_values($typeCounts)) ?>,
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.7)',
                        'rgba(16, 185, 129, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(239, 68, 68, 0.7)',
                        'rgba(139, 92, 246, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: { display: true, text: 'User Interaction Events by Type', font: { size: 14 } },
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => ctx.parsed.y.toLocaleString() + ' ' + ctx.label + ' event' + (ctx.parsed.y !== 1 ? 's' : '') } }
                },
                scales: {
                    x: { title: { display: true, text: 'Event Type' } },
                    y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Count' } }
                }
            }
        });
        </script>
        <p class="chart-desc">Breakdown of all captured user interaction events across sessions. Each bar shows the total count for a distinct event type (e.g. click, scroll, keydown, idle). Identifies which interactions are most common, useful for understanding user engagement patterns.</p>
        <?php else: ?>
            <p>No data available yet.</p>
        <?php endif; ?>

        <?php if (!empty($clickedElements)): ?>
        <h2>Most Clicked Elements</h2>
        <div class="chart-wrap">
            <canvas id="clickedChart"></canvas>
        </div>
        <p class="chart-desc">Shows which HTML elements users click most frequently. Reveals user intent — if images are clicked more than buttons, users may expect them to be links. Useful for UX optimization.</p>
        <?php endif; ?>

        <?php if (!empty($scrollDepthDist)): ?>
        <h2>Scroll Depth Distribution</h2>
        <div class="chart-wrap">
            <canvas id="scrollChart"></canvas>
        </div>
        <p class="chart-desc">How far users scroll before leaving the page, bucketed into quartiles. High counts in 0–25% indicate users bouncing early. High counts in 76–100% mean users are reading the full page.</p>
        <?php endif; ?>
    </div>
</div>

<script>
<?php if (!empty($clickedElements)): ?>
new Chart(document.getElementById('clickedChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($r) => strtoupper($r['tag_name']), $clickedElements)) ?>,
        datasets: [{
            label: 'Clicks',
            data: <?= json_encode(array_map(fn($r) => (int)$r['count'], $clickedElements)) ?>,
            backgroundColor: 'rgba(59, 130, 246, 0.7)',
            borderColor: 'rgba(59, 130, 246, 1)',
            borderWidth: 1
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: {
            title: { display: true, text: 'Top Clicked HTML Elements', font: { size: 14 } },
            legend: { display: false },
            tooltip: { callbacks: { label: function(ctx) { return ctx.parsed.x + ' click' + (ctx.parsed.x !== 1 ? 's' : ''); } } }
        },
        scales: {
            x: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Click Count' } },
            y: { title: { display: true, text: 'Element Tag' } }
        }
    }
});
<?php endif; ?>

<?php if (!empty($scrollDepthDist)): ?>
new Chart(document.getElementById('scrollChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($scrollDepthDist, 'depth_range')) ?>,
        datasets: [{
            label: 'Sessions',
            data: <?= json_encode(array_map(fn($r) => (int)$r['count'], $scrollDepthDist)) ?>,
            backgroundColor: ['rgba(239,68,68,0.7)', 'rgba(245,158,11,0.7)', 'rgba(59,130,246,0.7)', 'rgba(16,185,129,0.7)'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: { display: true, text: 'Final Scroll Depth Distribution', font: { size: 14 } },
            legend: { display: false },
            tooltip: { callbacks: { label: function(ctx) { return ctx.parsed.y + ' session' + (ctx.parsed.y !== 1 ? 's' : ''); } } }
        },
        scales: {
            x: { title: { display: true, text: 'Scroll Depth Range' } },
            y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Sessions' } }
        }
    }
});
<?php endif; ?>
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
