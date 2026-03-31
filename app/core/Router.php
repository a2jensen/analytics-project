<?php
// Front-controller router for all web page routes (/login, /dashboard, /reports/*, /users).
// Completely separate from api/router.php which handles /api/* — the .htaccess rules keep them
// separated by path prefix.
//
// Route table design: each entry is [METHOD, pattern, handler].
// Patterns use {placeholder} syntax compiled to named regex groups.
// HTML forms only speak GET/POST, so PUT/DELETE are tunnelled via a hidden _method field.
class Router {
    // Converts a route pattern like /users/{id}/edit into a named-capture regex,
    // then matches it against the actual request path.
    // Returns an associative array of captured params on match, false otherwise.
    private static function matchPath(string $pattern, string $path): array|false {
        $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '@^' . $regex . '$@';
        if (!preg_match($regex, $path, $matches)) return false;
        // Keep only the named captures (string keys), drop numeric indices
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    public static function dispatch(): void {
        $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path   = rtrim($path, '/') ?: '/';
        $method = strtoupper($_SERVER['REQUEST_METHOD']);

        // Method override: HTML forms submit PUT/DELETE as POST with a hidden _method field
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper($_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        require_once __DIR__ . '/../models/User.php';
        require_once __DIR__ . '/../models/DashboardModel.php';
        require_once __DIR__ . '/../models/StaticModel.php';
        require_once __DIR__ . '/../models/PerformanceModel.php';
        require_once __DIR__ . '/../models/ActivityModel.php';
        require_once __DIR__ . '/../models/ReportModel.php';
        require_once __DIR__ . '/../controllers/AuthController.php';
        require_once __DIR__ . '/../controllers/DashboardController.php';
        require_once __DIR__ . '/../controllers/ReportsController.php';
        require_once __DIR__ . '/../controllers/UserController.php';
        require_once __DIR__ . '/../models/SessionReplayModel.php';
        require_once __DIR__ . '/../controllers/ReportController.php';
        require_once __DIR__ . '/../controllers/SessionReplayController.php';
        require_once __DIR__ . '/CommentaryGenerator.php';

        // Route table: [HTTP method, path pattern, handler]
        // Patterns: {placeholder} matches any non-slash segment.
        // Handlers: callable — either a closure or [ClassName, 'method'].
        // All handlers receive an array of matched path params (may be empty).
        $routes = [
            ['GET',    '/',                        [DashboardController::class, 'index']],
            ['GET',    '/dashboard',               [DashboardController::class, 'index']],

            ['GET',    '/login',                   [AuthController::class, 'showLogin']],
            ['POST',   '/login',                   [AuthController::class, 'handleLogin']],
            ['GET',    '/signup',                  [AuthController::class, 'showSignup']],
            ['POST',   '/signup',                  [AuthController::class, 'handleSignup']],
            ['POST',   '/logout',                  [AuthController::class, 'logout']],

            ['GET',    '/reports/static',          [ReportsController::class, 'staticData']],
            ['GET',    '/reports/performance',     [ReportsController::class, 'performanceData']],
            ['GET',    '/reports/activity',        [ReportsController::class, 'activityData']],

            // /new suffix is the REST convention for "show the form to create a resource"
            ['GET',    '/reports/static/new',      fn($p) => ReportController::generate('static')],
            ['GET',    '/reports/performance/new', fn($p) => ReportController::generate('performance')],
            ['GET',    '/reports/activity/new',    fn($p) => ReportController::generate('activity')],

            ['GET',    '/reports/saved',           [ReportController::class, 'index']],
            ['POST',   '/reports/saved',           [ReportController::class, 'store']],
            ['GET',    '/reports/saved/{id}',      [ReportController::class, 'view']],

            ['GET',    '/replay',                  [SessionReplayController::class, 'index']],
            ['GET',    '/replay/{session_id}',     [SessionReplayController::class, 'show']],

            ['GET',    '/users',                   [UserController::class, 'index']],
            ['GET',    '/users/new',               [UserController::class, 'create']],
            ['POST',   '/users',                   [UserController::class, 'store']],
            ['GET',    '/users/{id}/edit',         [UserController::class, 'edit']],
            ['PUT',    '/users/{id}',              [UserController::class, 'update']],
            ['DELETE', '/users/{id}',              [UserController::class, 'destroy']],
        ];

        // Track which methods are valid for this path (for 405 responses)
        $allowedMethods = [];

        foreach ($routes as [$routeMethod, $pattern, $handler]) {
            $params = self::matchPath($pattern, $path);
            if ($params === false) continue;

            // Path matched — record this method as allowed regardless of request method
            $allowedMethods[] = $routeMethod;

            if ($routeMethod !== $method) continue;

            // Full match: path + method — dispatch and return
            call_user_func($handler, $params);
            return;
        }

        if (!empty($allowedMethods)) {
            // Route exists but not for this HTTP method
            http_response_code(405);
            header('Allow: ' . implode(', ', array_unique($allowedMethods)));
            require __DIR__ . '/../views/errors/404.php';
            return;
        }

        http_response_code(404);
        require __DIR__ . '/../views/errors/404.php';
    }
}
