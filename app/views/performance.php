<?php $pageTitle = 'Performance Data — Analytics'; require __DIR__ . '/layout/header.php'; ?>

<h1>Performance Data</h1>
<?php if ($_SESSION['role'] === 'super_admin' || ($_SESSION['role'] === 'analyst' && Auth::canAccessSection('performance'))): ?>
<a href="/reports/performance/generate" class="btn">Generate Report</a>
<button class="btn" onclick="exportWithChart('performance', null, 'perfChart')">Export</button>
<?php endif; ?>
<p><?= count($rows) ?> of <?= $total ?> records</p>

<?php require __DIR__ . '/layout/pagination.php'; ?>

<?php if ($total > 0): ?>
<div class="analyst-commentary">
    <h2>Analyst Commentary</h2>

    <?php
    $lcp = (float)$webVitals['lcp'];
    $cls = (float)$webVitals['cls'];
    $inp = (float)$webVitals['inp'];

    $lcpStatus = $lcp < 2500 ? 'good' : ($lcp < 4000 ? 'warn' : 'poor');
    $clsStatus = $cls < 0.1 ? 'good' : ($cls < 0.25 ? 'warn' : 'poor');
    $inpStatus = $inp < 200 ? 'good' : ($inp < 500 ? 'warn' : 'poor');

    $statusLabel = ['good' => '✓ Good', 'warn' => '⚠ Needs Improvement', 'poor' => '✗ Poor'];

    $dns = (float)$networkTiming['dns'];
    $tcp = (float)$networkTiming['tcp'];
    $tls = (float)$networkTiming['tls'];
    $ttfb = (float)$networkTiming['ttfb'];
    $dl = (float)$networkTiming['download'];
    $totalTiming = $dns + $tcp + $tls + $ttfb + $dl;

    $phases = ['DNS' => $dns, 'TCP' => $tcp, 'TLS' => $tls, 'TTFB' => $ttfb, 'Download' => $dl];
    arsort($phases);
    $bottleneck = array_key_first($phases);
    $bottleneckVal = $phases[$bottleneck];
    $bottleneckPct = $totalTiming > 0 ? round(($bottleneckVal / $totalTiming) * 100) : 0;
    ?>

    <div class="insight">
        <span class="insight-label">Core Web Vitals Assessment:</span>
        LCP averages <?= round($lcp, 1) ?>ms (<span class="<?= $lcpStatus ?>"><?= $statusLabel[$lcpStatus] ?></span>).
        CLS averages <?= round($cls, 4) ?> (<span class="<?= $clsStatus ?>"><?= $statusLabel[$clsStatus] ?></span>).
        INP averages <?= round($inp, 1) ?>ms (<span class="<?= $inpStatus ?>"><?= $statusLabel[$inpStatus] ?></span>).
        <?php
        $passing = ($lcpStatus === 'good' ? 1 : 0) + ($clsStatus === 'good' ? 1 : 0) + ($inpStatus === 'good' ? 1 : 0);
        if ($passing === 3): ?>
            All three Core Web Vitals pass Google's recommended thresholds — the site delivers a strong user experience.
        <?php elseif ($passing >= 1): ?>
            <?= 3 - $passing ?> of 3 vitals need attention. Focus optimization efforts on the metrics marked above.
        <?php else: ?>
            All three vitals are below recommended thresholds — significant performance work is needed.
        <?php endif; ?>
    </div>

    <div class="insight">
        <span class="insight-label">Network Bottleneck:</span>
        The <strong><?= $bottleneck ?></strong> phase dominates at <?= round($bottleneckVal, 1) ?>ms average, accounting for <?= $bottleneckPct ?>% of total request time.
        <?php if ($bottleneck === 'Download'): ?>
            Consider enabling compression (gzip/brotli), optimizing image sizes, or using a CDN to reduce transfer times.
        <?php elseif ($bottleneck === 'TTFB'): ?>
            High TTFB suggests server-side processing delays. Investigate database query performance, caching, or server configuration.
        <?php elseif ($bottleneck === 'TLS'): ?>
            TLS negotiation overhead is high. Ensure TLS 1.3 is enabled and session resumption is configured.
        <?php elseif ($bottleneck === 'TCP'): ?>
            TCP connection time is elevated. Users may be geographically distant from the server — consider a CDN.
        <?php elseif ($bottleneck === 'DNS'): ?>
            DNS resolution is the bottleneck. Consider DNS prefetching or switching to a faster DNS provider.
        <?php endif; ?>
    </div>
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
                    <th>TTFB (ms)</th>
                    <th>DOM Interactive (ms)</th>
                    <th>DOM Complete (ms)</th>
                    <th>Total Load (ms)</th>
                    <th>LCP (ms)</th>
                    <th>CLS</th>
                    <th>INP (ms)</th>
                    <th>DNS (ms)</th>
                    <th>TCP (ms)</th>
                    <th>TLS (ms)</th>
                    <th>Resources</th>
                    <th>Transfer Size</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td title="<?= htmlspecialchars($row['session_id']) ?>"><?= htmlspecialchars(substr($row['session_id'], 0, 12)) ?>…</td>
                    <td><?= htmlspecialchars($row['url']) ?></td>
                    <td><?= $row['ttfb'] ?></td>
                    <td><?= $row['dom_interactive'] ?></td>
                    <td><?= $row['dom_complete'] ?></td>
                    <td><?= $row['total_load_time'] ?></td>
                    <td><?= $row['lcp'] ?? '—' ?></td>
                    <td><?= $row['cls'] ?? '—' ?></td>
                    <td><?= $row['inp'] ?? '—' ?></td>
                    <td><?= $row['dns_lookup'] ?></td>
                    <td><?= $row['tcp_connect'] ?></td>
                    <td><?= $row['tls_handshake'] ?></td>
                    <td><?= $row['total_resources'] ?></td>
                    <td><?= number_format($row['transfer_size'] / 1024, 1) ?> KB</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="chart-col">
        <h2>Timing per Session (10 most recent)</h2>
        <?php if (!empty($chartRows)): ?>
        <?php
        $sessions = array_map(fn($r) => substr($r['session_id'], 0, 8) . '…', $chartRows);
        $ttfbVals = array_map(fn($r) => (float)$r['ttfb'],           $chartRows);
        $lcpVals  = array_map(fn($r) => (float)($r['lcp'] ?? 0),     $chartRows);
        $domVals  = array_map(fn($r) => (float)$r['dom_complete'],    $chartRows);
        ?>
        <div class="chart-wrap">
            <canvas id="perfChart"></canvas>
            <noscript><p>Charts require JavaScript to display. Please enable JavaScript in your browser.</p></noscript>
        </div>
        <script>

        new Chart(document.getElementById('perfChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($sessions) ?>,
                datasets: [
                    {
                        label: 'TTFB (ms)',
                        data: <?= json_encode($ttfbVals) ?>,
                        backgroundColor: 'rgba(59, 130, 246, 0.7)'
                    },
                    {
                        label: 'DOM Complete (ms)',
                        data: <?= json_encode($domVals) ?>,
                        backgroundColor: 'rgba(16, 185, 129, 0.7)'
                    },
                    {
                        label: 'LCP (ms)',
                        data: <?= json_encode($lcpVals) ?>,
                        backgroundColor: 'rgba(245, 158, 11, 0.7)'
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    title: { display: true, text: 'Page Load Timing Breakdown (10 Most Recent Sessions)', font: { size: 14 } },
                    tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + ctx.parsed.y.toFixed(1) + ' ms' } }
                },
                scales: {
                    x: { title: { display: true, text: 'Session ID' } },
                    y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Time (ms)' } }
                }
            }
        });
        </script>
        <p class="chart-desc">Compares three key timing metrics for the 10 most recent sessions. TTFB (Time to First Byte) measures server response speed. DOM Complete is when the page is fully parsed and rendered. LCP (Largest Contentful Paint) is a Core Web Vital reflecting perceived load speed — lower values mean faster experiences.</p>
        <?php else: ?>
            <p>No data available yet.</p>
        <?php endif; ?>

        <h2>Core Web Vitals</h2>
        <div class="chart-wrap">
            <canvas id="vitalsChart"></canvas>
        </div>
        <p class="chart-desc">Average values for the three Core Web Vitals. LCP (Largest Contentful Paint): green &lt; 2500ms. CLS (Cumulative Layout Shift): green &lt; 0.1. INP (Interaction to Next Paint): green &lt; 200ms. Bar colors indicate whether the metric passes, needs improvement, or is poor.</p>

        <h2>Network Timing Breakdown</h2>
        <div class="chart-wrap">
            <canvas id="networkTimingChart"></canvas>
        </div>
        <p class="chart-desc">Average time spent in each phase of a network request. Identifies which part of the connection lifecycle is the bottleneck — DNS resolution, TCP handshake, TLS negotiation, server response (TTFB), or content download.</p>
    </div>
</div>

<script>
// Core Web Vitals with threshold coloring
(function() {
    var lcp = <?= (float)$webVitals['lcp'] ?>;
    var cls = <?= (float)$webVitals['cls'] ?>;
    var inp = <?= (float)$webVitals['inp'] ?>;

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

// Network Timing Breakdown
new Chart(document.getElementById('networkTimingChart'), {
    type: 'bar',
    data: {
        labels: ['Average Request Lifecycle'],
        datasets: [
            { label: 'DNS Lookup', data: [<?= (float)$networkTiming['dns'] ?>], backgroundColor: 'rgba(59,130,246,0.8)' },
            { label: 'TCP Connect', data: [<?= (float)$networkTiming['tcp'] ?>], backgroundColor: 'rgba(16,185,129,0.8)' },
            { label: 'TLS Handshake', data: [<?= (float)$networkTiming['tls'] ?>], backgroundColor: 'rgba(245,158,11,0.8)' },
            { label: 'TTFB', data: [<?= (float)$networkTiming['ttfb'] ?>], backgroundColor: 'rgba(239,68,68,0.8)' },
            { label: 'Download', data: [<?= (float)$networkTiming['download'] ?>], backgroundColor: 'rgba(139,92,246,0.8)' }
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
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
