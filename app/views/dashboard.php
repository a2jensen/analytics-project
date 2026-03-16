<?php $pageTitle = 'Dashboard — Analytics'; require __DIR__ . '/layout/header.php'; ?>

<h1>Dashboard</h1>

<div class="cards">
    <?php if (in_array('static', $allowedSections)): ?>
    <div class="card">
        <div class="card-label">Static Records</div>
        <div class="card-value"><?= $counts['static_data'] ?></div>
    </div>
    <?php endif; ?>
    <?php if (in_array('performance', $allowedSections)): ?>
    <div class="card">
        <div class="card-label">Performance Records</div>
        <div class="card-value"><?= $counts['performance_data'] ?></div>
    </div>
    <?php endif; ?>
    <?php if (in_array('activity', $allowedSections)): ?>
    <div class="card">
        <div class="card-label">Activity Events</div>
        <div class="card-value"><?= $counts['activity_data'] ?></div>
    </div>
    <?php endif; ?>
</div>

<div class="charts-grid">

    <?php if (in_array('static', $allowedSections)): ?>
    <div>
        <h2>Static — Sessions by Network Type</h2>
        <div class="chart-wrap">
            <canvas id="networkChart"></canvas>
            <noscript><p>Charts require JavaScript to display. Please enable JavaScript in your browser.</p></noscript>
        </div>
        <p class="chart-desc">Shows how many sessions were recorded on each network connection type (e.g. 4g, wifi). Collected once per pageview from the Network Information API.</p>
    </div>
    <?php endif; ?>

    <?php if (in_array('performance', $allowedSections)): ?>
    <div>
        <h2>Performance — Avg Timing Metrics (ms)</h2>
        <div class="chart-wrap">
            <canvas id="perfAvgChart"></canvas>
            <noscript><p>Charts require JavaScript to display. Please enable JavaScript in your browser.</p></noscript>
        </div>
        <p class="chart-desc">Averages of key page load timings across all recorded sessions. TTFB measures server response speed, DOM Complete and Total Load reflect rendering time, and LCP (Largest Contentful Paint) is a Core Web Vital indicating perceived load speed.</p>
    </div>
    <?php endif; ?>

    <?php if (in_array('activity', $allowedSections)): ?>
    <div>
        <h2>Activity — Events by Type</h2>
        <div class="chart-wrap">
            <canvas id="eventChart"></canvas>
            <noscript><p>Charts require JavaScript to display. Please enable JavaScript in your browser.</p></noscript>
        </div>
        <p class="chart-desc">Breakdown of all user interaction events captured during sessions. Each bar represents a distinct event type (e.g. clicks, scrolls, keyboard activity) showing which interactions are most frequent across all users.</p>
    </div>
    <?php endif; ?>

</div>

<?php if (in_array('activity', $allowedSections)): ?>
<div style="margin-top:1.5rem;">
    <h2>Activity — Hourly Breakdown</h2>
    <div class="chart-wrap">
        <canvas id="heatmapChart"></canvas>
        <noscript><p>Charts require JavaScript to display. Please enable JavaScript in your browser.</p></noscript>
    </div>
    <p class="chart-desc">Stacked bar chart showing total user activity events per hour of day (0–23), broken down by event type. Taller bars indicate peak usage hours. Each color segment represents a different interaction type.</p>
</div>
<?php endif; ?>

<script>
// array_map with arrow functions extracts a single column from each query result array into a flat
// array — Chart.js expects separate labels and data arrays, not an array of objects.
// $perfAvgs values are cast to float before round() because PDO returns numeric columns as strings
// in FETCH_ASSOC mode.

<?php if (in_array('static', $allowedSections)): ?>
// Static: network type breakdown
new Chart(document.getElementById('networkChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($r) => $r['network_type'] ?? 'unknown', $networkTypes)) ?>,
        datasets: [{
            label: 'Number of Sessions',
            data: <?= json_encode(array_map(fn($r) => (int)$r['count'], $networkTypes)) ?>,
            backgroundColor: 'rgba(16, 185, 129, 0.7)',
            borderColor: 'rgba(16, 185, 129, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: { display: true, text: 'Session Count by Network Type', font: { size: 14 } },
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ctx.parsed.y + ' session' + (ctx.parsed.y !== 1 ? 's' : '') } }
        },
        scales: {
            x: { title: { display: true, text: 'Network Type' } },
            y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Sessions' } }
        }
    }
});
<?php endif; ?>

