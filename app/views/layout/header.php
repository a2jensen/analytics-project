<?php
// Shared HTML head and nav bar included at the top of every view.
// Accepts $pageTitle from the including view; falls back to 'Analytics' if not set.
// Nav bar is only rendered when a session exists — so it is hidden on the login page
// without any extra logic in the views.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($pageTitle ?? 'Analytics') ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="/public/css/style.css" />
</head>
<body>
<?php if (Auth::check()):
    $navRole     = $_SESSION['role'] ?? '';
    $navSections = $_SESSION['sections'] ?? [];
    $navCurrent  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $navActive   = fn(string $href): string => $navCurrent === $href ? ' class="active"' : '';
?>
<nav>
    <span class="nav-brand">Analytics</span>

    <?php if ($navRole === 'super_admin' || $navRole === 'analyst'): ?>
        <a href="/dashboard"<?= $navActive('/dashboard') ?>>Dashboard</a>
    <?php endif; ?>

    <?php if ($navRole === 'super_admin' || ($navRole === 'analyst' && in_array('static', $navSections))): ?>
        <a href="/reports/static"<?= $navActive('/reports/static') ?>>Static</a>
    <?php endif; ?>

    <?php if ($navRole === 'super_admin' || ($navRole === 'analyst' && in_array('performance', $navSections))): ?>
        <a href="/reports/performance"<?= $navActive('/reports/performance') ?>>Performance</a>
    <?php endif; ?>

    <?php if ($navRole === 'super_admin' || ($navRole === 'analyst' && in_array('activity', $navSections))): ?>
        <a href="/reports/activity"<?= $navActive('/reports/activity') ?>>Activity</a>
    <?php endif; ?>

    <a href="/reports/saved"<?= $navActive('/reports/saved') ?>>Saved Reports</a>

    <?php if ($navRole === 'super_admin'): ?>
        <a href="/users"<?= $navActive('/users') ?>>Users</a>
    <?php endif; ?>

    <form method="POST" action="/logout" style="display:inline">
        <button type="submit" class="nav-logout">Logout</button>
    </form>
</nav>
<?php endif; ?>
<main>
