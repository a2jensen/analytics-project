<?php $pageTitle = 'Static Data — Analytics'; require __DIR__ . '/layout/header.php'; ?>

<h1>Static Data</h1>
<?php if ($_SESSION['role'] === 'super_admin' || ($_SESSION['role'] === 'analyst' && Auth::canAccessSection('static'))): ?>
<a href="/reports/static/new" class="btn">Generate Report</a>
<button class="btn" onclick="exportWithChart('static', null, 'networkChart')">Export</button>
<?php endif; ?>
<p><?= count($rows) ?> of <?= $total ?> records</p>

<?php require __DIR__ . '/layout/pagination.php'; ?>

<?php if ($total > 0): ?>
<div class="analyst-commentary">
    <h2>Analyst Commentary</h2>

    <?php
    // Network profile
    $topNet = $networkTypes[0] ?? null;
    if ($topNet) {
        $netTotal = array_sum(array_map(fn($r) => (int)$r['count'], $networkTypes));
        $topPct = round(($topNet['count'] / $netTotal) * 100);
        $netName = $topNet['network_type'] ?? 'unknown';
    }
    // Memory profile
    $memTotal = array_sum(array_map(fn($r) => (int)$r['count'], $memoryDist));
    $topMem = null;
    $topMemPct = 0;
    foreach ($memoryDist as $m) {
        if ((int)$m['count'] > $topMemPct) { $topMem = $m; $topMemPct = (int)$m['count']; }
    }
    // Cores profile
    $coreTotal = array_sum(array_map(fn($r) => (int)$r['count'], $coresDist));
    $topCore = null;
    $topCorePct = 0;
    foreach ($coresDist as $c) {
        if ((int)$c['count'] > $topCorePct) { $topCore = $c; $topCorePct = (int)$c['count']; }
    }
    ?>

    <?php if ($topNet): ?>
    <div class="insight">
        <span class="insight-label">Network Profile:</span>
        <?= $topPct ?>% of sessions are on <strong><?= htmlspecialchars($netName) ?></strong> connections<?= $topPct > 50 ? ', indicating a predominantly ' . (in_array($netName, ['4g', '3g', '2g']) ? 'mobile' : 'desktop/wifi') . ' user base' : '' ?>.
        <?php if (count($networkTypes) > 1): ?>
            The remaining traffic is split across <?= count($networkTypes) - 1 ?> other connection type<?= count($networkTypes) - 1 > 1 ? 's' : '' ?>.
        <?php endif; ?>
        <?php if (in_array($netName, ['3g', '2g'])): ?>
            Consider optimizing asset sizes for bandwidth-constrained users.
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($topMem && $memTotal > 0): ?>
    <div class="insight">
        <span class="insight-label">Device Hardware:</span>
        <?php $memPct = round(($topMemPct / $memTotal) * 100); ?>
        The most common RAM configuration is <strong><?= (int)$topMem['memory'] ?> GB</strong> (<?= $memPct ?>% of sessions).
        <?php
        $zeroMem = 0;
        foreach ($memoryDist as $m) { if ((int)$m['memory'] === 0) $zeroMem = (int)$m['count']; }
        $zeroPct = $memTotal > 0 ? round(($zeroMem / $memTotal) * 100) : 0;
        if ($zeroPct > 20): ?>
            Note: <?= $zeroPct ?>% of sessions report 0 GB memory — these browsers don't support the Device Memory API, so hardware profiling is unreliable for this segment.
        <?php endif; ?>
        <?php if ($topCore && $coreTotal > 0): ?>
            The majority of devices have <strong><?= (int)$topCore['cores'] ?> CPU cores</strong> (<?= round(($topCorePct / $coreTotal) * 100) ?>%), suggesting <?= (int)$topCore['cores'] >= 8 ? 'mid-to-high-end' : ((int)$topCore['cores'] >= 4 ? 'mid-range' : 'low-end') ?> hardware.
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
                    <th>Language</th>
                    <th>Screen</th>
                    <th>Viewport</th>
                    <th>Pixel Ratio</th>
                    <th>Cores</th>
                    <th>Memory</th>
                    <th>Network</th>
                    <th>Color Scheme</th>
                    <th>Timezone</th>
                    <th>Cookies</th>
                    <th>JS</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <?php // substr truncates the full UUID to 12 chars for display; the full value is kept
                          // in the title attribute so it is visible on hover without crowding the column. ?>
                    <td title="<?= htmlspecialchars($row['session_id']) ?>"><?= htmlspecialchars(substr($row['session_id'], 0, 12)) ?>…</td>
                    <td><?= htmlspecialchars($row['url']) ?></td>
                    <td><?= htmlspecialchars($row['language']) ?></td>
                    <td><?= $row['screen_width'] ?>×<?= $row['screen_height'] ?></td>
                    <td><?= $row['viewport_width'] ?>×<?= $row['viewport_height'] ?></td>
                    <td><?= $row['pixel_ratio'] ?></td>
                    <td><?= $row['cores'] ?></td>
                    <td><?= $row['memory'] ?> GB</td>
                    <td><?= htmlspecialchars($row['network_type'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['color_scheme'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['timezone']) ?></td>
                    <td><?= $row['cookies_enabled'] ? 'Yes' : 'No' ?></td>
                    <td><?= $row['js_enabled'] ? 'Yes' : 'No' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="chart-col">
        <h2>Sessions by Network Type</h2>
        <?php if (!empty($networkTypes)): ?>
        <div class="chart-wrap">
            <canvas id="networkChart"></canvas>
            <noscript><p>Charts require JavaScript to display. Please enable JavaScript in your browser.</p></noscript>
        </div>
        <script>

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
                    title: { display: true, text: 'Session Count by Network Connection Type', font: { size: 14 } },
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => ctx.parsed.y + ' session' + (ctx.parsed.y !== 1 ? 's' : '') + ' on ' + ctx.label } }
                },
                scales: {
                    x: { title: { display: true, text: 'Network Type' } },
                    y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Sessions' } }
                }
            }
        });
        </script>
        <p class="chart-desc">Distribution of visitor sessions by network connection type (e.g. 4g, wifi, 3g). Collected from the browser's Network Information API on each pageview. Helps identify the connectivity profile of your audience.</p>
        <?php else: ?>
            <p>No data available yet.</p>
        <?php endif; ?>

        <?php if (!empty($memoryDist)): ?>
        <h2>Device Memory (RAM)</h2>
        <div class="chart-wrap">
            <canvas id="memoryChart"></canvas>
        </div>
        <p class="chart-desc">Distribution of sessions by device RAM. Helps understand whether your users are on low-end or high-end hardware, which can inform performance optimization decisions.</p>
        <?php endif; ?>

        <?php if (!empty($coresDist)): ?>
        <h2>CPU Cores</h2>
        <div class="chart-wrap">
            <canvas id="coresChart"></canvas>
        </div>
        <p class="chart-desc">Distribution of sessions by CPU core count. Combined with RAM data, gives a full picture of your users' device capabilities.</p>
        <?php endif; ?>
    </div>
