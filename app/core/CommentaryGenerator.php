<?php
// Generates plain-text analyst commentary for each report section.
// Used to pre-populate the commentary textarea on the report generation form.
class CommentaryGenerator {

    public static function static(PDO $pdo): string {
        $model = new StaticModel($pdo);
        $lines = [];

        $networkTypes = $model->getNetworkTypes();
        if (!empty($networkTypes)) {
            $top = $networkTypes[0];
            $total = array_sum(array_map(fn($r) => (int)$r['count'], $networkTypes));
            $pct = $total > 0 ? round(($top['count'] / $total) * 100) : 0;
            $name = $top['network_type'] ?? 'unknown';
            $line = "Network Profile: {$pct}% of sessions are on {$name} connections";
            if ($pct > 50 && in_array($name, ['4g', '3g', '2g'])) {
                $line .= ', indicating a predominantly mobile user base';
            } elseif ($pct > 50) {
                $line .= ', indicating a predominantly desktop/wifi user base';
            }
            $line .= '.';
            if (in_array($name, ['3g', '2g'])) {
                $line .= ' Consider optimizing asset sizes for bandwidth-constrained users.';
            }
            $lines[] = $line;
        }

        $memoryDist = $model->getMemoryDistribution();
        $coresDist = $model->getCoresDistribution();
        if (!empty($memoryDist) || !empty($coresDist)) {
            $parts = [];
            if (!empty($memoryDist)) {
                $memTotal = array_sum(array_map(fn($r) => (int)$r['count'], $memoryDist));
                $topMem = $memoryDist[0];
                foreach ($memoryDist as $m) {
                    if ((int)$m['count'] > (int)$topMem['count']) $topMem = $m;
                }
                if ($memTotal > 0) {
                    $memPct = round(((int)$topMem['count'] / $memTotal) * 100);
                    $parts[] = "most common RAM is {$topMem['memory']} GB ({$memPct}% of sessions)";
                }
            }
            if (!empty($coresDist)) {
                $coreTotal = array_sum(array_map(fn($r) => (int)$r['count'], $coresDist));
                $topCore = $coresDist[0];
                foreach ($coresDist as $c) {
                    if ((int)$c['count'] > (int)$topCore['count']) $topCore = $c;
                }
                if ($coreTotal > 0) {
                    $corePct = round(((int)$topCore['count'] / $coreTotal) * 100);
                    $tier = (int)$topCore['cores'] >= 8 ? 'mid-to-high-end' : ((int)$topCore['cores'] >= 4 ? 'mid-range' : 'low-end');
                    $parts[] = "majority have {$topCore['cores']} CPU cores ({$corePct}%), suggesting {$tier} hardware";
                }
            }
            if (!empty($parts)) {
                $lines[] = 'Device Hardware: ' . ucfirst(implode('; ', $parts)) . '.';
            }
        }

        return implode("\n\n", $lines);
    }

    public static function performance(PDO $pdo): string {
        $model = new PerformanceModel($pdo);
        $lines = [];

        $vitals = $model->getWebVitalsAvg();
        $lcp = (float)$vitals['lcp'];
        $cls = (float)$vitals['cls'];
        $inp = (float)$vitals['inp'];

        $lcpLabel = $lcp < 2500 ? 'Good' : ($lcp < 4000 ? 'Needs Improvement' : 'Poor');
        $clsLabel = $cls < 0.1 ? 'Good' : ($cls < 0.25 ? 'Needs Improvement' : 'Poor');
        $inpLabel = $inp < 200 ? 'Good' : ($inp < 500 ? 'Needs Improvement' : 'Poor');

        $passing = ($lcp < 2500 ? 1 : 0) + ($cls < 0.1 ? 1 : 0) + ($inp < 200 ? 1 : 0);

        $vitalsLine = "Core Web Vitals: LCP " . round($lcp, 1) . "ms ({$lcpLabel}), "
            . "CLS " . round($cls, 4) . " ({$clsLabel}), "
            . "INP " . round($inp, 1) . "ms ({$inpLabel}).";
        if ($passing === 3) {
            $vitalsLine .= ' All three pass Google\'s recommended thresholds.';
        } elseif ($passing >= 1) {
            $vitalsLine .= ' ' . (3 - $passing) . ' of 3 vitals need attention.';
        } else {
            $vitalsLine .= ' All three are below recommended thresholds — significant performance work is needed.';
        }
        $lines[] = $vitalsLine;

        $timing = $model->getNetworkTimingAvg();
        $phases = [
            'DNS' => (float)$timing['dns'],
            'TCP' => (float)$timing['tcp'],
            'TLS' => (float)$timing['tls'],
            'TTFB' => (float)$timing['ttfb'],
            'Download' => (float)$timing['download'],
        ];
        $totalTiming = array_sum($phases);
        arsort($phases);
        $bottleneck = array_key_first($phases);
        $bottleneckVal = $phases[$bottleneck];
        $bottleneckPct = $totalTiming > 0 ? round(($bottleneckVal / $totalTiming) * 100) : 0;

        $tips = [
            'Download' => 'Consider enabling compression or using a CDN to reduce transfer times.',
            'TTFB' => 'High TTFB suggests server-side delays. Investigate caching or query performance.',
            'TLS' => 'TLS negotiation overhead is high. Ensure TLS 1.3 and session resumption are configured.',
            'TCP' => 'TCP time is elevated. Users may be distant from the server — consider a CDN.',
            'DNS' => 'DNS resolution is the bottleneck. Consider DNS prefetching or a faster provider.',
        ];

        $timingLine = "Network Bottleneck: {$bottleneck} phase dominates at " . round($bottleneckVal, 1)
            . "ms ({$bottleneckPct}% of total request time).";
        if (isset($tips[$bottleneck])) {
            $timingLine .= ' ' . $tips[$bottleneck];
        }
        $lines[] = $timingLine;

        return implode("\n\n", $lines);
    }

