<?php $pageTitle = 'Saved Reports — Analytics'; require __DIR__ . '/../layout/header.php'; ?>

<h1>Saved Reports</h1>
<p><?= count($reports) ?> of <?= $total ?> reports</p>

<?php require __DIR__ . '/../layout/pagination.php'; ?>

<?php if (empty($reports)): ?>
    <p>No saved reports yet.</p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Section</th>
                    <th>Created By</th>
                    <th>Created</th>
                    <th>View</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($reports as $report): ?>
                <tr>
                    <td><?= htmlspecialchars($report['title']) ?></td>
                    <td><?= htmlspecialchars($report['section']) ?></td>
                    <td><?= htmlspecialchars($report['created_by_username']) ?></td>
                    <td><?= htmlspecialchars($report['created_at']) ?></td>
                    <td><a href="/reports/saved/view?id=<?= (int)$report['id'] ?>" class="btn">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