</div>

<script>
<?php if (!empty($memoryDist)): ?>
new Chart(document.getElementById('memoryChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(fn($r) => ($r['memory'] ?: '0') . ' GB', $memoryDist)) ?>,
        datasets: [{
            data: <?= json_encode(array_map(fn($r) => (int)$r['count'], $memoryDist)) ?>,
            backgroundColor: ['rgba(239,68,68,0.7)', 'rgba(245,158,11,0.7)', 'rgba(16,185,129,0.7)', 'rgba(59,130,246,0.7)', 'rgba(139,92,246,0.7)']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: { display: true, text: 'Sessions by Device RAM', font: { size: 14 } },
            tooltip: { callbacks: { label: function(ctx) { return ctx.label + ': ' + ctx.parsed + ' session' + (ctx.parsed !== 1 ? 's' : ''); } } }
        }
    }
});
<?php endif; ?>

<?php if (!empty($coresDist)): ?>
new Chart(document.getElementById('coresChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($r) => $r['cores'] . ' cores', $coresDist)) ?>,
        datasets: [{
            label: 'Sessions',
            data: <?= json_encode(array_map(fn($r) => (int)$r['count'], $coresDist)) ?>,
            backgroundColor: 'rgba(139, 92, 246, 0.7)',
            borderColor: 'rgba(139, 92, 246, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: { display: true, text: 'Sessions by CPU Core Count', font: { size: 14 } },
            legend: { display: false },
            tooltip: { callbacks: { label: function(ctx) { return ctx.parsed.y + ' session' + (ctx.parsed.y !== 1 ? 's' : ''); } } }
        },
        scales: {
            x: { title: { display: true, text: 'CPU Cores' } },
            y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Sessions' } }
        }
    }
});
<?php endif; ?>
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
