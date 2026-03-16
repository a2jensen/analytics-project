<?php
$pageTitle = htmlspecialchars($report['title']) . ' — Analytics';
require __DIR__ . '/../layout/header.php';

$rows      = $report['snapshot_data'];
$section   = $report['section'];
$chartType = $report['chart_type'];
$columns   = !empty($rows) ? array_keys($rows[0]) : [];
?>

<h1><?= htmlspecialchars($report['title']) ?></h1>
<button class="btn" onclick="exportWithChart('saved', <?= (int)$report['id'] ?>, 'reportChart')">Export</button>
<p class="report-meta">
    <?= htmlspecialchars(ucfirst($section)) ?> &middot;
    Saved by <?= htmlspecialchars($report['created_by_username']) ?> &middot;
    <?= htmlspecialchars($report['created_at']) ?>
</p>

<?php if (!empty($report['commentary'])): ?>
    <div class="report-commentary">
        <h2>Commentary</h2>
        <p><?= nl2br(htmlspecialchars($report['commentary'])) ?></p>
    </div>
<?php endif; ?>

<?php if (empty($rows)): ?>
    <p>No records in this snapshot.</p>
<?php else: ?>

    <?php
    // ── Compute section-specific aggregates from snapshot rows ──
    if ($section === 'static') {
        // Network type distribution
        $netCounts = [];
        foreach ($rows as $r) {
            $nt = $r['network_type'] ?? 'unknown';
            $netCounts[$nt] = ($netCounts[$nt] ?? 0) + 1;
        }
        arsort($netCounts);

        // Memory distribution
        $memCounts = [];
        foreach ($rows as $r) {
            $mem = (int)($r['memory'] ?? 0);
            $key = $mem . ' GB';
            $memCounts[$key] = ($memCounts[$key] ?? 0) + 1;
        }
        ksort($memCounts);

        // Cores distribution
        $coreCounts = [];
        foreach ($rows as $r) {
            $c = (int)($r['cores'] ?? 0);
            $key = $c . ' cores';
            $coreCounts[$key] = ($coreCounts[$key] ?? 0) + 1;
        }
        ksort($coreCounts);

    } elseif ($section === 'performance') {
        // Session timings (up to 10 most recent by ID)
        $sorted = $rows;
        usort($sorted, fn($a, $b) => (int)$b['id'] - (int)$a['id']);
        $recent = array_slice($sorted, 0, 10);
        $recent = array_reverse($recent);
        $sessLabels = array_map(fn($r) => substr($r['session_id'] ?? '', 0, 8) . '…', $recent);
        $ttfbVals = array_map(fn($r) => (float)($r['ttfb'] ?? 0), $recent);
        $domVals = array_map(fn($r) => (float)($r['dom_complete'] ?? 0), $recent);
        $lcpVals = array_map(fn($r) => (float)($r['lcp'] ?? 0), $recent);

        // Web Vitals averages
        $count = count($rows);
        $lcpAvg = $count > 0 ? array_sum(array_map(fn($r) => (float)($r['lcp'] ?? 0), $rows)) / $count : 0;
        $clsAvg = $count > 0 ? array_sum(array_map(fn($r) => (float)($r['cls'] ?? 0), $rows)) / $count : 0;
        $inpAvg = $count > 0 ? array_sum(array_map(fn($r) => (float)($r['inp'] ?? 0), $rows)) / $count : 0;

        // Network timing averages
        $dnsAvg = $count > 0 ? array_sum(array_map(fn($r) => (float)($r['dns_lookup'] ?? 0), $rows)) / $count : 0;
        $tcpAvg = $count > 0 ? array_sum(array_map(fn($r) => (float)($r['tcp_connect'] ?? 0), $rows)) / $count : 0;
        $tlsAvg = $count > 0 ? array_sum(array_map(fn($r) => (float)($r['tls_handshake'] ?? 0), $rows)) / $count : 0;
        $ttfbAvg = $count > 0 ? array_sum(array_map(fn($r) => (float)($r['ttfb'] ?? 0), $rows)) / $count : 0;
        $dlAvg = $count > 0 ? array_sum(array_map(fn($r) => (float)($r['download'] ?? 0), $rows)) / $count : 0;

    } elseif ($section === 'activity') {
        // Event type counts
        $typeCounts = [];
        foreach ($rows as $r) {
            $t = $r['type'] ?? 'unknown';
            $typeCounts[$t] = ($typeCounts[$t] ?? 0) + 1;
        }
        arsort($typeCounts);

        // Clicked elements (filter click events, group by tag_name)
        $clickCounts = [];
        foreach ($rows as $r) {
            if (($r['type'] ?? '') === 'click' && !empty($r['tag_name'])) {
                $tag = strtoupper($r['tag_name']);
                $clickCounts[$tag] = ($clickCounts[$tag] ?? 0) + 1;
            }
        }
        arsort($clickCounts);
        $clickCounts = array_slice($clickCounts, 0, 10, true);

        // Scroll depth distribution (filter scroll_final, bucket into quartiles)
        $scrollBuckets = ['0-25%' => 0, '26-50%' => 0, '51-75%' => 0, '76-100%' => 0];
        foreach ($rows as $r) {
            if (($r['type'] ?? '') === 'scroll_final' && isset($r['scroll_depth'])) {
                $d = (float)$r['scroll_depth'];
                if ($d <= 25) $scrollBuckets['0-25%']++;
                elseif ($d <= 50) $scrollBuckets['26-50%']++;
                elseif ($d <= 75) $scrollBuckets['51-75%']++;
                else $scrollBuckets['76-100%']++;
            }
        }
        $hasScrollData = array_sum($scrollBuckets) > 0;
    }
    ?>

    <div class="data-layout">
        <div>
            <h2>Data</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($columns as $col): ?>
                                <th><?= htmlspecialchars($col) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($columns as $col): ?>
                                <td><?= htmlspecialchars((string)($row[$col] ?? '—')) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="chart-col">
        <?php if ($section === 'static'): ?>
            <!-- ── Static: Network Type ── -->
            <h2>Sessions by Network Type</h2>
            <div class="chart-wrap"><canvas id="reportChart"></canvas></div>

            <?php if (!empty($memCounts)): ?>
            <h2>Device Memory (RAM)</h2>
            <div class="chart-wrap"><canvas id="memoryChart"></canvas></div>
            <?php endif; ?>

            <?php if (!empty($coreCounts)): ?>
            <h2>CPU Cores</h2>
            <div class="chart-wrap"><canvas id="coresChart"></canvas></div>
            <?php endif; ?>

        <?php elseif ($section === 'performance'): ?>
            <!-- ── Performance: Session Timings ── -->
            <h2>Timing per Session (up to 10)</h2>
            <div class="chart-wrap"><canvas id="reportChart"></canvas></div>

            <h2>Core Web Vitals</h2>
            <div class="chart-wrap"><canvas id="vitalsChart"></canvas></div>

            <h2>Network Timing Breakdown</h2>
            <div class="chart-wrap"><canvas id="networkTimingChart"></canvas></div>

        <?php elseif ($section === 'activity'): ?>
            <!-- ── Activity: Events by Type ── -->
            <h2>Events by Type</h2>
            <div class="chart-wrap"><canvas id="reportChart"></canvas></div>

            <?php if (!empty($clickCounts)): ?>
            <h2>Most Clicked Elements</h2>
            <div class="chart-wrap"><canvas id="clickedChart"></canvas></div>
            <?php endif; ?>

            <?php if (!empty($hasScrollData)): ?>
            <h2>Scroll Depth Distribution</h2>
            <div class="chart-wrap"><canvas id="scrollChart"></canvas></div>
            <?php endif; ?>

        <?php endif; ?>
        </div>
    </div>

    <script>
    <?php if ($section === 'static'): ?>

    // Network Type bar chart
    new Chart(document.getElementById('reportChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($netCounts)) ?>,
            datasets: [{
                label: 'Number of Sessions',
                data: <?= json_encode(array_values($netCounts)) ?>,
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

    <?php if (!empty($memCounts)): ?>
    // Memory doughnut
    new Chart(document.getElementById('memoryChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($memCounts)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($memCounts)) ?>,
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

    <?php if (!empty($coreCounts)): ?>
    // Cores bar chart
    new Chart(document.getElementById('coresChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($coreCounts)) ?>,
            datasets: [{
                label: 'Sessions',
                data: <?= json_encode(array_values($coreCounts)) ?>,
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

    <?php elseif ($section === 'performance'): ?>

    // Session timings grouped bar
    new Chart(document.getElementById('reportChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($sessLabels) ?>,
            datasets: [
                { label: 'TTFB (ms)', data: <?= json_encode($ttfbVals) ?>, backgroundColor: 'rgba(59, 130, 246, 0.7)' },
                { label: 'DOM Complete (ms)', data: <?= json_encode($domVals) ?>, backgroundColor: 'rgba(16, 185, 129, 0.7)' },
                { label: 'LCP (ms)', data: <?= json_encode($lcpVals) ?>, backgroundColor: 'rgba(245, 158, 11, 0.7)' }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                title: { display: true, text: 'Page Load Timing Breakdown (up to 10 Sessions)', font: { size: 14 } },
                tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + ctx.parsed.y.toFixed(1) + ' ms' } }
            },
            scales: {
                x: { title: { display: true, text: 'Session ID' } },
                y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Time (ms)' } }
            }
        }
    });

    // Core Web Vitals with threshold coloring
    (function() {
        var lcp = <?= round($lcpAvg, 1) ?>;
        var cls = <?= round($clsAvg, 4) ?>;
        var inp = <?= round($inpAvg, 1) ?>;

        function vitalColor(metric, val) {
            if (metric === 'lcp') return val < 2500 ? 'rgba(16,185,129,0.8)' : val < 4000 ? 'rgba(245,158,11,0.8)' : 'rgba(239,68,68,0.8)';
            if (metric === 'cls') return val < 0.1 ? 'rgba(16,185,129,0.8)' : val < 0.25 ? 'rgba(245,158,11,0.8)' : 'rgba(239,68,68,0.8)';
            if (metric === 'inp') return val < 200 ? 'rgba(16,185,129,0.8)' : val < 500 ? 'rgba(245,158,11,0.8)' : 'rgba(239,68,68,0.8)';
        }

        new Chart(document.getElementById('vitalsChart'), {
            type: 'bar',
            data: {
                labels: ['LCP (ms)', 'CLS', 'INP (ms)'],
                datasets: [{
                    label: 'Average',
                    data: [lcp, cls, inp],
                    backgroundColor: [vitalColor('lcp', lcp), vitalColor('cls', cls), vitalColor('inp', inp)],
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: {
                    title: { display: true, text: 'Core Web Vitals Health Check', font: { size: 14 } },
                    legend: { display: false },
                    tooltip: { callbacks: { label: function(ctx) { return ctx.label + ': ' + ctx.parsed.x; } } }
                },
                scales: {
                    x: { beginAtZero: true, title: { display: true, text: 'Value' } }
                }
            }
        });
    })();

    // Network Timing Breakdown stacked horizontal bar
    new Chart(document.getElementById('networkTimingChart'), {
        type: 'bar',
        data: {
            labels: ['Average Request Lifecycle'],
            datasets: [
                { label: 'DNS Lookup', data: [<?= round($dnsAvg, 1) ?>], backgroundColor: 'rgba(59,130,246,0.8)' },
                { label: 'TCP Connect', data: [<?= round($tcpAvg, 1) ?>], backgroundColor: 'rgba(16,185,129,0.8)' },
                { label: 'TLS Handshake', data: [<?= round($tlsAvg, 1) ?>], backgroundColor: 'rgba(245,158,11,0.8)' },
                { label: 'TTFB', data: [<?= round($ttfbAvg, 1) ?>], backgroundColor: 'rgba(239,68,68,0.8)' },
                { label: 'Download', data: [<?= round($dlAvg, 1) ?>], backgroundColor: 'rgba(139,92,246,0.8)' }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: {
                title: { display: true, text: 'Average Network Timing Phases (ms)', font: { size: 14 } },
                tooltip: { callbacks: { label: function(ctx) { return ctx.dataset.label + ': ' + ctx.parsed.x.toFixed(1) + ' ms'; } } }
            },
            scales: {
                x: { stacked: true, beginAtZero: true, title: { display: true, text: 'Time (ms)' } },
                y: { stacked: true }
            }
        }
    });

    <?php elseif ($section === 'activity'): ?>

    // Events by Type bar chart
    new Chart(document.getElementById('reportChart'), {
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

    <?php if (!empty($clickCounts)): ?>
    // Most Clicked Elements horizontal bar
    new Chart(document.getElementById('clickedChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($clickCounts)) ?>,
            datasets: [{
                label: 'Clicks',
                data: <?= json_encode(array_values($clickCounts)) ?>,
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

    <?php if (!empty($hasScrollData)): ?>
    // Scroll Depth quartile bar
    new Chart(document.getElementById('scrollChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($scrollBuckets)) ?>,
            datasets: [{
                label: 'Sessions',
                data: <?= json_encode(array_values($scrollBuckets)) ?>,
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

    <?php endif; ?>
    </script>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
