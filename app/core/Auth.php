<?php
// Thin session-based auth helper used by all protected controllers.
class Auth {
    // Returns true if a user session exists — used to conditionally show the nav bar in the header view.
    public static function check(): bool {
        return isset($_SESSION['user']);
    }

    // Hard-gates protected routes. Redirects to /login and exits immediately if no session is found,
    // preventing forceful browsing.
    public static function require(): void {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
    }

    // Gates routes by role. Must be called after Auth::require() so a session is guaranteed to exist.
    // Redirects unauthenticated users to /login; renders 403 for authenticated users with the wrong role.
    public static function requireRole(string ...$roles): void {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
        if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
            http_response_code(403);
            require __DIR__ . '/../views/errors/403.php';
            exit;
        }
    }

    // Returns true if the current user may access the given section.
    // Super admins always pass. Analysts pass only if the section is in their permitted list.
    // Viewers never pass — they have no section access.
    public static function canAccessSection(string $section): bool {
        $role     = $_SESSION['role']     ?? '';
        $sections = $_SESSION['sections'] ?? [];

        if ($role === 'super_admin') {
            return true;
        }
        if ($role === 'analyst') {
            return in_array($section, $sections, true);
        }
        return false;
    }
}
