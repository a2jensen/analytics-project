<?php
// Shared pagination controls. Expects $page, $totalPages, $baseUrl to be set by the including view.
// Renders nothing if there is only one page.
if ($totalPages <= 1) return;
?>
<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="<?= htmlspecialchars($baseUrl) ?>?page=<?= $page - 1 ?>" class="btn-page">&larr; Previous</a>
    <?php else: ?>
        <span class="btn-page disabled">&larr; Previous</span>
    <?php endif; ?>

    <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>

    <?php if ($page < $totalPages): ?>
        <a href="<?= htmlspecialchars($baseUrl) ?>?page=<?= $page + 1 ?>" class="btn-page">Next &rarr;</a>
    <?php else: ?>
        <span class="btn-page disabled">Next &rarr;</span>
    <?php endif; ?>
</div>
