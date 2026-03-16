<?php $pageTitle = 'User Management — Analytics'; require __DIR__ . '/../layout/header.php'; ?>

<h1>User Management</h1>
<a href="/users/create" class="btn">Add User</a>
<p><?= count($users) ?> of <?= $total ?> users</p>

<?php require __DIR__ . '/../layout/pagination.php'; ?>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>Sections</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['role']) ?></td>
                <td><?= $u['sections'] ? htmlspecialchars($u['sections']) : '—' ?></td>
                <td><?= htmlspecialchars($u['created_at']) ?></td>
                <td>
                    <a href="/users/edit?id=<?= $u['id'] ?>" class="btn">Edit</a>
                    <?php if ($u['username'] !== $_SESSION['user']): ?>
                    <form method="POST" action="/users/delete" style="display:inline"
                          onsubmit="return confirm('Delete <?= htmlspecialchars($u['username']) ?>?')">
                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                        <button type="submit" class="btn-link" id="delete">Delete</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