    public static function activity(PDO $pdo): string {
        $model = new ActivityModel($pdo);
        $lines = [];

        $typeCounts = $model->getTypeCounts();
        $totalEvents = array_sum($typeCounts);
        if ($totalEvents > 0) {
            arsort($typeCounts);
            $topType = array_key_first($typeCounts);
            $topCount = $typeCounts[$topType];
            $topPct = round(($topCount / $totalEvents) * 100);
            $clickCount = $typeCounts['click'] ?? 0;
            $scrollCount = ($typeCounts['scroll_depth'] ?? 0) + ($typeCounts['scroll_final'] ?? 0);
            $clickPct = round(($clickCount / $totalEvents) * 100);
            $scrollPct = round(($scrollCount / $totalEvents) * 100);

            $line = 'User Engagement: ' . ucfirst(str_replace('_', ' ', $topType))
                . " events dominate at {$topPct}% ({$topCount} of {$totalEvents} events).";
            if ($clickCount > 0 && $scrollCount > 0) {
                $line .= " Clicks: {$clickPct}%, Scroll: {$scrollPct}%.";
                if ($scrollPct > $clickPct * 2) {
                    $line .= ' High scroll-to-click ratio suggests passive content consumption.';
                } elseif ($clickPct > $scrollPct) {
                    $line .= ' Clicks outpace scrolling — users are actively engaging with page elements.';
                }
            }
            $lines[] = $line;
        }

        $clicked = $model->getClickedElements();
        if (!empty($clicked)) {
            $topEl = $clicked[0];
            $tag = strtoupper($topEl['tag_name']);
            $totalClicks = array_sum(array_map(fn($r) => (int)$r['count'], $clicked));
            $elPct = $totalClicks > 0 ? round(((int)$topEl['count'] / $totalClicks) * 100) : 0;
            $line = "Click Targets: {$tag} elements receive the most clicks ({$elPct}% of all clicks).";
            if (in_array(strtolower($topEl['tag_name']), ['div', 'span', 'p'])) {
                $line .= " Users clicking non-interactive elements may indicate missing clickable affordances.";
            }
            $lines[] = $line;
        }

        $scrollDist = $model->getScrollDepthDistribution();
        if (!empty($scrollDist)) {
            $scrollTotal = array_sum(array_map(fn($r) => (int)$r['count'], $scrollDist));
            $deep = 0;
            $shallow = 0;
            foreach ($scrollDist as $s) {
                if (str_starts_with($s['depth_range'], '76') || str_starts_with($s['depth_range'], '100')) {
                    $deep += (int)$s['count'];
                }
                if (str_starts_with($s['depth_range'], '0')) {
                    $shallow += (int)$s['count'];
                }
            }
            $deepPct = $scrollTotal > 0 ? round(($deep / $scrollTotal) * 100) : 0;
            $shallowPct = $scrollTotal > 0 ? round(($shallow / $scrollTotal) * 100) : 0;

            $line = "Scroll Depth: {$deepPct}% of sessions reach 76-100% of the page.";
            if ($shallowPct > 30) {
                $line .= " {$shallowPct}% bounce in 0-25% — consider improving above-the-fold content.";
            }
            $lines[] = $line;
        }

        return implode("\n\n", $lines);
    }
}
