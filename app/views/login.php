<?php
// $error is passed from AuthController::showLogin() after being read from the session,
// not from a query parameter — keeps the error off the URL.
$pageTitle = 'Login — Analytics'; require __DIR__ . '/layout/header.php'; ?>

<div class="login-wrap">
    <h1>Analytics Login</h1>

    <?php if (!empty($success)): ?>
        <p class="success"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" action="/login">
        <div>
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autofocus />
        </div>

        <div>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required />
        </div>

        <button type="submit">Log In</button>
    </form>

    <p style="margin-top:1rem;text-align:center;">Don't have an account? <a href="/signup">Sign up</a></p>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
