<?php $pageTitle = 'Session Replay — Analytics'; require __DIR__ . '/../layout/header.php'; ?>

<h1>Session Replay</h1>
<p><?= $total ?> session<?= $total !== 1 ? 's' : '' ?> recorded</p>

<?php require __DIR__ . '/../layout/pagination.php'; ?>

<div class="table-wrap" style="margin-top:1rem;">
    <table>
        <thead>
            <tr>
                <th>Session ID</th>
                <th>URL</th>
                <th>Events</th>
                <th>Start</th>
                <th>End</th>
                <th>Duration</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($sessions as $s):
            $start = strtotime($s['session_start']);
            $end   = strtotime($s['session_end']);
            $dur   = max(0, $end - $start);
            if ($dur >= 60) {
                $durStr = floor($dur / 60) . 'm ' . ($dur % 60) . 's';
            } else {
                $durStr = $dur . 's';
            }
        ?>
            <tr>
                <td title="<?= htmlspecialchars($s['session_id']) ?>"><?= htmlspecialchars(substr($s['session_id'], 0, 12)) ?>…</td>
                <td title="<?= htmlspecialchars($s['url']) ?>"><?= htmlspecialchars(parse_url($s['url'], PHP_URL_PATH) ?: '/') ?></td>
                <td><?= (int)$s['event_count'] ?></td>
                <td><?= htmlspecialchars($s['session_start']) ?></td>
                <td><?= htmlspecialchars($s['session_end']) ?></td>
                <td><?= $durStr ?></td>
                <td><a href="/replay/<?= urlencode($s['session_id']) ?>" class="btn" style="font-size:0.8rem;padding:0.3rem 0.6rem;">Replay</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($sessions)): ?>
            <tr><td colspan="7" style="text-align:center;padding:2rem;">No sessions found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../layout/pagination.php'; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
