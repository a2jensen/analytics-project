<?php
$pageTitle = 'Generate Report — Analytics';
require __DIR__ . '/../layout/header.php';
?>


<section id="form-wrapper">

<h1>Generate Report — <?= htmlspecialchars(ucfirst($section)) ?></h1>

<?php if (!empty($error)): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST" action="/reports/saved" id="generate-report">
    <input type="hidden" name="section" value="<?= htmlspecialchars($section) ?>">

    <div class="form-group">
        <label for="title">Report Title</label>
        <input type="text" id="title" name="title" required
               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="commentary">Commentary</label>
        <textarea id="commentary" name="commentary" rows="8"><?= htmlspecialchars($_POST['commentary'] ?? $autoCommentary ?? '') ?></textarea>
        <small style="color:#888; display:block; margin-top:4px;">Auto-generated insights are pre-filled. Edit or add your own commentary.</small>
    </div>

    <div class="form-group">
        <div style="display:flex; gap:1rem;">
            <div>
                <label for="id_from">From ID</label>
                <input type="number" id="id_from" name="id_from" min="1" required
                       value="<?= htmlspecialchars($_POST['id_from'] ?? '') ?>">
            </div>
            <div>
                <label for="id_to">To ID</label>
                <input type="number" id="id_to" name="id_to" min="1" required
                       value="<?= htmlspecialchars($_POST['id_to'] ?? '') ?>">
            </div>
        </div>
    </div>

    <div class="form-group">
        <label for="chart_type">Chart Type</label>
        <!--
        <select id="chart_type" name="chart_type">
            <?php foreach (['bar', 'line', 'pie', 'doughnut'] as $type): ?>
                <option value="<?= $type ?>" <?= ($_POST['chart_type'] ?? 'bar') === $type ? 'selected' : '' ?>>
                    <?= ucfirst($type) ?>
                </option>
            <?php endforeach; ?>
        </select>
            -->
        <div id="radio-group">
            <div>
                <input type="radio" id="bar" name="chart_type" value="bar" checked>
                <label for="bar">Bar</label>
            </div>
            <div>
                <input type="radio" id="line" name="chart_type" value="line">
                <label for="line">Line</label>
            </div>
            <div>
                <input type="radio" id="pie" name="chart_type" value="pie">
                <label for="pie">Pie</label>
            </div>
            <div>
                <input type="radio" id="doughnut" name="chart_type" value="doughnut">
                <label for="doughnut">Doughnut</label>
            </div>
        </div>
    </div>

    <button type="submit" class="btn">Save Report</button>
    <a href="/reports/<?= htmlspecialchars($section) ?>">Cancel</a>
</form>
            </section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
