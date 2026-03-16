<?php
// Handles user management — listing, creating, editing, and deleting users.
// All methods are super_admin only.
class UserController {
    public static function index(): void {
        Auth::requireRole('super_admin');
        try {
            require_once __DIR__ . '/../../api/db.php';
            $model               = new UserModel($pdo);
            $total               = $model->countAll();
            $totalPages          = max(1, (int) ceil($total / 25));
            $page                = max(1, min((int)($_GET['page'] ?? 1), $totalPages));
            $users               = $model->getPageWithSections($page);
            $baseUrl             = '/users';
            require __DIR__ . '/../views/users/index.php';
        } catch (Exception $e) {
            http_response_code(500);
            require __DIR__ . '/../views/errors/500.php';
        }
    }

    public static function create(): void {
        Auth::requireRole('super_admin');
        $user         = null;
        $userSections = [];
        $error        = null;
        require __DIR__ . '/../views/users/form.php';
    }

    public static function store(): void {
        Auth::requireRole('super_admin');

        $username    = trim($_POST['username'] ?? '');
        $newPassword = $_POST['password'] ?? '';
        $role        = $_POST['role'] ?? '';
        $sections    = $_POST['sections'] ?? [];

        $validRoles = ['super_admin', 'analyst', 'viewer'];
        $validSections = ['static', 'performance', 'activity'];

        // Validate inputs
        if (empty($username) || empty($newPassword) || !in_array($role, $validRoles, true)) {
            $user         = null;
            $userSections = [];
            $error        = 'Username, password, and a valid role are required.';
            require __DIR__ . '/../views/users/form.php';
            return;
        }

        // Sanitise sections — only analysts get sections, and only valid values are accepted
        $sections = $role === 'analyst'
            ? array_values(array_intersect($sections, $validSections))
            : [];

        try {
            require_once __DIR__ . '/../../api/db.php';
            $model  = new UserModel($pdo);
            // $newPassword avoids collision with $password defined by api/db.php
            $hash   = password_hash($newPassword, PASSWORD_BCRYPT);
            $userId = $model->create($username, $hash, $role);
            $model->replaceSections($userId, $sections);
            header('Location: /users');
            exit;
        } catch (Exception $e) {
            $user         = null;
            $userSections = [];
            $error        = 'Could not create user. The username may already be taken.';
            require __DIR__ . '/../views/users/form.php';
        }
    }

    public static function edit(): void {
        Auth::requireRole('super_admin');
        $id = (int)($_GET['id'] ?? 0);

        try {
            require_once __DIR__ . '/../../api/db.php';
            $model        = new UserModel($pdo);
            $user         = $model->findById($id);
            $userSections = $user ? $model->getSectionsForUser($id) : [];

            if (!$user) {
                http_response_code(404);
                require __DIR__ . '/../views/errors/404.php';
                return;
            }

            $error = null;
            require __DIR__ . '/../views/users/form.php';
        } catch (Exception $e) {
            http_response_code(500);
            require __DIR__ . '/../views/errors/500.php';
        }
    }

    public static function update(): void {
        Auth::requireRole('super_admin');

        $id             = (int)($_POST['id'] ?? 0);
        $role           = $_POST['role'] ?? '';
        $newPassword    = $_POST['password'] ?? '';
        $changePassword = isset($_POST['change_password']);
        $sections       = $_POST['sections'] ?? [];

        $validRoles    = ['super_admin', 'analyst', 'viewer'];
        $validSections = ['static', 'performance', 'activity'];

        if (!$id || !in_array($role, $validRoles, true)) {
            http_response_code(400);
            require __DIR__ . '/../views/errors/500.php';
            return;
        }

        // Only analysts get sections; non-analyst roles get sections cleared (least-privilege)
        $sections = $role === 'analyst'
            ? array_values(array_intersect($sections, $validSections))
            : [];

        try {
            require_once __DIR__ . '/../../api/db.php';
            $model = new UserModel($pdo);
            // Only update password if the user explicitly checked "Change password"
            // — ignores whatever the browser may have autofilled into the field
            // $newPassword avoids collision with $password defined by api/db.php
            $hash = ($changePassword && !empty($newPassword))
                ? password_hash($newPassword, PASSWORD_BCRYPT)
                : null;
            $model->update($id, $role, $hash);
            $model->replaceSections($id, $sections);
            header('Location: /users');
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            require __DIR__ . '/../views/errors/500.php';
        }
    }

    public static function destroy(): void {
        Auth::requireRole('super_admin');

        $id = (int)($_POST['id'] ?? 0);

        // Prevent self-deletion — admin cannot delete their own account
        try {
            require_once __DIR__ . '/../../api/db.php';
            $model = new UserModel($pdo);
            $user  = $model->findById($id);

            if (!$user || $user['username'] === $_SESSION['user']) {
                header('Location: /users');
                exit;
            }

            $model->deleteById($id);
            header('Location: /users');
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            require __DIR__ . '/../views/errors/500.php';
        }
    }
}
