<?php
// Handles login/logout. Credentials are looked up from the users table via UserModel —
// nothing is hardcoded in this file.
class AuthController {
    // Reads and clears any pending login error from the session before rendering.
    // The error is stored in the session (not a query param) so it survives the redirect
    // without being visible in the URL.
    public static function showLogin(): void {
        $error   = $_SESSION['login_error'] ?? null;
        $success = $_SESSION['signup_success'] ?? null;
        unset($_SESSION['login_error'], $_SESSION['signup_success']);
        require __DIR__ . '/../views/login.php';
    }

    // Looks up the submitted username in the DB, then uses password_verify against the stored
    // bcrypt hash. Calls session_regenerate_id(true) on success to prevent session fixation.
    // Uses a generic error message to avoid leaking whether the username or password was wrong.
    // DB errors are caught and surfaced as a login-unavailable message rather than a crash.
    public static function handleLogin(): void {
        $username      = trim($_POST['username'] ?? '');
        $inputPassword = $_POST['password'] ?? '';

        // require_once db.php after capturing POST values — db.php defines $password (the DB
        // connection password) which would overwrite a local $password variable if set first.
        try {
            require_once __DIR__ . '/../../api/db.php';
            require_once __DIR__ . '/../core/RateLimiter.php';

            $limiter = new RateLimiter($pdo);
            $ip = $_SERVER['REMOTE_ADDR'];

            if ($limiter->isRateLimited($ip, 'login', 5, 900)) {
                $_SESSION['login_error'] = 'Too many login attempts. Please try again in 15 minutes.';
                header('Location: /login');
                exit;
            }

            $limiter->recordAttempt($ip, 'login');

            $userModel = new UserModel($pdo);
            $user      = $userModel->findByUsername($username);

            // DEBUG
            error_log("DEBUG handleLogin(): " . print_r([
                'username'             => $username,
                'input_password'       => $inputPassword,
                'input_password_len'   => strlen($inputPassword),
                'input_password_bytes' => bin2hex($inputPassword),
                'input_password_hash'  => password_hash($inputPassword, PASSWORD_BCRYPT),
                'stored_hash'          => $user ? $user['password_hash'] : '(user not found)',
                'stored_hash_len'      => $user ? strlen($user['password_hash']) : 'N/A',
                'verify_result'        => $user ? (password_verify($inputPassword, $user['password_hash']) ? 'PASS' : 'FAIL') : 'N/A',
            ], true));
            // END DEBUG

            if ($user && password_verify($inputPassword, $user['password_hash'])) {
                $limiter->clearAttempts($ip, 'login');
                session_regenerate_id(true); // prevent session fixation
                $_SESSION['user']     = $user['username'];
                $_SESSION['role']     = $user['role'];
                $_SESSION['sections'] = $userModel->getSectionsForUser((int)$user['id']);
                header('Location: /dashboard');
                exit;
            }

            // Generic message avoids leaking whether the username or password was wrong
            $_SESSION['login_error'] = 'Invalid username or password.';
            header('Location: /login');
            exit;
        } catch (Exception $e) {
            // DB unavailable — show a safe message rather than crashing
            $_SESSION['login_error'] = 'Login is currently unavailable. Please try again later.';
            header('Location: /login');
            exit;
        }
    }

    public static function showSignup(): void {
        $error = $_SESSION['signup_error'] ?? null;
        $old_username = $_SESSION['signup_old_username'] ?? '';
        unset($_SESSION['signup_error'], $_SESSION['signup_old_username']);
        require __DIR__ . '/../views/signup.php';
    }

    public static function handleSignup(): void {
        $username        = trim($_POST['username'] ?? '');
        $inputPassword   = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validation
        if (strlen($username) < 3) {
            $_SESSION['signup_error'] = 'Username must be at least 3 characters.';
            $_SESSION['signup_old_username'] = $username;
            header('Location: /signup');
            exit;
        }

        if (strlen($inputPassword) < 8) {
            $_SESSION['signup_error'] = 'Password must be at least 8 characters.';
            $_SESSION['signup_old_username'] = $username;
            header('Location: /signup');
            exit;
        }

        if ($inputPassword !== $confirmPassword) {
            $_SESSION['signup_error'] = 'Passwords do not match.';
            $_SESSION['signup_old_username'] = $username;
            header('Location: /signup');
            exit;
        }

        // require_once db.php after capturing POST values — db.php defines $password (the DB
        // connection password) which would overwrite a local $password variable if set first.
        try {
            require_once __DIR__ . '/../../api/db.php';
            $userModel = new UserModel($pdo);

            if ($userModel->findByUsername($username)) {
                $_SESSION['signup_error'] = 'Username already taken.';
                $_SESSION['signup_old_username'] = $username;
                header('Location: /signup');
                exit;
            }

            $hash = password_hash($inputPassword, PASSWORD_BCRYPT);
            $userModel->create($username, $hash, 'viewer');

            $_SESSION['signup_success'] = 'Account created. Please log in.';
            header('Location: /login');
            exit;
        } catch (Exception $e) {
            $_SESSION['signup_error'] = 'Registration is currently unavailable. Please try again later.';
            $_SESSION['signup_old_username'] = $username;
            header('Location: /signup');
            exit;
        }
    }

    // Fully destroys the session rather than just unsetting the user key,
    // ensuring all session data is cleared.
    public static function logout(): void {
        session_destroy();
        header('Location: /login');
        exit;
    }
}
