<?php
// Loads aggregated summary data from all three tables and passes it to the dashboard view.
// Directly reuses api/db.php for the DB connection rather than duplicating the connection setup.
class DashboardController {
    // Delegates all DB work to DashboardModel — controller only handles auth, wiring, and rendering.
    // Auth::require() stays outside the try/catch — a redirect for unauthenticated access is not an error.
    public static function index(): void {
        Auth::require();

        // Viewers have no access to live data — send them straight to saved reports.
        if ($_SESSION['role'] === 'viewer') {
            header('Location: /reports/saved');
            exit;
        }

        // Super admins see all sections. Analysts see only their permitted sections.
        $allowedSections = $_SESSION['role'] === 'super_admin'
            ? ['static', 'performance', 'activity']
            : $_SESSION['sections'];

        try {
            require_once __DIR__ . '/../../api/db.php';
            $model = new DashboardModel($pdo);

            $counts       = $model->getCounts();
            $networkTypes = $model->getNetworkTypes();
            $perfAvgs     = $model->getPerfAverages();
            $eventTypes   = $model->getEventTypes();
            $heatmapData  = $model->getActivityHeatmap();

            require __DIR__ . '/../views/dashboard.php';
        } catch (Exception $e) {
            http_response_code(500);
            require __DIR__ . '/../views/errors/500.php';
        }
    }
}
