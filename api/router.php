<?php
// Entry point for all /api/* requests. Parses the URL to determine the resource
// and optional ID, then delegates to the correct handler file.
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// parse the URL
// ex. /api/static/5 -> ['api', 'static', '5']
$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts = explode('/', $uri);

// $parts[0] = 'api'
// $parts[1] = 'static', 'performance', or 'activity'
// $parts[2] = id (optional)
$resource = $parts[1] ?? null;
$id       = $parts[2] ?? null;
$method   = $_SERVER['REQUEST_METHOD'];

// Rate limit API requests — 100 per minute per IP
require_once __DIR__ . '/../app/core/RateLimiter.php';
require_once __DIR__ . '/db.php';

$limiter = new RateLimiter($pdo);
$ip = $_SERVER['REMOTE_ADDR'];

if ($limiter->isRateLimited($ip, 'api', 100, 60)) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    exit;
}

$limiter->recordAttempt($ip, 'api');

// route to correct handler
// Wrapped in try/catch so a DB connection failure or unhandled query error returns JSON,
// not a raw PHP error — db.php no longer catches internally so this is the API-side safety net.
try {
    switch ($resource) {
        case 'static':
            require_once 'static.php';
            handleStatic($method, $id);
            break;
        case 'performance':
            require_once 'performance.php';
            handlePerformance($method, $id);
            break;
        case 'activity':
            require_once 'activity.php';
            handleActivity($method, $id);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'resource not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'internal server error']);
}
?>