<?php if (in_array('performance', $allowedSections)): ?>
// Performance: average timing metrics
new Chart(document.getElementById('perfAvgChart'), {
    type: 'bar',
    data: {
        labels: ['TTFB', 'DOM Complete', 'LCP', 'Total Load'],
        datasets: [{
            label: 'Average (ms)',
            data: [
                <?= round((float)$perfAvgs['ttfb'], 1) ?>,
                <?= round((float)$perfAvgs['dom_complete'], 1) ?>,
                <?= round((float)$perfAvgs['lcp'], 1) ?>,
                <?= round((float)$perfAvgs['total_load_time'], 1) ?>
            ],
            backgroundColor: [
                'rgba(59, 130, 246, 0.7)',
                'rgba(16, 185, 129, 0.7)',
                'rgba(245, 158, 11, 0.7)',
                'rgba(139, 92, 246, 0.7)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: { display: true, text: 'Average Page Load Timings Across All Sessions', font: { size: 14 } },
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ctx.label + ': ' + ctx.parsed.y.toFixed(1) + ' ms average' } }
        },
        scales: {
            x: { title: { display: true, text: 'Timing Metric' } },
            y: { beginAtZero: true, title: { display: true, text: 'Time (ms)' } }
        }
    }
});
<?php endif; ?>

<?php if (in_array('activity', $allowedSections)): ?>
// Activity: event type breakdown
new Chart(document.getElementById('eventChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($eventTypes, 'type')) ?>,
        datasets: [{
            label: 'Total Events',
            data: <?= json_encode(array_map('intval', array_column($eventTypes, 'count'))) ?>,
            backgroundColor: 'rgba(239, 68, 68, 0.7)',
            borderColor: 'rgba(239, 68, 68, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: { display: true, text: 'Total User Interaction Events by Type', font: { size: 14 } },
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ctx.parsed.y.toLocaleString() + ' ' + ctx.label + ' event' + (ctx.parsed.y !== 1 ? 's' : '') } }
        },
        scales: {
            x: { title: { display: true, text: 'Event Type' } },
            y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Count' } }
        }
    }
});
<?php endif; ?>

<?php if (in_array('activity', $allowedSections)): ?>
// Activity: hourly stacked bar
(function() {
    var raw = <?= json_encode($heatmapData) ?>;
    var hours = [];
    for (var i = 0; i < 24; i++) hours.push(i + ':00');

    // Collect unique types and build per-type arrays of 24 values
    var typeMap = {};
    raw.forEach(function(r) {
        if (!typeMap[r.type]) typeMap[r.type] = new Array(24).fill(0);
        typeMap[r.type][parseInt(r.hour)] = parseInt(r.count);
    });

    var colors = {
        click: 'rgba(59, 130, 246, 0.8)',
        scroll_depth: 'rgba(16, 185, 129, 0.8)',
        scroll_final: 'rgba(20, 184, 166, 0.8)',
        keyboard_activity: 'rgba(245, 158, 11, 0.8)',
        page_exit: 'rgba(239, 68, 68, 0.8)'
    };
    var fallback = 'rgba(139, 92, 246, 0.8)';

    var datasets = Object.keys(typeMap).map(function(type) {
        return {
            label: type.replace(/_/g, ' '),
            data: typeMap[type],
            backgroundColor: colors[type] || fallback
        };
    });

    new Chart(document.getElementById('heatmapChart'), {
        type: 'bar',
        data: { labels: hours, datasets: datasets },
        options: {
            responsive: true,
            plugins: {
                title: { display: true, text: 'User Activity by Hour of Day', font: { size: 14 } },
                legend: { display: true, position: 'top' },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ctx.dataset.label + ': ' + ctx.parsed.y + ' event' + (ctx.parsed.y !== 1 ? 's' : '');
                        }
                    }
                }
            },
            scales: {
                x: { stacked: true, title: { display: true, text: 'Hour of Day' } },
                y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Events' } }
            }
        }
    });
})();
<?php endif; ?>
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
