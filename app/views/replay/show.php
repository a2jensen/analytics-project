<?php
$pageTitle = 'Replay — Analytics';
require __DIR__ . '/../layout/header.php';

$screenW = (int)($meta['screen_width'] ?? 1920);
$screenH = (int)($meta['screen_height'] ?? 1080);
if ($screenW <= 0) $screenW = 1920;
if ($screenH <= 0) $screenH = 1080;

$firstEvent = $events[0]['event_timestamp'] ?? '';
$lastEvent  = end($events)['event_timestamp'] ?? $firstEvent;
$url        = $events[0]['url'] ?? '';
?>

<div class="replay-header">
    <h1>Session Replay</h1>
    <div class="replay-meta">
        <span><strong>Session:</strong> <?= htmlspecialchars($sessionId) ?></span>
        <span><strong>URL:</strong> <?= htmlspecialchars($url) ?></span>
        <span><strong>Events:</strong> <?= count($events) ?></span>
        <?php if ($meta): ?>
            <span><strong>Screen:</strong> <?= $screenW ?>×<?= $screenH ?></span>
            <span><strong>Network:</strong> <?= htmlspecialchars($meta['network_type'] ?? 'unknown') ?></span>
        <?php endif; ?>
    </div>
</div>

<div class="replay-container">
    <div class="replay-main">
        <!-- Viewport canvas -->
        <div class="replay-viewport" id="viewport">
            <div class="replay-canvas" id="canvas" style="aspect-ratio: <?= $screenW ?>/<?= $screenH ?>;">
                <div class="replay-url-bar"><?= htmlspecialchars($url) ?></div>
                <div id="scroll-indicator" class="scroll-indicator"><div id="scroll-thumb" class="scroll-thumb"></div></div>
                <div id="idle-overlay" class="idle-overlay" style="display:none;">Idle</div>
                <div id="end-overlay" class="end-overlay" style="display:none;">Session Ended</div>
            </div>
        </div>

        <!-- Timeline -->
        <div class="replay-timeline-wrap">
            <div class="replay-controls">
                <button id="playBtn" class="btn" style="font-size:0.85rem;padding:0.3rem 0.8rem;">&#9654; Play</button>
                <select id="speedSelect" style="padding:0.3rem;border-radius:4px;border:1px solid #d1d5db;">
                    <option value="1">1×</option>
                    <option value="2">2×</option>
                    <option value="5" selected>5×</option>
                    <option value="10">10×</option>
                    <option value="25">25×</option>
                </select>
                <span id="timeDisplay" class="replay-time">0.0s / 0.0s</span>
            </div>
            <div class="replay-timeline" id="timeline">
                <div class="replay-playhead" id="playhead"></div>
            </div>
            <div class="replay-legend">
                <span><span class="legend-dot" style="background:#3b82f6;"></span> Click</span>
                <span><span class="legend-dot" style="background:#10b981;"></span> Scroll</span>
                <span><span class="legend-dot" style="background:#f59e0b;"></span> Keyboard</span>
                <span><span class="legend-dot" style="background:#ef4444;"></span> Exit</span>
                <span><span class="legend-dot" style="background:#9ca3af;"></span> Idle</span>
            </div>
        </div>
    </div>

    <!-- Event log sidebar -->
    <div class="replay-sidebar">
        <h2>Event Log</h2>
        <div class="replay-event-log" id="eventLog">
            <?php foreach ($events as $i => $e): ?>
                <div class="replay-log-entry" data-index="<?= $i ?>" id="log-<?= $i ?>">
                    <span class="log-time"><?= htmlspecialchars(substr($e['event_timestamp'], 11, 8)) ?></span>
                    <span class="log-type log-type-<?= htmlspecialchars($e['type']) ?>"><?= htmlspecialchars($e['type']) ?></span>
                    <span class="log-detail">
                        <?php if ($e['type'] === 'click'): ?>
                            (<?= (int)$e['x'] ?>, <?= (int)$e['y'] ?>) <?= htmlspecialchars(strtolower($e['tag_name'] ?? '')) ?>
                        <?php elseif (str_contains($e['type'], 'scroll')): ?>
                            <?= (int)($e['scroll_depth'] ?? 0) ?>%
                        <?php elseif ($e['type'] === 'keyboard_activity'): ?>
                            <?= htmlspecialchars($e['key_name'] ?? '') ?>
                        <?php elseif ($e['type'] === 'page_exit'): ?>
                            <?= (int)($e['time_on_page'] ?? 0) / 1000 ?>s on page
                        <?php endif; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
