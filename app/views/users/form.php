<?php
$isEdit     = isset($user);
$pageTitle  = $isEdit ? 'Edit User — Analytics' : 'Add User — Analytics';
require __DIR__ . '/../layout/header.php';
?>

<section id="form-wrapper">

<h1><?= $isEdit ? 'Edit User' : 'Add User' ?></h1>

<?php if (!empty($error)): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST" action="<?= $isEdit ? '/users/' . $user['id'] : '/users' ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="_method" value="PUT">
    <?php endif; ?>

    <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username"
               value="<?= htmlspecialchars($user['username'] ?? '') ?>"
               <?= $isEdit ? 'readonly' : 'required' ?>>
    </div>

    <div class="form-group">
        <?php if ($isEdit): ?>
            <div>
            <input type="checkbox" id="change-password-toggle" name="change_password" value="1" onchange="togglePasswordField(this.checked)">
            <label>
                Change password
            </label>
            </div>
            <div id="password-field" style="display:none; margin-top:0.5rem;">
                <input type="password" id="password" name="password" autocomplete="new-password" placeholder="New Password" disabled>
            </div>
        <?php else: ?>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="role">Role</label>
        <select id="role" name="role" required onchange="toggleSections(this.value)">
            <option value="viewer"      <?= ($user['role'] ?? '') === 'viewer'      ? 'selected' : '' ?>>Viewer</option>
            <option value="analyst"     <?= ($user['role'] ?? '') === 'analyst'     ? 'selected' : '' ?>>Analyst</option>
            <option value="super_admin" <?= ($user['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
        </select>
    </div>

    <!-- Section checkboxes — only relevant for analysts, hidden otherwise -->
    <div class="form-group" id="sections-group"
         style="<?= ($user['role'] ?? '') !== 'analyst' ? 'display:none' : '' ?>">
        <label>Sections</label>
        <label><input type="checkbox" name="sections[]" value="static"
            <?= in_array('static',      $userSections ?? []) ? 'checked' : '' ?>> Static</label>
        <label><input type="checkbox" name="sections[]" value="performance"
            <?= in_array('performance', $userSections ?? []) ? 'checked' : '' ?>> Performance</label>
        <label><input type="checkbox" name="sections[]" value="activity"
            <?= in_array('activity',    $userSections ?? []) ? 'checked' : '' ?>> Activity</label>
    </div>

    <button type="submit" class="btn"><?= $isEdit ? 'Save Changes' : 'Create User' ?></button>
    <a href="/users">Cancel</a>
</form>
</section>

<script>
// Show section checkboxes only when role is analyst
function toggleSections(role) {
    document.getElementById('sections-group').style.display =
        role === 'analyst' ? '' : 'none';
}

// Show/hide password field based on checkbox — field is hidden by default in edit mode
// so browser autofill never targets it unless the user explicitly opts in
function togglePasswordField(checked) {
    const wrap = document.getElementById('password-field');
    const input = document.getElementById('password');
    wrap.style.display = checked ? '' : 'none';
    input.disabled = !checked; // disabled fields are NOT submitted in POST — browser can't sneak them in
    input.value = '';
}
/** 
// DEBUG — log password field value on submit before it reaches the server
document.querySelector('form').addEventListener('submit', function(e) {
    const pw = document.getElementById('password').value;
    console.log('password field length:', pw.length);
    console.log('password field value:', JSON.stringify(pw));
    if (pw.length > 0) {
        alert('WARNING: password field is not empty — value length: ' + pw.length + '\nValue: ' + JSON.stringify(pw));
        e.preventDefault(); // block submit so you can see the value before it gets sent
    }
}); */
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
