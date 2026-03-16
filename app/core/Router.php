<?php
// Front-controller router for all web page routes (/login, /dashboard, /reports/*, /users).
// Completely separate from api/router.php which handles /api/* — the .htaccess rules keep them
// separated by path prefix.
class Router {
    // Strips trailing slashes then normalises an empty path to / so both / and /dashboard hit
    // the same case. Requires all controllers up front rather than lazily, since any route could
    // be matched.
    public static function dispatch(): void {
        $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path   = rtrim($path, '/') ?: '/';
        $method = $_SERVER['REQUEST_METHOD'];

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
        require_once __DIR__ . '/../controllers/ReportController.php';
        require_once __DIR__ . '/../controllers/SessionReplayController.php';
        require_once __DIR__ . '/../models/SessionReplayModel.php';
        require_once __DIR__ . '/CommentaryGenerator.php';

        switch ($path) {
            case '/':
            case '/dashboard':
                DashboardController::index();
                break;

            case '/login':
                if ($method === 'POST') {
                    AuthController::handleLogin();
                } else {
                    AuthController::showLogin();
                }
                break;

            case '/signup':
                if ($method === 'POST') {
                    AuthController::handleSignup();
                } else {
                    AuthController::showSignup();
                }
                break;

            case '/logout':
                AuthController::logout();
                break;

            case '/reports/static':
                ReportsController::staticData();
                break;

            case '/reports/performance':
                ReportsController::performanceData();
                break;

            case '/reports/activity':
                ReportsController::activityData();
                break;

            case '/reports/saved':
                ReportController::index();
                break;

            case '/reports/saved/store':
                ReportController::store();
                break;

            case '/reports/saved/view':
                ReportController::view();
                break;

            case '/reports/static/generate':
                ReportController::generate('static');
                break;

            case '/reports/performance/generate':
                ReportController::generate('performance');
                break;

            case '/reports/activity/generate':
                ReportController::generate('activity');
                break;

            case '/replay':
                SessionReplayController::index();
                break;

            case '/replay/show':
                SessionReplayController::show();
                break;

            case '/users':
                UserController::index();
                break;

            case '/users/create':
                UserController::create();
                break;

            case '/users/store':
                UserController::store();
                break;

            case '/users/edit':
                UserController::edit();
                break;

            case '/users/update':
                UserController::update();
                break;

            case '/users/delete':
                UserController::destroy();
                break;

            default:
                http_response_code(404);
                require __DIR__ . '/../views/errors/404.php';
        }
    }
}
