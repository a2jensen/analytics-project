<?php
// Fetches full table data for each of the three report pages and hands it to the corresponding view.
// All three methods follow the same pattern: auth guard → section check → fetch → render view.
// Auth::require() stays outside the try/catch — a redirect for unauthenticated access is not an error.
class ReportsController {
    private static function parsePage(int $total, int $perPage = 25): array {
        $totalPages  = max(1, (int) ceil($total / $perPage));
        $page        = max(1, min((int)($_GET['page'] ?? 1), $totalPages));
        return [$page, $totalPages];
    }

    public static function staticData(): void {
        Auth::require();
        if (!Auth::canAccessSection('static')) {
            http_response_code(403);
            require __DIR__ . '/../views/errors/403.php';
            return;
        }
        try {
            require_once __DIR__ . '/../../api/db.php';
            $model                = new StaticModel($pdo);
            $total                = $model->countAll();
            [$page, $totalPages]  = self::parsePage($total);
            $rows                 = $model->getPage($page);
            $networkTypes         = $model->getNetworkTypes();
            $memoryDist           = $model->getMemoryDistribution();
            $coresDist            = $model->getCoresDistribution();
            $baseUrl              = '/reports/static';
            require __DIR__ . '/../views/static.php';
        } catch (Exception $e) {
            http_response_code(500);
            require __DIR__ . '/../views/errors/500.php';
        }
    }

    public static function performanceData(): void {
        Auth::require();
        if (!Auth::canAccessSection('performance')) {
            http_response_code(403);
            require __DIR__ . '/../views/errors/403.php';
            return;
        }
        try {
            require_once __DIR__ . '/../../api/db.php';
            $model                = new PerformanceModel($pdo);
            $total                = $model->countAll();
            [$page, $totalPages]  = self::parsePage($total);
            $rows                 = $model->getPage($page);
            $chartRows            = $model->getRecentForChart(10);
            $webVitals            = $model->getWebVitalsAvg();
            $networkTiming        = $model->getNetworkTimingAvg();
            $baseUrl              = '/reports/performance';
            require __DIR__ . '/../views/performance.php';
        } catch (Exception $e) {
            http_response_code(500);
            require __DIR__ . '/../views/errors/500.php';
        }
    }

    public static function activityData(): void {
        Auth::require();
        if (!Auth::canAccessSection('activity')) {
            http_response_code(403);
            require __DIR__ . '/../views/errors/403.php';
            return;
        }
        try {
            require_once __DIR__ . '/../../api/db.php';
            $model                = new ActivityModel($pdo);
            $total                = $model->countAll();
            [$page, $totalPages]  = self::parsePage($total);
            $rows                 = $model->getPage($page);
            $typeCounts           = $model->getTypeCounts();
            $clickedElements      = $model->getClickedElements();
            $scrollDepthDist      = $model->getScrollDepthDistribution();
            $baseUrl              = '/reports/activity';
            require __DIR__ . '/../views/activity.php';
        } catch (Exception $e) {
            http_response_code(500);
            require __DIR__ . '/../views/errors/500.php';
        }
    }
}