(function() {
    var events = <?= json_encode(array_values($events)) ?>;
    var screenW = <?= $screenW ?>;
    var screenH = <?= $screenH ?>;

    // Parse timestamps to ms offsets from session start
    var startMs = new Date(events[0].event_timestamp.replace(' ', 'T')).getTime();
    var parsed = events.map(function(e) {
        e._offset = new Date(e.event_timestamp.replace(' ', 'T')).getTime() - startMs;
        return e;
    });
    var totalMs = parsed[parsed.length - 1]._offset || 1;

    var canvas    = document.getElementById('canvas');
    var timeline  = document.getElementById('timeline');
    var playhead  = document.getElementById('playhead');
    var playBtn   = document.getElementById('playBtn');
    var speedSel  = document.getElementById('speedSelect');
    var timDisp   = document.getElementById('timeDisplay');
    var scrollInd = document.getElementById('scroll-thumb');
    var idleOv    = document.getElementById('idle-overlay');
    var endOv     = document.getElementById('end-overlay');

    var playing   = false;
    var currentMs = 0;
    var lastFrame = null;
    var lastEventIdx = -1;

    // Place event markers on timeline
    parsed.forEach(function(e, i) {
        var pct = (e._offset / totalMs) * 100;
        var dot = document.createElement('div');
        dot.className = 'timeline-marker';
        dot.style.left = pct + '%';
        var colors = {click:'#3b82f6', scroll_depth:'#10b981', scroll_final:'#10b981', keyboard_activity:'#f59e0b', page_exit:'#ef4444'};
        dot.style.background = colors[e.type] || '#9ca3af';
        dot.title = e.type + ' @ ' + (e._offset / 1000).toFixed(1) + 's';
        timeline.appendChild(dot);
    });

    function updateTime() {
        timDisp.textContent = (currentMs / 1000).toFixed(1) + 's / ' + (totalMs / 1000).toFixed(1) + 's';
        playhead.style.left = ((currentMs / totalMs) * 100) + '%';
    }

    function clearVisuals() {
        canvas.querySelectorAll('.click-marker, .key-badge').forEach(function(el) { el.remove(); });
        scrollInd.style.height = '0%';
        idleOv.style.display = 'none';
        endOv.style.display = 'none';
        document.querySelectorAll('.replay-log-entry.active').forEach(function(el) { el.classList.remove('active'); });
    }

    function renderEvent(e, idx) {
        // Highlight log entry
        document.querySelectorAll('.replay-log-entry.active').forEach(function(el) { el.classList.remove('active'); });
        var logEl = document.getElementById('log-' + idx);
        if (logEl) {
            logEl.classList.add('active');
            logEl.scrollIntoView({block: 'nearest', behavior: 'smooth'});
        }

        if (e.type === 'click' && e.x != null && e.y != null) {
            var marker = document.createElement('div');
            marker.className = 'click-marker';
            marker.style.left = ((e.x / screenW) * 100) + '%';
            marker.style.top  = ((e.y / screenH) * 100) + '%';

            var label = e.tag_name ? e.tag_name.toLowerCase() : '';
            if (e.element_text) label += ': ' + e.element_text.substring(0, 30).trim();
            if (label) marker.title = label;

            canvas.appendChild(marker);
            setTimeout(function() { marker.classList.add('fade'); }, 50);
            setTimeout(function() { if (marker.parentNode) marker.remove(); }, 2000);
        }

        if (e.type === 'scroll_depth' || e.type === 'scroll_final') {
            var depth = parseInt(e.scroll_depth) || 0;
            scrollInd.style.height = Math.min(depth, 100) + '%';
        }

        if (e.type === 'keyboard_activity') {
            var badge = document.createElement('div');
            badge.className = 'key-badge';
            badge.textContent = e.key_name || '⌨';
            canvas.appendChild(badge);
            setTimeout(function() { badge.classList.add('fade'); }, 50);
            setTimeout(function() { if (badge.parentNode) badge.remove(); }, 1500);
        }

        if (e.type === 'page_exit') {
            var secs = e.time_on_page ? (parseInt(e.time_on_page) / 1000).toFixed(1) : '?';
            endOv.textContent = 'Session Ended — ' + secs + 's on page';
            endOv.style.display = 'flex';
        }
    }

    function renderUpTo(ms) {
        clearVisuals();
        lastEventIdx = -1;
        for (var i = 0; i < parsed.length; i++) {
            if (parsed[i]._offset <= ms) {
                lastEventIdx = i;
                // Only render clicks/keys for recent events (last 2s visual window)
                if (parsed[i].type === 'click' || parsed[i].type === 'keyboard_activity') {
                    if (ms - parsed[i]._offset < 2000) {
                        renderEvent(parsed[i], i);
                    }
                } else {
                    renderEvent(parsed[i], i);
                }
            }
        }
    }

    function tick(timestamp) {
        if (!playing) return;
        if (!lastFrame) lastFrame = timestamp;

        var delta = (timestamp - lastFrame) * parseFloat(speedSel.value);
        lastFrame = timestamp;
        currentMs += delta;

        if (currentMs >= totalMs) {
            currentMs = totalMs;
            playing = false;
            playBtn.innerHTML = '&#9654; Play';
            renderUpTo(currentMs);
            updateTime();
            return;
        }

        // Fire events that fall in this tick
        while (lastEventIdx + 1 < parsed.length && parsed[lastEventIdx + 1]._offset <= currentMs) {
            lastEventIdx++;
            renderEvent(parsed[lastEventIdx], lastEventIdx);
        }

        updateTime();
        requestAnimationFrame(tick);
    }

    playBtn.addEventListener('click', function() {
        if (playing) {
            playing = false;
            lastFrame = null;
            playBtn.innerHTML = '&#9654; Play';
        } else {
            if (currentMs >= totalMs) {
                currentMs = 0;
                lastEventIdx = -1;
                clearVisuals();
            }
            playing = true;
            lastFrame = null;
            playBtn.innerHTML = '&#10074;&#10074; Pause';
            requestAnimationFrame(tick);
        }
    });

    // Timeline click to scrub
    timeline.addEventListener('click', function(evt) {
        var rect = timeline.getBoundingClientRect();
        var pct = (evt.clientX - rect.left) / rect.width;
        currentMs = pct * totalMs;
        renderUpTo(currentMs);
        updateTime();
    });

    updateTime();
})();
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
