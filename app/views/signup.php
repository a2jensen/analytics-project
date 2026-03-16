<?php
$pageTitle = 'Sign Up — Analytics'; require __DIR__ . '/layout/header.php'; ?>

<div class="login-wrap">
    <h1>Create Account</h1>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" action="/signup">
        <div>
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autofocus
                   minlength="3" value="<?= htmlspecialchars($old_username ?? '') ?>" />
        </div>

        <div>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="8" />
        </div>

        <div>
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8" />
        </div>

        <button type="submit">Create Account</button>
    </form>

    <p style="margin-top:1rem;text-align:center;">Already have an account? <a href="/login">Log in</a></p>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
